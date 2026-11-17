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
const REPEATABLE_BEHAVIORS = ['row-up', 'row-down', 'cursor-up', 'cursor-down']

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

// How long a pressed leader waits for its letter before the keyboard goes back to normal.
const LEADER_TIMEOUT_MS = 1500

function isBareKeyDormant(event) {
    return (
        isTyping(event.target) ||
        isInKeyboardOwner(event.target) ||
        isModalOpen() ||
        isControlActivation(event)
    )
}

function leaderCodes(groups) {
    return groups.map((group) => group.leader).filter(Boolean)
}

// Returns the binding that fired, or null when the key is left to the browser. A group with a leader
// takes part only while that leader is pending, and every other group only while none is.
function fire(event, groups, handlers, leader, singleKeys) {
    const bareKeysDormant = !singleKeys || isBareKeyDormant(event)
    const pressedModifier = eventModifier(event)
    const byHandler = groupByHandler(groups)

    for (const handlerKey of PRECEDENCE) {
        const groups = byHandler[handlerKey]
        const handler = handlers[handlerKey]

        if (!groups || !handler) {
            continue
        }

        for (const group of groups) {
            if ((group.leader ?? null) !== leader) {
                continue
            }

            // Bare keys belong to whatever has focus when it is a field, a modal, an open list or a
            // button being pressed, and stay silent while the user has single-key shortcuts off;
            // only a held modifier says the key is meant for us.
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

// A leader is pressed and released on its own, then the letter follows. It only starts on a bare
// press the page owns, so g still types g in a field and Alt+G stays with the browser.
function startsLeader(event, groups) {
    return (
        !event.repeat &&
        eventModifier(event) === '' &&
        leaderCodes(groups).includes(event.code) &&
        !isBareKeyDormant(event)
    )
}

// A press of Shift, Ctrl, Alt or Meta on its own just starts the next key.
const MODIFIER_KEYS = ['Shift', 'Control', 'Alt', 'Meta']

export function createDispatcher(getGroups, handlers, options = {}) {
    const {
        onLeader = () => {},
        singleKeysEnabled = () => true,
        setTimer = (callback, ms) => setTimeout(callback, ms),
        clearTimer = (id) => clearTimeout(id),
    } = options

    // What the last fresh press of each key did. Its auto-repeat is judged by that, not by whichever
    // binding happens to match first, so a held key neither stacks actions nor leaks to the browser
    // halfway through.
    let lastPress = null
    let pendingLeader = null
    let leaderTimer = null

    function endLeader() {
        clearTimer(leaderTimer)
        pendingLeader = null
        onLeader(null)
    }

    function beginLeader(code) {
        pendingLeader = code
        leaderTimer = setTimer(endLeader, LEADER_TIMEOUT_MS)
        onLeader(code)
    }

    function onKeydown(event) {
        if (isComposing(event)) {
            return
        }

        if (isReservedOverlayKey(event)) {
            // The cheatsheet takes over the keyboard, so a pending g has nothing left to complete.
            if (pendingLeader !== null) {
                endLeader()
            }
            return
        }

        if (pendingLeader !== null && MODIFIER_KEYS.includes(event.key)) {
            return
        }

        if (event.repeat) {
            // A held leader keeps waiting; it never repeats into its own letter.
            if (pendingLeader !== null || lastPress?.code !== event.code) {
                return
            }

            if (!lastPress.repeatable) {
                event.preventDefault()
                return
            }
        }

        const groups = getGroups()
        const leader = pendingLeader
        const singleKeys = singleKeysEnabled()

        if (leader !== null) {
            endLeader()

            if (event.code === 'Escape') {
                event.preventDefault()
                lastPress = null
                return
            }
        } else if (singleKeys && startsLeader(event, groups)) {
            event.preventDefault()
            lastPress = null
            beginLeader(event.code)
            return
        }

        const fired = fire(event, groups, handlers, leader, singleKeys)

        if (!event.repeat) {
            lastPress = fired
                ? { code: event.code, repeatable: isRepeatable(fired) }
                : null
        }
    }

    // For a page change while g waits: its letter would be read against a keymap it was not meant for.
    onKeydown.cancelLeader = () => {
        if (pendingLeader !== null) {
            endLeader()
        }
    }

    return onKeydown
}
