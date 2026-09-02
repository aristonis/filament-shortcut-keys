import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import { createTableHandler } from '../../resources/js/table.js'
import {
    createElement,
    installDocument,
    restoreGlobals,
} from './support/dom.mjs'

const ROW_SELECTOR = 'tr.fi-ta-row'
const SEARCH_SELECTOR = '.fi-ta-search-field input, input[type="search"]'
const FOCUS_CLASS = 'fi-hotkey-row-focused'
const EDIT_SELECTOR = '[wire\\:key*=".actions.edit."]'
const DELETE_SELECTOR = '[wire\\:key*=".actions.delete."]'

function row(selectors = {}) {
    return createElement({ tagName: 'TR', selectors })
}

function table(rows, extra = {}) {
    return installDocument({ selectors: { [ROW_SELECTOR]: rows, ...extra } })
}

function behavior(name) {
    return { code: 'KeyX', activation: { kind: 'table', behavior: name } }
}

function focusedIndexOf(rows) {
    return rows.findIndex((candidate) =>
        candidate.classList.contains(FOCUS_CLASS),
    )
}

afterEach(restoreGlobals)

describe('the row cursor', () => {
    it('starts at the top row when moving down from nowhere', () => {
        const rows = [row(), row(), row()]
        table(rows)
        const handle = createTableHandler()

        assert.equal(handle(behavior('row-down')), true)
        assert.equal(focusedIndexOf(rows), 0)
    })

    it('starts at the bottom row when moving up from nowhere', () => {
        const rows = [row(), row(), row()]
        table(rows)
        const handle = createTableHandler()

        assert.equal(handle(behavior('row-up')), true)
        assert.equal(focusedIndexOf(rows), 2)
    })

    it('stops at the last row instead of wrapping', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('row-down'))
        handle(behavior('row-down'))
        handle(behavior('row-down'))

        assert.equal(focusedIndexOf(rows), 1)
    })

    it('stops at the first row instead of wrapping', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('row-down'))
        handle(behavior('row-up'))
        handle(behavior('row-up'))

        assert.equal(focusedIndexOf(rows), 0)
    })

    it('marks exactly one row and scrolls it into view', () => {
        const rows = [row(), row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('row-down'))
        handle(behavior('row-down'))

        assert.equal(
            rows.filter((candidate) =>
                candidate.classList.contains(FOCUS_CLASS),
            ).length,
            1,
        )
        assert.equal(rows[1].style.outline, '2px solid currentColor')
        assert.equal(rows[0].style.outline, '')
        assert.deepEqual(rows[1].scrollCalls, [{ block: 'nearest' }])
    })

    it('reports failure on an empty table, so the key is not swallowed', () => {
        table([])
        const handle = createTableHandler()

        assert.equal(handle(behavior('row-down')), false)
    })

    it('drops the cursor on reset, because it belongs to the page that was left', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('row-down'))
        handle(behavior('row-down'))
        handle.reset()
        handle(behavior('row-down'))

        assert.equal(focusedIndexOf(rows), 0)
    })
})

describe('row-scoped actions', () => {
    it('acts on the focused row rather than the first row in the table', () => {
        const first = row({
            'input[type="checkbox"]': createElement({ tagName: 'INPUT' }),
        })
        const second = row({
            'input[type="checkbox"]': createElement({ tagName: 'INPUT' }),
        })
        table([first, second])
        const handle = createTableHandler()

        handle(behavior('row-down'))
        handle(behavior('row-down'))

        assert.equal(handle(behavior('select')), true)
        assert.equal(second.querySelector('input[type="checkbox"]').clicks, 1)
        assert.equal(first.querySelector('input[type="checkbox"]').clicks, 0)
    })

    it('reaches Edit and Delete by wire:key, which both the modal and the page shape emit', () => {
        const edit = createElement({ tagName: 'A' })
        const remove = createElement({ tagName: 'BUTTON' })
        const only = row({ [EDIT_SELECTOR]: edit, [DELETE_SELECTOR]: remove })
        table([only])
        const handle = createTableHandler()
        handle(behavior('row-down'))

        assert.equal(handle(behavior('edit')), true)
        assert.equal(handle(behavior('delete')), true)
        assert.equal(edit.clicks, 1)
        assert.equal(remove.clicks, 1)
    })

    it('fires a custom row action on the focused row', () => {
        const target = createElement({ tagName: 'BUTTON' })
        const only = row({ '#approve': target })
        table([only])
        const handle = createTableHandler()
        handle(behavior('row-down'))

        assert.equal(
            handle({
                code: 'KeyA',
                activation: { kind: 'click', selector: '#approve' },
            }),
            true,
        )
        assert.equal(target.clicks, 1)
    })

    it('declines while no row is focused', () => {
        table([
            row({
                'input[type="checkbox"]': createElement({ tagName: 'INPUT' }),
            }),
        ])
        const handle = createTableHandler()

        assert.equal(handle(behavior('select')), false)
    })

    it('declines when the focused row does not carry that action', () => {
        table([row()])
        const handle = createTableHandler()
        handle(behavior('row-down'))

        assert.equal(handle(behavior('edit')), false)
    })
})

describe('table behaviours', () => {
    it('focuses the search field', () => {
        const input = createElement({ tagName: 'INPUT' })
        table([], { [SEARCH_SELECTOR]: input })
        const handle = createTableHandler()

        assert.equal(handle(behavior('search')), true)
        assert.equal(input.focusCalls, 1)
    })

    it('declines when the table has no search field', () => {
        table([])

        assert.equal(createTableHandler()(behavior('search')), false)
    })

    it('pages by rel, which survives translation of the visible label', () => {
        const prev = createElement({ tagName: 'A' })
        const next = createElement({ tagName: 'A' })
        table([], {
            '.fi-pagination [rel="prev"]': prev,
            '.fi-pagination [rel="next"]': next,
        })
        const handle = createTableHandler()

        assert.equal(handle(behavior('page-prev')), true)
        assert.equal(handle(behavior('page-next')), true)
        assert.equal(prev.clicks, 1)
        assert.equal(next.clicks, 1)
    })

    it('declines on the last page, where the link is absent', () => {
        table([])

        assert.equal(createTableHandler()(behavior('page-next')), false)
    })

    it('declines an unknown behaviour and a binding with no activation', () => {
        table([])
        const handle = createTableHandler()

        assert.equal(handle(behavior('teleport')), false)
        assert.equal(handle({ code: 'KeyX', activation: null }), false)
        assert.equal(
            handle({
                code: 'KeyX',
                activation: { kind: 'navigate', url: '/orders' },
            }),
            false,
        )
    })
})
