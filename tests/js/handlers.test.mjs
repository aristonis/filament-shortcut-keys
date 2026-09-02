import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'

import { handlers } from '../../resources/js/handlers.js'
import {
    createElement,
    installDocument,
    installWindow,
    restoreGlobals,
} from './support/dom.mjs'

function binding(activation) {
    return { code: 'KeyE', activation }
}

afterEach(restoreGlobals)

describe('the navigation handler', () => {
    it('navigates through Livewire when the SPA shell is present', () => {
        installDocument()
        const visited = []
        installWindow({ Livewire: { navigate: (url) => visited.push(url) } })

        assert.equal(
            handlers.navigation(binding({ kind: 'navigate', url: '/orders' })),
            true,
        )
        assert.deepEqual(visited, ['/orders'])
    })

    it('falls back to a full page load outside the SPA shell', () => {
        installDocument()
        const window = installWindow()

        assert.equal(
            handlers.navigation(binding({ kind: 'navigate', url: '/orders' })),
            true,
        )
        assert.deepEqual(window.location.assigned, ['/orders'])
    })

    it('falls back when Livewire is present but exposes no navigate', () => {
        installDocument()
        const window = installWindow({ Livewire: {} })

        handlers.navigation(binding({ kind: 'navigate', url: '/orders' }))

        assert.deepEqual(window.location.assigned, ['/orders'])
    })

    it('declines anything that is not a navigation', () => {
        installDocument()
        installWindow()

        assert.equal(
            handlers.navigation(binding({ kind: 'click', selector: '#x' })),
            false,
        )
        assert.equal(handlers.navigation(binding(null)), false)
    })
})

// Header actions and custom bindings both fire either way, so they are checked against the same
// expectations. This is the pair hints.js has to badge on both branches.
for (const name of ['global', 'custom']) {
    describe(`the ${name} handler`, () => {
        it('navigates when the action is a link', () => {
            // Filament renders Create, Edit and View as anchors whenever the resource registers the
            // page, and as Livewire controls when it does not. Both shapes arrive here.
            installDocument()
            const visited = []
            installWindow({
                Livewire: { navigate: (url) => visited.push(url) },
            })

            assert.equal(
                handlers[name](
                    binding({ kind: 'navigate', url: '/orders/create' }),
                ),
                true,
            )
            assert.deepEqual(visited, ['/orders/create'])
        })

        it('clicks when the action is a control', () => {
            const button = createElement({ tagName: 'BUTTON' })
            installDocument({ selectors: { '#create': button } })
            installWindow()

            assert.equal(
                handlers[name](binding({ kind: 'click', selector: '#create' })),
                true,
            )
            assert.equal(button.clicks, 1)
        })

        it('reports failure when the control is not on the page, so the next candidate can try', () => {
            installDocument({ selectors: {} })
            installWindow()

            assert.equal(
                handlers[name](binding({ kind: 'click', selector: '#gone' })),
                false,
            )
        })

        it('declines a binding with no activation', () => {
            installDocument()
            installWindow()

            assert.equal(handlers[name](binding(null)), false)
        })
    })
}
