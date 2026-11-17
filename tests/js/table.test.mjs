import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import { createTableHandler } from '../../resources/js/table.js'
import {
    createElement,
    installDocument,
    restoreGlobals,
} from './support/dom.mjs'

// Record rows only: Filament also renders a column-search row and group headers as tr.fi-ta-row.
const ROW_SELECTOR = 'tr.fi-ta-row[wire\\:key*=".table.records."]'
const SEARCH_SELECTOR = '.fi-ta-search-field input, input[type="search"]'
const FOCUS_CLASS = 'fi-hotkey-row-focused'
const EDIT_SELECTOR = '[wire\\:key*=".actions.edit."]'
const DELETE_SELECTOR = '[wire\\:key*=".actions.delete."]'

let nextRecord = 0

// A record row as Filament renders it: its wire:key ends in the record key, which survives a re-render
// while the row's position does not.
function row(selectors = {}, key = `r${++nextRecord}`) {
    return createElement({
        tagName: 'TR',
        selectors,
        attributes: { 'wire:key': `page.table.records.${key}` },
        matches: [ROW_SELECTOR],
    })
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

        assert.equal(handle(behavior('cursor-down')), true)
        assert.equal(focusedIndexOf(rows), 0)
    })

    it('starts at the bottom row when moving up from nowhere', () => {
        const rows = [row(), row(), row()]
        table(rows)
        const handle = createTableHandler()

        assert.equal(handle(behavior('cursor-up')), true)
        assert.equal(focusedIndexOf(rows), 2)
    })

    it('stops at the last row instead of wrapping', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('cursor-down'))
        handle(behavior('cursor-down'))
        handle(behavior('cursor-down'))

        assert.equal(focusedIndexOf(rows), 1)
    })

    it('stops at the first row instead of wrapping', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('cursor-down'))
        handle(behavior('cursor-up'))
        handle(behavior('cursor-up'))

        assert.equal(focusedIndexOf(rows), 0)
    })

    it('marks exactly one row and scrolls it into view', () => {
        const rows = [row(), row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('cursor-down'))
        handle(behavior('cursor-down'))

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

        assert.equal(handle(behavior('cursor-down')), false)
    })

    it('drops the cursor on reset, because it belongs to the page that was left', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('cursor-down'))
        handle(behavior('cursor-down'))
        handle.reset()
        handle(behavior('cursor-down'))

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

        handle(behavior('cursor-down'))
        handle(behavior('cursor-down'))

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
        handle(behavior('cursor-down'))

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
        handle(behavior('cursor-down'))

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
        handle(behavior('cursor-down'))

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
        table([row()], {
            '.fi-pagination [rel="prev"]': prev,
            '.fi-pagination [rel="next"]': next,
        })
        const handle = createTableHandler()
        handle(behavior('cursor-down'))

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

describe('the row cursor and real focus', () => {
    it('moves keyboard focus onto the row it marks', () => {
        // Otherwise Enter and Space go to whatever was clicked last, not to the marked row.
        const rows = [row(), row()]
        const document = table(rows)
        const handle = createTableHandler()

        handle(behavior('cursor-down'))
        handle(behavior('cursor-down'))

        assert.equal(document.activeElement, rows[1])
    })

    it('makes the row focusable by script without adding it to the Tab order', () => {
        const rows = [row()]
        table(rows)
        const handle = createTableHandler()

        handle(behavior('cursor-down'))

        assert.equal(rows[0].getAttribute('tabindex'), '-1')
    })
})

describe('arrows wait for the row cursor', () => {
    // Without a live cursor the arrows belong to the page: up/down scroll it, left/right scroll a
    // wide table sideways.
    function paged() {
        const next = createElement({ tagName: 'A' })
        const rows = [row(), row()]
        const document = table(rows, { '.fi-pagination [rel="next"]': next })

        return { next, rows, document, handle: createTableHandler() }
    }

    it('leaves up and down to the page until a row is marked', () => {
        const { rows, handle } = paged()

        assert.equal(handle(behavior('row-down')), false)
        assert.equal(handle(behavior('row-up')), false)
        assert.equal(focusedIndexOf(rows), -1)
    })

    it('leaves left and right to the page until a row is marked', () => {
        const { next, handle } = paged()

        assert.equal(handle(behavior('page-next')), false)
        assert.equal(next.clicks, 0)
    })

    it('moves with the arrows once j or k has started the cursor', () => {
        const { rows, handle } = paged()

        handle(behavior('cursor-down'))

        assert.equal(handle(behavior('row-down')), true)
        assert.equal(focusedIndexOf(rows), 1)
    })

    it('keeps the arrows while focus fell back to the page, as after a re-render', () => {
        const { rows, document, handle } = paged()
        handle(behavior('cursor-down'))

        document.activeElement = createElement({ tagName: 'BODY' })

        assert.equal(handle(behavior('row-down')), true)
        assert.equal(focusedIndexOf(rows), 1)
    })

    it('gives the arrows back once the user focused something else', () => {
        const { next, document, handle } = paged()
        handle(behavior('cursor-down'))

        document.activeElement = createElement({ tagName: 'A' })

        assert.equal(handle(behavior('row-down')), false)
        assert.equal(handle(behavior('page-next')), false)
        assert.equal(next.clicks, 0)
    })

    it('releases the cursor on Escape and hands the arrows back', () => {
        const { rows, handle } = paged()
        handle(behavior('cursor-down'))

        assert.equal(handle(behavior('cursor-release')), true)
        assert.equal(focusedIndexOf(rows), -1)
        assert.equal(handle(behavior('row-down')), false)
    })

    it('leaves Escape to the page when no row is marked', () => {
        const { handle } = paged()

        assert.equal(handle(behavior('cursor-release')), false)
    })
})

describe('the row cursor follows the record, not the position', () => {
    const EDIT = '[wire\\:key*=".actions.edit."]'

    function editableRow(key) {
        return row({ [EDIT]: createElement({ tagName: 'A' }) }, key)
    }

    it('acts on the marked record after a sort moved it', () => {
        const first = editableRow('a')
        const document = table([first, editableRow('b')])
        const handle = createTableHandler()
        handle(behavior('cursor-down'))

        // Livewire re-rendered the table sorted the other way: new elements, same records.
        const movedA = editableRow('a')
        const movedB = editableRow('b')
        document.selectors[ROW_SELECTOR] = [movedB, movedA]
        document.activeElement = createElement({ tagName: 'BODY' })

        assert.equal(handle(behavior('edit')), true)
        assert.equal(movedA.querySelector(EDIT).clicks, 1)
        assert.equal(movedB.querySelector(EDIT).clicks, 0)
        assert.equal(movedA.classList.contains(FOCUS_CLASS), true)
    })

    it('drops the cursor once its record left the table', () => {
        const document = table([editableRow('a'), editableRow('b')])
        const handle = createTableHandler()
        handle(behavior('cursor-down'))

        const other = editableRow('c')
        document.selectors[ROW_SELECTOR] = [other]
        document.activeElement = createElement({ tagName: 'BODY' })

        assert.equal(handle(behavior('edit')), false)
        assert.equal(other.querySelector(EDIT).clicks, 0)
        assert.equal(handle(behavior('row-down')), false)
    })
})

describe('giving the keys back to the page', () => {
    it('releases the cursor when the user clicks outside the rows', () => {
        // A click on blank page space moves focus to the body, which alone looks like a re-render.
        const rows = [row(), row()]
        const document = table(rows)
        const handle = createTableHandler()
        handle(behavior('cursor-down'))

        handle.onPointerDown({ target: createElement() })
        document.activeElement = createElement({ tagName: 'BODY' })

        assert.equal(focusedIndexOf(rows), -1)
        assert.equal(handle(behavior('row-down')), false)
    })

    it('keeps the cursor when the click lands inside a row', () => {
        const rows = [row(), row()]
        table(rows)
        const handle = createTableHandler()
        handle(behavior('cursor-down'))

        const cell = rows[0].appendChild(createElement({ tagName: 'TD' }))
        handle.onPointerDown({ target: cell })

        assert.equal(focusedIndexOf(rows), 0)
    })

    it('leaves Escape to the page once focus moved elsewhere, and drops the stale cursor', () => {
        const rows = [row(), row()]
        const document = table(rows)
        const handle = createTableHandler()
        handle(behavior('cursor-down'))

        document.activeElement = createElement({ tagName: 'A' })

        assert.equal(handle(behavior('cursor-release')), false)
        assert.equal(focusedIndexOf(rows), -1)
    })
})
