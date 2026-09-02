import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import { paintHints } from '../../resources/js/hints.js'
import {
    createElement,
    installDocument,
    restoreGlobals,
} from './support/dom.mjs'

const HINT_CLASS = 'fi-hotkey-hint'

function group(handler, code, activation) {
    return { handler, bindings: [{ code, activation }] }
}

function badgesOn(element) {
    return element.children
        .filter((child) => child.className === HINT_CLASS)
        .map((child) => child.textContent)
}

afterEach(restoreGlobals)

describe('paintHints', () => {
    it('badges a header action that fires by navigating', () => {
        // The regression: Create, Edit and View arrive under the global handler as `navigate` whenever
        // the resource registers that page. They fired on the key but rendered no badge, so the only
        // way to discover the key was the cheatsheet.
        const anchor = createElement({ tagName: 'A' })
        installDocument({ selectors: { 'a[href="/orders/create"]': anchor } })

        paintHints([
            group('global', 'KeyC', {
                kind: 'navigate',
                url: '/orders/create',
            }),
        ])

        assert.deepEqual(badgesOn(anchor), ['C'])
    })

    it('badges a header action that fires by clicking', () => {
        const button = createElement({ tagName: 'BUTTON' })
        installDocument({ selectors: { '#bulk': button } })

        paintHints([
            group('global', 'KeyB', { kind: 'click', selector: '#bulk' }),
        ])

        assert.deepEqual(badgesOn(button), ['B'])
    })

    it('badges a navigation link', () => {
        const link = createElement({ tagName: 'A' })
        installDocument({ selectors: { 'a[href="/orders"]': link } })

        paintHints([
            group('navigation', 'KeyO', { kind: 'navigate', url: '/orders' }),
        ])

        assert.deepEqual(badgesOn(link), ['O'])
    })

    it('badges a custom binding of either kind', () => {
        const link = createElement({ tagName: 'A' })
        const button = createElement({ tagName: 'BUTTON' })
        installDocument({
            selectors: { 'a[href="/reports"]': link, '#export': button },
        })

        paintHints([
            group('custom', 'KeyR', { kind: 'navigate', url: '/reports' }),
            group('custom', 'KeyE', { kind: 'click', selector: '#export' }),
        ])

        assert.deepEqual(badgesOn(link), ['R'])
        assert.deepEqual(badgesOn(button), ['E'])
    })

    it('leaves the table handler unbadged, because row actions repeat per row', () => {
        const rowButton = createElement({ tagName: 'BUTTON' })
        installDocument({ selectors: { '#row-edit': rowButton } })

        paintHints([
            group('table', 'KeyE', { kind: 'click', selector: '#row-edit' }),
        ])

        assert.deepEqual(badgesOn(rowButton), [])
    })

    it('ignores a handler that is not on the allowlist', () => {
        // `page` is in the dispatcher's precedence list, so a group can carry it. Badging is opt-in,
        // so an unknown handler paints nothing rather than painting on unreviewed markup.
        const element = createElement()
        installDocument({ selectors: { '#anything': element } })

        paintHints([
            group('page', 'KeyP', { kind: 'click', selector: '#anything' }),
        ])

        assert.deepEqual(badgesOn(element), [])
    })

    it('strips the Key prefix but leaves other codes alone', () => {
        const digit = createElement({ tagName: 'BUTTON' })
        installDocument({ selectors: { '#save': digit } })

        paintHints([
            group('global', 'Digit1', { kind: 'click', selector: '#save' }),
        ])

        assert.deepEqual(badgesOn(digit), ['Digit1'])
    })

    it('skips a binding whose element is not on the page', () => {
        installDocument({ selectors: {} })

        assert.doesNotThrow(() =>
            paintHints([
                group('global', 'KeyC', { kind: 'click', selector: '#gone' }),
            ]),
        )
    })

    it('skips a binding with no activation', () => {
        const element = createElement()
        installDocument({ selectors: { '#anything': element } })

        paintHints([group('global', 'KeyX', null)])

        assert.deepEqual(badgesOn(element), [])
    })

    it('badges an element only once when the same paint runs twice', () => {
        const button = createElement({ tagName: 'BUTTON' })
        installDocument({ selectors: { '#bulk': button } })
        const groups = [
            group('global', 'KeyB', { kind: 'click', selector: '#bulk' }),
        ]

        paintHints(groups)
        paintHints(groups)

        assert.deepEqual(badgesOn(button), ['B'])
    })

    it('removes badges from the previous page before painting', () => {
        // Livewire navigation re-runs this against markup that may already carry stale badges.
        const stale = createElement({ tagName: 'SPAN' })
        stale.className = HINT_CLASS
        installDocument({ selectors: { ['.' + HINT_CLASS]: stale } })

        paintHints([])

        assert.equal(stale.removed, true)
    })
})
