import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { createDispatcher } from '../../resources/js/dispatcher.js'
import { createElement, createKeyEvent } from './support/dom.mjs'

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
})
