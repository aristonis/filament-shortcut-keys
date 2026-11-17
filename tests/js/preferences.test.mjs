import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import {
    setSingleKeysEnabled,
    singleKeysEnabled,
} from '../../resources/js/preferences.js'

function memoryStorage() {
    const items = {}
    return {
        getItem: (key) => (key in items ? items[key] : null),
        setItem: (key, value) => {
            items[key] = String(value)
        },
        removeItem: (key) => {
            delete items[key]
        },
    }
}

afterEach(() => {
    delete globalThis.localStorage
})

describe('the single-key shortcut preference', () => {
    it('is on until the user turns it off, and remembers the choice', () => {
        globalThis.localStorage = memoryStorage()

        assert.equal(singleKeysEnabled(), true)
        setSingleKeysEnabled(false)
        assert.equal(singleKeysEnabled(), false)
        setSingleKeysEnabled(true)
        assert.equal(singleKeysEnabled(), true)
    })

    it('stays on where the browser refuses storage, as in a private window', () => {
        globalThis.localStorage = {
            getItem() {
                throw new Error('denied')
            },
            setItem() {
                throw new Error('denied')
            },
            removeItem() {
                throw new Error('denied')
            },
        }

        assert.doesNotThrow(() => setSingleKeysEnabled(false))
        assert.equal(singleKeysEnabled(), true)
    })
})
