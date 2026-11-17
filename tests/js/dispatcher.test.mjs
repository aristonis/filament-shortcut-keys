import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import { createDispatcher } from '../../resources/js/dispatcher.js'
import {
    createElement,
    createKeyEvent,
    installDocument,
    restoreGlobals,
} from './support/dom.mjs'

function group(handler, { modifier = '', code = 'KeyE' } = {}) {
    return {
        handler,
        modifier,
        bindings: [
            { code, activation: { kind: 'click', selector: '#' + handler } },
        ],
    }
}

// A handler that records what it was asked to fire and reports whether it managed to.
function recorder(fired = true) {
    const calls = []
    const handler = (binding, event) => {
        calls.push({ binding, event })

        return fired
    }
    handler.calls = calls

    return handler
}

afterEach(restoreGlobals)

describe('createDispatcher', () => {
    it('fires the binding whose code and modifier both match', () => {
        const global = recorder()
        const dispatch = createDispatcher(
            () => [group('global', { modifier: 'alt+shift' })],
            { global },
        )

        const event = createKeyEvent({
            code: 'KeyE',
            altKey: true,
            shiftKey: true,
        })
        dispatch(event)

        assert.equal(global.calls.length, 1)
        assert.equal(event.defaultPrevented, true)
    })

    it('ignores a group bound to a different modifier', () => {
        const global = recorder()
        const dispatch = createDispatcher(
            () => [group('global', { modifier: 'alt+shift' })],
            { global },
        )

        const event = createKeyEvent({ code: 'KeyE', altKey: true })
        dispatch(event)

        assert.equal(global.calls.length, 0)
        assert.equal(event.defaultPrevented, false)
    })

    it('prefers the more specific context when two handlers bind the same key', () => {
        // page beats custom beats table beats global beats navigation.
        const custom = recorder()
        const navigation = recorder()
        const dispatch = createDispatcher(
            () => [group('navigation'), group('custom')],
            { custom, navigation },
        )

        dispatch(createKeyEvent({ code: 'KeyE' }))

        assert.equal(custom.calls.length, 1)
        assert.equal(navigation.calls.length, 0)
    })

    it('falls through to the next candidate when a handler cannot act', () => {
        // A handler returning false means "the element was not there", not "the key is consumed".
        const custom = recorder(false)
        const navigation = recorder(true)
        const dispatch = createDispatcher(
            () => [group('navigation'), group('custom')],
            { custom, navigation },
        )

        const event = createKeyEvent({ code: 'KeyE' })
        dispatch(event)

        assert.equal(custom.calls.length, 1)
        assert.equal(navigation.calls.length, 1)
        assert.equal(event.defaultPrevented, true)
    })

    it('leaves the key to the browser when nothing fires', () => {
        const global = recorder(false)
        const dispatch = createDispatcher(() => [group('global')], { global })

        const event = createKeyEvent({ code: 'KeyE' })
        dispatch(event)

        assert.equal(event.defaultPrevented, false)
    })

    it('holds bare-key groups back while the user is typing', () => {
        const global = recorder()
        const dispatch = createDispatcher(() => [group('global')], { global })

        dispatch(
            createKeyEvent({
                code: 'KeyE',
                target: createElement({ tagName: 'INPUT' }),
            }),
        )

        assert.equal(global.calls.length, 0)
    })

    it('still fires modifier groups while the user is typing', () => {
        const global = recorder()
        const dispatch = createDispatcher(
            () => [group('global', { modifier: 'alt+shift' })],
            { global },
        )

        const event = createKeyEvent({
            code: 'KeyE',
            altKey: true,
            shiftKey: true,
            target: createElement({ tagName: 'INPUT' }),
        })
        dispatch(event)

        assert.equal(global.calls.length, 1)
    })

    it('never treats the cheatsheet key as a shortcut', () => {
        const global = recorder()
        const dispatch = createDispatcher(
            () => [group('global', { code: 'Slash', modifier: 'shift' })],
            { global },
        )

        const event = createKeyEvent({ code: 'Slash', shiftKey: true })
        dispatch(event)

        assert.equal(global.calls.length, 0)
        assert.equal(event.defaultPrevented, false)
    })

    it('skips a group whose handler is not registered', () => {
        const dispatch = createDispatcher(
            () => [group('page'), group('global')],
            { global: recorder() },
        )

        assert.doesNotThrow(() => dispatch(createKeyEvent({ code: 'KeyE' })))
    })

    it('re-reads the groups on every keypress, because Livewire swaps the page under it', () => {
        const global = recorder()
        let groups = []
        const dispatch = createDispatcher(() => groups, { global })

        dispatch(createKeyEvent({ code: 'KeyE' }))
        groups = [group('global')]
        dispatch(createKeyEvent({ code: 'KeyE' }))

        assert.equal(global.calls.length, 1)
    })

    describe('when focus sits in something that owns the keyboard', () => {
        // A focused element nested inside a container matching `selector`, the way focus lands in a
        // Filament modal, select or dropdown.
        function focusedInside(selector, tagName = 'BUTTON') {
            const container = createElement({ matches: [selector] })

            return container.appendChild(createElement({ tagName }))
        }

        const OWNERS = [
            '[role="dialog"]',
            '[role="alertdialog"]',
            '[aria-modal="true"]',
            '[role="listbox"]',
            '[role="combobox"]',
            '[role="menu"]',
            '[role="menubar"]',
            '[role="tablist"]',
            '.fi-dropdown-panel',
        ]

        for (const selector of OWNERS) {
            it(`holds bare keys back inside ${selector}`, () => {
                const table = recorder()
                const dispatch = createDispatcher(
                    () => [group('table', { code: 'ArrowDown' })],
                    { table },
                )

                const event = createKeyEvent({
                    code: 'ArrowDown',
                    target: focusedInside(selector),
                })
                dispatch(event)

                assert.equal(table.calls.length, 0)
                assert.equal(event.defaultPrevented, false)
            })
        }

        it('lets Enter confirm a focused modal button instead of editing the row behind it', () => {
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Enter' })],
                { table },
            )

            const event = createKeyEvent({
                code: 'Enter',
                target: focusedInside('[role="alertdialog"]'),
            })
            dispatch(event)

            assert.equal(table.calls.length, 0)
            assert.equal(event.defaultPrevented, false)
        })

        it('still fires modifier groups inside a dialog', () => {
            const navigation = recorder()
            const dispatch = createDispatcher(
                () => [group('navigation', { modifier: 'alt+shift' })],
                { navigation },
            )

            dispatch(
                createKeyEvent({
                    code: 'KeyE',
                    altKey: true,
                    shiftKey: true,
                    target: focusedInside('[role="dialog"]'),
                }),
            )

            assert.equal(navigation.calls.length, 1)
        })
    })

    describe('activation keys on a focused control', () => {
        for (const [code, tagName] of [
            ['Enter', 'BUTTON'],
            ['Enter', 'A'],
            ['NumpadEnter', 'BUTTON'],
            ['Space', 'BUTTON'],
            ['Space', 'A'],
        ]) {
            it(`leaves ${code} to a focused ${tagName}`, () => {
                const table = recorder()
                const dispatch = createDispatcher(
                    () => [group('table', { code })],
                    { table },
                )

                const event = createKeyEvent({
                    code,
                    target: createElement({ tagName }),
                })
                dispatch(event)

                assert.equal(table.calls.length, 0)
                assert.equal(event.defaultPrevented, false)
            })
        }

        it('leaves Enter to an element acting as a button', () => {
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Enter' })],
                { table },
            )

            dispatch(
                createKeyEvent({
                    code: 'Enter',
                    target: createElement({ attributes: { role: 'button' } }),
                }),
            )

            assert.equal(table.calls.length, 0)
        })

        it('still fires Enter when focus is on the page itself', () => {
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Enter' })],
                { table },
            )

            dispatch(
                createKeyEvent({
                    code: 'Enter',
                    target: createElement({ tagName: 'BODY' }),
                }),
            )

            assert.equal(table.calls.length, 1)
        })

        it('still fires a row-action letter while a button has focus', () => {
            // Only Enter and Space mean "activate this control"; a letter has no native meaning on it.
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'KeyA' })],
                { table },
            )

            dispatch(
                createKeyEvent({
                    code: 'KeyA',
                    target: createElement({ tagName: 'BUTTON' }),
                }),
            )

            assert.equal(table.calls.length, 1)
        })
    })

    describe('one press, one action', () => {
        function rowDown() {
            return {
                handler: 'table',
                modifier: '',
                bindings: [
                    {
                        code: 'ArrowDown',
                        activation: { kind: 'table', behavior: 'row-down' },
                    },
                ],
            }
        }

        it('swallows the auto-repeat of a held key it already acted on', () => {
            // Holding Delete must not open a confirm per repeat, and holding Space after selecting a
            // row must not start scrolling the page.
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Delete' })],
                { table },
            )

            dispatch(createKeyEvent({ code: 'Delete' }))
            const repeat = createKeyEvent({ code: 'Delete', repeat: true })
            dispatch(repeat)

            assert.equal(table.calls.length, 1)
            assert.equal(repeat.defaultPrevented, true)
        })

        it('leaves the repeat of a key it did not act on to the browser', () => {
            const table = recorder(false)
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Space' })],
                { table },
            )

            dispatch(createKeyEvent({ code: 'Space' }))
            const repeat = createKeyEvent({ code: 'Space', repeat: true })
            dispatch(repeat)

            assert.equal(table.calls.length, 1)
            assert.equal(repeat.defaultPrevented, false)
        })

        it('lets a held arrow keep moving the row cursor', () => {
            const table = recorder()
            const dispatch = createDispatcher(() => [rowDown()], { table })

            dispatch(createKeyEvent({ code: 'ArrowDown' }))
            dispatch(createKeyEvent({ code: 'ArrowDown', repeat: true }))

            assert.equal(table.calls.length, 2)
        })

        it('lets a held j keep moving the row cursor', () => {
            const table = recorder()
            const nextRow = {
                handler: 'table',
                modifier: '',
                bindings: [
                    {
                        code: 'KeyJ',
                        activation: { kind: 'table', behavior: 'cursor-down' },
                    },
                ],
            }
            const dispatch = createDispatcher(() => [nextRow], { table })

            dispatch(createKeyEvent({ code: 'KeyJ' }))
            dispatch(createKeyEvent({ code: 'KeyJ', repeat: true }))

            assert.equal(table.calls.length, 2)
        })

        it('keeps repeating the binding that fired, past one that could not act', () => {
            // A page binding on the same key with nothing to click falls through to the table on the
            // first press; the repeat must not stop at the page binding.
            const page = recorder(false)
            const table = recorder()
            const pageArrow = group('page', { code: 'ArrowDown' })
            const dispatch = createDispatcher(() => [pageArrow, rowDown()], {
                page,
                table,
            })

            dispatch(createKeyEvent({ code: 'ArrowDown' }))
            dispatch(createKeyEvent({ code: 'ArrowDown', repeat: true }))

            assert.equal(table.calls.length, 2)
        })

        for (const [label, overrides] of [
            ['isComposing', { isComposing: true }],
            // Safari ends composition before the last keydown, which then only carries keyCode 229.
            ['keyCode 229', { keyCode: 229 }],
        ]) {
            it(`ignores keys while an input method is composing text (${label})`, () => {
                const global = recorder()
                const dispatch = createDispatcher(
                    () => [group('global', { modifier: 'alt+shift' })],
                    { global },
                )

                const event = createKeyEvent({
                    code: 'KeyE',
                    altKey: true,
                    shiftKey: true,
                    ...overrides,
                })
                dispatch(event)

                assert.equal(global.calls.length, 0)
                assert.equal(event.defaultPrevented, false)
            })
        }
    })

    describe('while a modal is open but focus stayed behind it', () => {
        for (const selector of [
            '.fi-modal-open[aria-modal="true"]',
            '#filament-shortcut-keys-overlay:not([hidden])',
        ]) {
            it(`holds bare keys back when ${selector} is on the page`, () => {
                installDocument({ selectors: { [selector]: createElement() } })
                const table = recorder()
                const dispatch = createDispatcher(
                    () => [group('table', { code: 'Delete' })],
                    { table },
                )

                dispatch(
                    createKeyEvent({
                        code: 'Delete',
                        target: createElement({ tagName: 'BODY' }),
                    }),
                )

                assert.equal(table.calls.length, 0)
            })
        }
    })

    describe('non-text inputs', () => {
        function checkbox() {
            return createElement({
                tagName: 'INPUT',
                attributes: { type: 'checkbox' },
            })
        }

        it('still moves the row cursor after a row checkbox was clicked', () => {
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'ArrowDown' })],
                { table },
            )

            dispatch(createKeyEvent({ code: 'ArrowDown', target: checkbox() }))

            assert.equal(table.calls.length, 1)
        })

        it('leaves Space to a focused checkbox', () => {
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Space' })],
                { table },
            )

            dispatch(createKeyEvent({ code: 'Space', target: checkbox() }))

            assert.equal(table.calls.length, 0)
        })
    })

    describe('the g leader key', () => {
        function leaderGroup(code = 'KeyO') {
            return {
                handler: 'navigation',
                modifier: '',
                leader: 'KeyG',
                bindings: [
                    { code, activation: { kind: 'navigate', url: '/orders' } },
                ],
            }
        }

        // A timer the test advances by hand, so the leader's timeout needs no real waiting.
        function manualTimer() {
            const timer = { pending: null }
            timer.set = (callback) => {
                timer.pending = callback
                return 1
            }
            timer.clear = () => {
                timer.pending = null
            }
            timer.fire = () => {
                const callback = timer.pending
                timer.pending = null
                callback?.()
            }
            return timer
        }

        function setup(extraGroups = []) {
            const navigation = recorder()
            const timer = manualTimer()
            const leaders = []
            const dispatch = createDispatcher(
                () => [leaderGroup(), ...extraGroups],
                { navigation, table: recorder() },
                {
                    setTimer: timer.set,
                    clearTimer: timer.clear,
                    onLeader: (code) => leaders.push(code),
                },
            )
            const body = () => createElement({ tagName: 'BODY' })
            const press = (code, extra = {}) => {
                const event = createKeyEvent({ code, target: body(), ...extra })
                dispatch(event)
                return event
            }

            return { navigation, timer, leaders, press }
        }

        it('fires the letter pressed after g', () => {
            const { navigation, leaders, press } = setup()

            const g = press('KeyG')
            const o = press('KeyO')

            assert.equal(g.defaultPrevented, true)
            assert.equal(o.defaultPrevented, true)
            assert.equal(navigation.calls.length, 1)
            assert.deepEqual(leaders, ['KeyG', null])
        })

        it('does not fire the letter on its own', () => {
            const { navigation, press } = setup()

            press('KeyO')

            assert.equal(navigation.calls.length, 0)
        })

        it('waits for the next key only so long', () => {
            const { navigation, timer, leaders, press } = setup()

            press('KeyG')
            timer.fire()
            press('KeyO')

            assert.equal(navigation.calls.length, 0)
            assert.deepEqual(leaders, ['KeyG', null])
        })

        it('lets a key that completes nothing go to the page and ends the wait', () => {
            const { navigation, press } = setup()

            press('KeyG')
            const x = press('KeyX')
            press('KeyO')

            assert.equal(x.defaultPrevented, false)
            assert.equal(navigation.calls.length, 0)
        })

        it('cancels the wait on Escape', () => {
            const { navigation, press } = setup()

            press('KeyG')
            const escape = press('Escape')
            press('KeyO')

            assert.equal(escape.defaultPrevented, true)
            assert.equal(navigation.calls.length, 0)
        })

        it('types g into a field instead of starting the wait', () => {
            const { navigation, press } = setup()

            const g = press('KeyG', {
                target: createElement({ tagName: 'INPUT' }),
            })
            press('KeyO')

            assert.equal(g.defaultPrevented, false)
            assert.equal(navigation.calls.length, 0)
        })

        it('leaves Alt+G and other held combos to the browser', () => {
            const { navigation, leaders, press } = setup()

            const g = press('KeyG', { altKey: true })
            press('KeyO')

            assert.equal(g.defaultPrevented, false)
            assert.deepEqual(leaders, [])
            assert.equal(navigation.calls.length, 0)
        })

        it('puts the letter after g through the same guards as any bare key', () => {
            const { navigation, press } = setup()

            press('KeyG')
            press('KeyO', { target: createElement({ tagName: 'INPUT' }) })

            assert.equal(navigation.calls.length, 0)
        })

        it('keeps waiting while g is held down', () => {
            const { navigation, press } = setup()

            press('KeyG')
            press('KeyG', { repeat: true })
            press('KeyO')

            assert.equal(navigation.calls.length, 1)
        })
    })

    describe('while g waits, and the single-key switch', () => {
        function leaderGroup() {
            return {
                handler: 'navigation',
                modifier: '',
                leader: 'KeyG',
                bindings: [
                    {
                        code: 'KeyO',
                        activation: { kind: 'navigate', url: '/orders' },
                    },
                ],
            }
        }

        function setup({ singleKeys = true, groups = [leaderGroup()] } = {}) {
            const navigation = recorder()
            const global = recorder()
            const leaders = []
            const dispatch = createDispatcher(
                () => groups,
                { navigation, global },
                {
                    setTimer: () => 1,
                    clearTimer: () => {},
                    onLeader: (code) => leaders.push(code),
                    singleKeysEnabled: () => singleKeys,
                },
            )
            const press = (code, extra = {}) => {
                const event = createKeyEvent({
                    code,
                    target: createElement({ tagName: 'BODY' }),
                    ...extra,
                })
                dispatch(event)
                return event
            }

            return { navigation, global, leaders, dispatch, press }
        }

        it('keeps waiting through a press of Shift on its own', () => {
            const { navigation, press } = setup()

            press('KeyG')
            press('ShiftLeft', { key: 'Shift', shiftKey: true })
            press('KeyO')

            assert.equal(navigation.calls.length, 1)
        })

        it('stops waiting when the cheatsheet key is pressed', () => {
            const { navigation, leaders, press } = setup()

            press('KeyG')
            press('Slash', { key: '?', shiftKey: true })
            press('KeyO')

            assert.deepEqual(leaders, ['KeyG', null])
            assert.equal(navigation.calls.length, 0)
        })

        it('stops waiting when the page changes underneath it', () => {
            const { navigation, leaders, dispatch, press } = setup()

            press('KeyG')
            dispatch.cancelLeader()
            press('KeyO')

            assert.deepEqual(leaders, ['KeyG', null])
            assert.equal(navigation.calls.length, 0)
        })

        it('silences g and every bare key once single-key shortcuts are off', () => {
            const bareExport = group('global', { code: 'KeyE' })
            const { navigation, global, leaders, press } = setup({
                singleKeys: false,
                groups: [leaderGroup(), bareExport],
            })

            const g = press('KeyG')
            press('KeyO')
            press('KeyE')

            assert.equal(g.defaultPrevented, false)
            assert.deepEqual(leaders, [])
            assert.equal(navigation.calls.length, 0)
            assert.equal(global.calls.length, 0)
        })

        it('still fires held-modifier shortcuts with single-key shortcuts off', () => {
            const { global, press } = setup({
                singleKeys: false,
                groups: [group('global', { modifier: 'alt+shift' })],
            })

            press('KeyE', { altKey: true, shiftKey: true })

            assert.equal(global.calls.length, 1)
        })
    })

    describe('while a shortcut waits for its server response', () => {
        it('holds bare keys back, so the next key lands on the modal that is coming', () => {
            const table = recorder()
            const dispatch = createDispatcher(
                () => [group('table', { code: 'Enter' })],
                { table },
                { isAwaitingResponse: () => true },
            )

            const enter = createKeyEvent({
                code: 'Enter',
                target: createElement({ tagName: 'TR' }),
            })
            dispatch(enter)

            assert.equal(table.calls.length, 0)
            assert.equal(enter.defaultPrevented, false)
        })

        it('still fires a held-modifier shortcut', () => {
            const global = recorder()
            const dispatch = createDispatcher(
                () => [group('global', { modifier: 'alt+shift' })],
                { global },
                { isAwaitingResponse: () => true },
            )

            dispatch(
                createKeyEvent({ code: 'KeyE', altKey: true, shiftKey: true }),
            )

            assert.equal(global.calls.length, 1)
        })
    })
})
