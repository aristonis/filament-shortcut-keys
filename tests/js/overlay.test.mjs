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
    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'

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
})
