import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import {
    eventModifier,
    isReservedOverlayKey,
    isTyping,
    readKeymap,
} from '../../resources/js/keymap.js'
import {
    createElement,
    createKeyEvent,
    installDocument,
    restoreGlobals,
} from './support/dom.mjs'

const KEYMAP_ELEMENT_ID = 'filament-shortcut-keys-map'

afterEach(restoreGlobals)

describe('readKeymap', () => {
    it('parses the map the server injected', () => {
        const script = createElement({
            tagName: 'SCRIPT',
            textContent: '[{"handler":"navigation"}]',
        })
        installDocument({ elementsById: { [KEYMAP_ELEMENT_ID]: script } })

        assert.deepEqual(readKeymap(), [{ handler: 'navigation' }])
    })

    it('returns an empty map when the element is absent', () => {
        installDocument()

        assert.deepEqual(readKeymap(), [])
    })

    it('returns an empty map rather than throwing on malformed json', () => {
        // A truncated or escaped payload must degrade to "no shortcuts", never break the panel.
        const script = createElement({
            tagName: 'SCRIPT',
            textContent: '[{"handler":',
        })
        installDocument({ elementsById: { [KEYMAP_ELEMENT_ID]: script } })

        assert.deepEqual(readKeymap(), [])
    })
})

describe('eventModifier', () => {
    it('serialises in the order the PHP ModifierScheme uses', () => {
        // ctrl, alt, shift, meta. Any other order and "alt+shift" never matches a real press.
        const event = createKeyEvent({
            ctrlKey: true,
            altKey: true,
            shiftKey: true,
            metaKey: true,
        })

        assert.equal(eventModifier(event), 'ctrl+alt+shift+meta')
    })

    it('reads a bare key as an empty modifier', () => {
        assert.equal(eventModifier(createKeyEvent()), '')
    })

    it('joins only the modifiers actually held', () => {
        assert.equal(
            eventModifier(createKeyEvent({ altKey: true, shiftKey: true })),
            'alt+shift',
        )
    })
})

describe('isTyping', () => {
    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) {
        it(`is true inside a ${tagName}`, () => {
            assert.equal(isTyping(createElement({ tagName })), true)
        })
    }

    it('is true inside a contenteditable region', () => {
        assert.equal(
            isTyping(
                createElement({ tagName: 'DIV', isContentEditable: true }),
            ),
            true,
        )
    })

    it('is false on an ordinary element', () => {
        assert.equal(isTyping(createElement({ tagName: 'DIV' })), false)
    })

    it('is false when the event carries no target', () => {
        assert.equal(isTyping(null), false)
    })
})

describe('isReservedOverlayKey', () => {
    it('claims Shift+Slash for the cheatsheet', () => {
        assert.equal(
            isReservedOverlayKey(
                createKeyEvent({ code: 'Slash', shiftKey: true }),
            ),
            true,
        )
    })

    it('leaves a bare Slash alone, because it is the table search key', () => {
        assert.equal(
            isReservedOverlayKey(createKeyEvent({ code: 'Slash' })),
            false,
        )
    })

    it('leaves Shift+Slash alone once another modifier joins it', () => {
        for (const extra of ['altKey', 'ctrlKey', 'metaKey']) {
            const event = createKeyEvent({
                code: 'Slash',
                shiftKey: true,
                [extra]: true,
            })

            assert.equal(isReservedOverlayKey(event), false, extra)
        }
    })
})
