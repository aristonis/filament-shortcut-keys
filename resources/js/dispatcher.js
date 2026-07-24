import { eventModifier, isReservedOverlayKey, isTyping } from './keymap.js'

// When two sets bind the same physical key, the more specific context wins: a page shortcut beats a
// table one, which beats a global one, which beats navigation. The first handler that fires stops the
// search.
const PRECEDENCE = ['page', 'table', 'global', 'navigation']

function groupByHandler(groups) {
    const byHandler = {}

    for (const group of groups) {
        ;(byHandler[group.handler] ??= []).push(group)
    }

    return byHandler
}

export function createDispatcher(getGroups, handlers) {
    return function onKeydown(event) {
        if (isReservedOverlayKey(event)) {
            return
        }

        const typing = isTyping(event.target)
        const pressedModifier = eventModifier(event)
        const byHandler = groupByHandler(getGroups())

        for (const handlerKey of PRECEDENCE) {
            const groups = byHandler[handlerKey]
            const handler = handlers[handlerKey]

            if (!groups || !handler) {
                continue
            }

            for (const group of groups) {
                // Bare-key groups stay dormant while typing so text input is never hijacked.
                if (typing && group.modifier === '') {
                    continue
                }

                if (group.modifier !== pressedModifier) {
                    continue
                }

                const binding = group.bindings.find((candidate) => candidate.code === event.code)

                if (binding && handler(binding, event)) {
                    event.preventDefault()
                    return
                }
            }
        }
    }
}
