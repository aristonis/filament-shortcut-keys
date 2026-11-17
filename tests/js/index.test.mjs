import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import {
    createElement,
    createKeyEvent,
    installDocument,
    installWindow,
    restoreGlobals,
} from './support/dom.mjs'
import {
    isAwaitingResponse,
    noteClick,
    settle,
} from '../../resources/js/inflight.js'

const KEYMAP = [
    {
        set: 'navigation',
        handler: 'navigation',
        modifier: '',
        leader: 'KeyG',
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

        for (const code of ['KeyG', 'KeyO']) {
            document.dispatch(
                'keydown',
                createKeyEvent({
                    code,
                    target: createElement({ tagName: 'BODY' }),
                }),
            )
        }

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
        assert.equal(document.listenerCount('pointerdown'), 1)
    })

    it('repaints badges and announcements after Livewire re-renders the page in place', async () => {
        // A morph replaces the markup with the server's, which carries none of the badges or
        // aria-keyshortcuts the script added, and no navigation event follows to put them back.
        const exportButton = createElement({ tagName: 'BUTTON' })
        const keymap = createElement({
            textContent: JSON.stringify([
                {
                    set: 'global',
                    handler: 'global',
                    modifier: '',
                    leader: null,
                    bindings: [
                        {
                            target: 'global:export',
                            code: 'KeyE',
                            activation: { kind: 'click', selector: '#export' },
                        },
                    ],
                },
            ]),
        })
        installDocument({
            elementsById: { 'filament-shortcut-keys-map': keymap },
            selectors: { '#export': exportButton },
        })
        const hooks = {}
        installWindow({
            Livewire: {
                hook: (name, callback) => {
                    ;(hooks[name] ??= []).push(callback)
                },
            },
        })

        await loadEntry()
        exportButton.removeAttribute('aria-keyshortcuts')
        exportButton.removeAttribute('data-fsk-keyshortcuts')
        exportButton.children = []

        for (const callback of hooks.morphed ?? []) {
            callback({})
        }
        await Promise.resolve()

        assert.equal(exportButton.getAttribute('aria-keyshortcuts'), 'E')
        assert.equal(exportButton.children.length, 1)
    })

    describe('waiting for a response', () => {
        afterEach(() => settle())

        const livewireButton = () =>
            createElement({
                tagName: 'BUTTON',
                attributes: { 'wire:click': "mountAction('send')" },
            })

        function livewireStub() {
            const hooks = {}
            return {
                hooks,
                hook: (name, callback) => {
                    ;(hooks[name] ??= []).push(callback)
                },
            }
        }

        // Drives Livewire's commit hook the way a finished request does.
        function finishRequest(livewire, outcome) {
            for (const callback of livewire.hooks.commit ?? []) {
                const handlers = {}
                callback({
                    succeed: (fn) => (handlers.succeed = fn),
                    fail: (fn) => (handlers.fail = fn),
                    respond: () => {},
                })
                handlers[outcome]?.({})
            }
        }

        for (const outcome of ['succeed', 'fail']) {
            it(`ends the wait when the request ends (${outcome}), even if nothing re-renders`, async () => {
                installDocument()
                const livewire = livewireStub()
                installWindow({ Livewire: livewire })
                await loadEntry()

                noteClick(livewireButton())
                finishRequest(livewire, outcome)

                assert.equal(isAwaitingResponse(), false)
            })
        }

        it('registers once Livewire starts, when the script loaded first', async () => {
            const document = installDocument()
            const window = installWindow()
            await loadEntry()

            const livewire = livewireStub()
            window.Livewire = livewire
            document.dispatch('livewire:init', {})

            noteClick(livewireButton())
            finishRequest(livewire, 'succeed')

            assert.equal(isAwaitingResponse(), false)
        })
    })
})
