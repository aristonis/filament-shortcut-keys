import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import {
    createElement,
    createKeyEvent,
    installDocument,
    installWindow,
    restoreGlobals,
} from './support/dom.mjs'

const KEYMAP = [
    {
        set: 'navigation',
        handler: 'navigation',
        modifier: 'alt+shift',
        bindings: [
            {
                target: 'orders',
                code: 'KeyO',
                activation: { kind: 'navigate', url: '/admin/orders' },
            },
        ],
    },
]

afterEach(restoreGlobals)

// Each distinct URL makes node evaluate the entry module again, the same as a page that ends up
// with the bundle loaded twice.
let evaluation = 0
const loadEntry = () =>
    import(`../../resources/js/index.js?load=${++evaluation}`)

describe('index entry', () => {
    it('acts once per key press even when the bundle is evaluated twice', async () => {
        const document = installDocument({
            elementsById: {
                'filament-shortcut-keys-map': createElement({
                    textContent: JSON.stringify(KEYMAP),
                }),
            },
        })
        const window = installWindow()

        await loadEntry()
        await loadEntry()

        document.dispatch(
            'keydown',
            createKeyEvent({
                code: 'KeyO',
                altKey: true,
                shiftKey: true,
                target: createElement({ tagName: 'BODY' }),
            }),
        )

        assert.deepEqual(window.location.assigned, ['/admin/orders'])
    })

    it('hands the keyboard to the newest evaluation of the bundle', async () => {
        // An upgraded bundle loaded mid-session must run its own code, so each evaluation retires
        // the one before it through the shared window handle rather than standing down.
        const document = installDocument({
            elementsById: {
                'filament-shortcut-keys-map': createElement({
                    textContent: JSON.stringify(KEYMAP),
                }),
            },
        })
        const window = installWindow()

        await loadEntry()
        const first = window.__filamentShortcutKeys
        const listeners = document.listenerCount('keydown')
        await loadEntry()
        const second = window.__filamentShortcutKeys

        assert.equal(first.signal.aborted, true)
        assert.equal(second.signal.aborted, false)
        assert.equal(document.listenerCount('keydown'), listeners)
        assert.equal(document.listenerCount('livewire:navigated'), 1)
    })
})
