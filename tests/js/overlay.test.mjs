import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import { initOverlay } from '../../resources/js/overlay.js'
import {
    createElement,
    createKeyEvent,
    installDocument,
    restoreGlobals,
} from './support/dom.mjs'

const OVERLAY_ID = 'filament-shortcut-keys-overlay'
const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
const SWITCH = '[data-fsk-single-keys]'

function cheatsheet({ open = false, focusables = [] } = {}) {
    return createElement({
        tagName: 'DIV',
        attributes: open ? {} : { hidden: '' },
        selectors: { [FOCUSABLE]: focusables },
    })
}

function panel(overlay, activeElement = null) {
    const document = installDocument({
        elementsById: overlay ? { [OVERLAY_ID]: overlay } : {},
        activeElement,
    })
    initOverlay()

    return document
}

function toggleKey(overrides = {}) {
    return createKeyEvent({
        code: 'Slash',
        key: '?',
        shiftKey: true,
        ...overrides,
    })
}

const isOpen = (overlay) => !overlay.hasAttribute('hidden')

afterEach(restoreGlobals)

describe('the cheatsheet overlay', () => {
    it('opens on "?" and moves focus inside', () => {
        const first = createElement({ tagName: 'BUTTON' })
        const overlay = cheatsheet({ focusables: [first] })
        const document = panel(overlay)

        const event = document.dispatch('keydown', toggleKey())

        assert.equal(isOpen(overlay), true)
        assert.equal(event.defaultPrevented, true)
        assert.equal(document.activeElement, first)
    })

    it('closes on a second "?" and hands focus back where it was', () => {
        const opener = createElement({ tagName: 'BUTTON' })
        const overlay = cheatsheet({
            focusables: [createElement({ tagName: 'BUTTON' })],
        })
        const document = panel(overlay, opener)

        document.dispatch('keydown', toggleKey())
        document.dispatch('keydown', toggleKey())

        assert.equal(isOpen(overlay), false)
        assert.equal(document.activeElement, opener)
    })

    it('lets the user type "?" into a field instead of opening', () => {
        const overlay = cheatsheet()
        const document = panel(overlay)

        const event = document.dispatch(
            'keydown',
            toggleKey({ target: createElement({ tagName: 'INPUT' }) }),
        )

        assert.equal(isOpen(overlay), false)
        assert.equal(event.defaultPrevented, false)
    })

    it('closes on Escape', () => {
        const overlay = cheatsheet({
            open: true,
            focusables: [createElement({ tagName: 'BUTTON' })],
        })
        const document = panel(overlay)

        const event = document.dispatch(
            'keydown',
            createKeyEvent({ key: 'Escape' }),
        )

        assert.equal(isOpen(overlay), false)
        assert.equal(event.defaultPrevented, true)
    })

    it('ignores Escape while closed', () => {
        const overlay = cheatsheet()
        const document = panel(overlay)

        const event = document.dispatch(
            'keydown',
            createKeyEvent({ key: 'Escape' }),
        )

        assert.equal(event.defaultPrevented, false)
    })

    it('wraps Tab from the last focusable back to the first', () => {
        const first = createElement({ tagName: 'BUTTON' })
        const last = createElement({ tagName: 'BUTTON' })
        const overlay = cheatsheet({ open: true, focusables: [first, last] })
        const document = panel(overlay, last)

        const event = document.dispatch(
            'keydown',
            createKeyEvent({ key: 'Tab' }),
        )

        assert.equal(document.activeElement, first)
        assert.equal(event.defaultPrevented, true)
    })

    it('wraps Shift+Tab from the first focusable back to the last', () => {
        const first = createElement({ tagName: 'BUTTON' })
        const last = createElement({ tagName: 'BUTTON' })
        const overlay = cheatsheet({ open: true, focusables: [first, last] })
        const document = panel(overlay, first)

        const event = document.dispatch(
            'keydown',
            createKeyEvent({ key: 'Tab', shiftKey: true }),
        )

        assert.equal(document.activeElement, last)
        assert.equal(event.defaultPrevented, true)
    })

    it('leaves Tab alone in the middle of the overlay', () => {
        const first = createElement({ tagName: 'BUTTON' })
        const middle = createElement({ tagName: 'BUTTON' })
        const last = createElement({ tagName: 'BUTTON' })
        const overlay = cheatsheet({
            open: true,
            focusables: [first, middle, last],
        })
        const document = panel(overlay, middle)

        const event = document.dispatch(
            'keydown',
            createKeyEvent({ key: 'Tab' }),
        )

        assert.equal(document.activeElement, middle)
        assert.equal(event.defaultPrevented, false)
    })

    it('closes on a backdrop click', () => {
        const overlay = cheatsheet({ open: true })
        const document = panel(overlay)

        document.dispatch('click', { target: overlay })

        assert.equal(isOpen(overlay), false)
    })

    it('closes on a click inside the close control', () => {
        const overlay = cheatsheet({ open: true })
        const closeButton = createElement({
            tagName: 'BUTTON',
            matches: ['[data-fsk-overlay-close]'],
        })
        const icon = createElement({ tagName: 'SPAN' })
        closeButton.appendChild(icon)
        const document = panel(overlay)

        document.dispatch('click', { target: icon })

        assert.equal(isOpen(overlay), false)
    })

    it('stays open on a click elsewhere inside it', () => {
        const overlay = cheatsheet({ open: true })
        const document = panel(overlay)

        document.dispatch('click', { target: createElement({ tagName: 'P' }) })

        assert.equal(isOpen(overlay), true)
    })

    it('does nothing on a page the server rendered no overlay into', () => {
        const document = panel(null)

        assert.doesNotThrow(() => document.dispatch('keydown', toggleKey()))
        assert.doesNotThrow(() =>
            document.dispatch('click', { target: createElement() }),
        )
    })

    describe('the single-key switch', () => {
        afterEach(() => {
            delete globalThis.localStorage
        })

        function withSwitch() {
            const toggle = createElement({
                tagName: 'INPUT',
                attributes: { type: 'checkbox' },
            })
            const overlay = createElement({
                tagName: 'DIV',
                attributes: { hidden: '' },
                selectors: { [FOCUSABLE]: [toggle], [SWITCH]: toggle },
            })
            toggle.matches = [SWITCH]
            const items = {}
            globalThis.localStorage = {
                getItem: (key) => items[key] ?? null,
                setItem: (key, value) => {
                    items[key] = value
                },
                removeItem: (key) => {
                    delete items[key]
                },
            }
            const changes = []
            const document = installDocument({
                elementsById: { [OVERLAY_ID]: overlay },
            })
            initOverlay(undefined, {
                onSingleKeysChange: (on) => changes.push(on),
            })

            return { toggle, document, changes }
        }

        it('shows the saved setting when the cheatsheet opens', () => {
            const { toggle, document } = withSwitch()
            globalThis.localStorage.setItem(
                'filament-shortcut-keys:single-keys',
                'off',
            )

            document.dispatch('keydown', toggleKey())

            assert.equal(toggle.checked, false)
        })

        it('saves the choice and reports it when the switch is flipped', () => {
            const { toggle, document, changes } = withSwitch()

            toggle.checked = false
            document.dispatch('change', { target: toggle })

            assert.deepEqual(changes, [false])
            assert.equal(
                globalThis.localStorage.getItem(
                    'filament-shortcut-keys:single-keys',
                ),
                'off',
            )
        })
    })
})

describe('rows for keys the server could not check', () => {
    // A row action on a lazy relation manager or table widget is listed with the control it needs;
    // the cheatsheet shows it only when the page actually has that control.
    const APPROVE = '[wire\\:key*=".actions.approve."]'
    const REJECT = '[wire\\:key*=".actions.reject."]'

    function presenceRow(selector) {
        return createElement({ attributes: { 'data-fsk-presence': selector } })
    }

    function sheetWith(groups) {
        const rows = groups.flat()
        const sections = groups.map((groupRows) =>
            createElement({ selectors: { '.fsk-row': groupRows } }),
        )

        return createElement({
            tagName: 'DIV',
            attributes: { hidden: '' },
            selectors: {
                '[data-fsk-presence]': rows.filter((r) =>
                    r.hasAttribute('data-fsk-presence'),
                ),
                '.fsk-group': sections,
            },
        })
    }

    function pageWith(overlay, present) {
        const document = installDocument({
            elementsById: { [OVERLAY_ID]: overlay },
            selectors: Object.fromEntries(
                present.map((selector) => [selector, createElement()]),
            ),
        })
        initOverlay()

        return document
    }

    it('shows a row whose control is on the page and hides one whose is not', () => {
        const approve = presenceRow(APPROVE)
        const reject = presenceRow(REJECT)
        const document = pageWith(sheetWith([[approve, reject]]), [APPROVE])

        document.dispatch('keydown', toggleKey())

        assert.equal(approve.hasAttribute('hidden'), false)
        assert.equal(reject.hasAttribute('hidden'), true)
    })

    it('hides a group heading when every row under it is hidden', () => {
        const reject = presenceRow(REJECT)
        const fixed = createElement()
        const overlay = sheetWith([[reject], [fixed]])
        const [rowActions, table] = overlay.querySelectorAll('.fsk-group')
        const document = pageWith(overlay, [])

        document.dispatch('keydown', toggleKey())

        assert.equal(rowActions.hasAttribute('hidden'), true)
        assert.equal(table.hasAttribute('hidden'), false)
    })

    it('still opens, keeping the row, when a selector is not valid CSS', () => {
        // A row-action name is concatenated into the selector, so a stray quote in a registered
        // name must not take the whole cheatsheet down with it.
        const broken = presenceRow('[wire\\:key*=".actions.a"b."]')
        const overlay = sheetWith([[broken]])
        const document = pageWith(overlay, [])
        document.querySelector = () => {
            throw new SyntaxError('not a valid selector')
        }

        document.dispatch('keydown', toggleKey())

        assert.equal(isOpen(overlay), true)
        assert.equal(broken.hasAttribute('hidden'), false)
    })

    it('checks again on every open, after a lazy table has loaded', () => {
        const approve = presenceRow(APPROVE)
        const document = pageWith(sheetWith([[approve]]), [])

        document.dispatch('keydown', toggleKey())
        document.dispatch('keydown', toggleKey())
        document.selectors[APPROVE] = createElement()
        document.dispatch('keydown', toggleKey())

        assert.equal(approve.hasAttribute('hidden'), false)
    })
})
