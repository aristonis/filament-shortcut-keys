import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import {
    isAwaitingResponse,
    noteClick,
    settle,
} from '../../resources/js/inflight.js'
import { createElement } from './support/dom.mjs'

const livewireButton = () =>
    createElement({
        tagName: 'BUTTON',
        attributes: { 'wire:click': "mountAction('delete')" },
    })

afterEach(() => settle())

describe('waiting for the request a shortcut started', () => {
    it('waits after a shortcut clicks a control that talks to the server', () => {
        noteClick(livewireButton(), 1000)

        assert.equal(isAwaitingResponse(1500), true)
    })

    it('does not wait after a click that stays in the browser', () => {
        // A row checkbox or a plain link sends no Livewire request, so nothing would end the wait.
        noteClick(createElement({ tagName: 'INPUT' }), 1000)

        assert.equal(isAwaitingResponse(1001), false)
    })

    it('stops waiting once the response has been rendered', () => {
        noteClick(livewireButton(), 1000)
        settle()

        assert.equal(isAwaitingResponse(1001), false)
    })

    it('gives up waiting after two seconds, so a lost response never locks the keys', () => {
        noteClick(livewireButton(), 1000)

        assert.equal(isAwaitingResponse(3001), false)
    })

    it('also waits for a click whose wire:click carries modifiers', () => {
        noteClick(
            createElement({
                tagName: 'BUTTON',
                attributes: { 'wire:click.prevent.stop': 'save' },
            }),
            1000,
        )

        assert.equal(isAwaitingResponse(1001), true)
    })
})
