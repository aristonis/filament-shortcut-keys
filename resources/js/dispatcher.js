import {
    eventModifier,
    isControlActivation,
    isInKeyboardOwner,
    isModalOpen,
    isReservedOverlayKey,
    isTyping,
} from './keymap.js'

// When two sets bind the same physical key, the more specific context wins: a page shortcut beats a
// user's custom binding, which beats table, global, then navigation. The first handler that fires
// stops the search.
const PRECEDENCE = ['page', 'custom', 'table', 'global', 'navigation']

// Moving the row cursor is the one action worth repeating while a key is held; every other shortcut
// fires once per press, so a held Delete or navigation key cannot stack up actions.
const REPEATABLE_BEHAVIORS = ['row-up', 'row-down']

function isRepeatable(binding) {
    const activation = binding.activation

    return (
        activation?.kind === 'table' &&
        REPEATABLE_BEHAVIORS.includes(activation.behavior)
    )
}

function groupByHandler(groups) {
    const byHandler = {}

    for (const group of groups) {
        ;(byHandler[group.handler] ??= []).push(group)
    }

    return byHandler
}

// Safari ends composition before the final keydown, which then only carries this legacy code.
const IME_PROCESS_KEY_CODE = 229

function isComposing(event) {
    return event.isComposing || event.keyCode === IME_PROCESS_KEY_CODE
}

// Returns the binding that fired, or null when the key is left to the browser.
function fire(event, groups, handlers) {
    const bareKeysDormant =
        isTyping(event.target) ||
        isInKeyboardOwner(event.target) ||
        isModalOpen() ||
        isControlActivation(event)
    const pressedModifier = eventModifier(event)
    const byHandler = groupByHandler(groups)

    for (const handlerKey of PRECEDENCE) {
        const groups = byHandler[handlerKey]
        const handler = handlers[handlerKey]

        if (!groups || !handler) {
            continue
        }

        for (const group of groups) {
            // Bare keys belong to whatever has focus when it is a field, a modal, an open list or a
            // button being pressed; only a held modifier says the key is meant for us.
            if (bareKeysDormant && group.modifier === '') {
                continue
            }

            if (group.modifier !== pressedModifier) {
                continue
            }

            const binding = group.bindings.find(
                (candidate) => candidate.code === event.code,
            )

            if (binding && handler(binding, event)) {
                event.preventDefault()
                return binding
            }
        }
    }

    return null
}

export function createDispatcher(getGroups, handlers) {
    // What the last fresh press of each key did. Its auto-repeat is judged by that, not by whichever
    // binding happens to match first, so a held key never stacks actions nor leaks to the browser
    // halfway through.
    let lastPress = null

    return function onKeydown(event) {
        if (isComposing(event) || isReservedOverlayKey(event)) {
            return
        }

        if (event.repeat) {
            if (lastPress?.code !== event.code) {
                return
            }

            if (!lastPress.repeatable) {
                event.preventDefault()
                return
            }
        }

        const fired = fire(event, getGroups(), handlers)

        if (!event.repeat) {
            lastPress = fired
                ? { code: event.code, repeatable: isRepeatable(fired) }
                : null
        }
    }
}
