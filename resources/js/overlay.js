import { isReservedOverlayKey, isTyping } from './keymap.js'
import { setSingleKeysEnabled, singleKeysEnabled } from './preferences.js'

// The cheatsheet the server injects on every page. Listeners live on the document so they survive
// Livewire navigation; the element itself is re-rendered per page, so it is always queried fresh.
const OVERLAY_ID = 'filament-shortcut-keys-overlay'
const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
const SINGLE_KEYS_SWITCH = '[data-fsk-single-keys]'
const PRESENCE_ROW = '[data-fsk-presence]'

let lastFocused = null

function overlay() {
    return document.getElementById(OVERLAY_ID)
}

function isOpen(element) {
    return element !== null && !element.hasAttribute('hidden')
}

function setHidden(element, hidden) {
    hidden
        ? element.setAttribute('hidden', '')
        : element.removeAttribute('hidden')
}

// The selector is built from a registered row-action name, so a name that is not valid CSS makes it
// throw. Such a row stays listed rather than keeping the cheatsheet from opening at all.
function isPresent(selector) {
    try {
        return document.querySelector(selector) !== null
    } catch {
        return true
    }
}

// A row action on a relation manager or a table widget is listed by the server without knowing whether
// that table has it: those tables load after the page. Each such row names the control it needs, and is
// shown only while the page has one. Checked on every open, since a lazy table can arrive at any time.
function syncPresence(element) {
    element.querySelectorAll(PRESENCE_ROW).forEach((row) => {
        setHidden(row, !isPresent(row.getAttribute('data-fsk-presence')))
    })

    element.querySelectorAll('.fsk-group').forEach((group) => {
        const rows = group.querySelectorAll('.fsk-row')
        setHidden(
            group,
            rows.length > 0 &&
                [...rows].every((row) => row.hasAttribute('hidden')),
        )
    })
}

function open(element) {
    // Remember where focus was so closing can return the user to exactly where they were.
    lastFocused = document.activeElement

    // The markup always renders the switch on; the saved choice is only known in the browser.
    const toggle = element.querySelector(SINGLE_KEYS_SWITCH)
    if (toggle) {
        toggle.checked = singleKeysEnabled()
    }

    syncPresence(element)
    element.removeAttribute('hidden')
    element.querySelector(FOCUSABLE)?.focus()
}

function close(element) {
    element.setAttribute('hidden', '')
    lastFocused?.focus?.()
    lastFocused = null
}

function trapTab(element, event) {
    const focusables = [...element.querySelectorAll(FOCUSABLE)]

    if (focusables.length === 0) {
        return
    }

    const first = focusables[0]
    const last = focusables[focusables.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        last.focus()
        event.preventDefault()
    } else if (!event.shiftKey && document.activeElement === last) {
        first.focus()
        event.preventDefault()
    }
}

function onKeydown(event) {
    const element = overlay()

    if (element === null) {
        return
    }

    // "?" toggles the cheatsheet, but never while the user is typing "?" into a field.
    if (isReservedOverlayKey(event) && !isTyping(event.target)) {
        event.preventDefault()
        isOpen(element) ? close(element) : open(element)
        return
    }

    if (!isOpen(element)) {
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        close(element)
    } else if (event.key === 'Tab') {
        trapTab(element, event)
    }
}

function onClick(event) {
    const element = overlay()

    if (!isOpen(element)) {
        return
    }

    // The backdrop is the overlay root itself; a click on it (or the close button) dismisses.
    if (
        event.target === element ||
        event.target.closest('[data-fsk-overlay-close]')
    ) {
        close(element)
    }
}

// Listeners live on the document so they survive Livewire navigation; the signal lets a newer
// evaluation of the bundle take them down.
export function initOverlay(signal, { onSingleKeysChange = () => {} } = {}) {
    document.addEventListener('keydown', onKeydown, { signal })
    document.addEventListener('click', onClick, { signal })
    document.addEventListener(
        'change',
        (event) => {
            if (!event.target?.closest?.(SINGLE_KEYS_SWITCH)) {
                return
            }

            setSingleKeysEnabled(event.target.checked)
            onSingleKeysChange(event.target.checked)
        },
        { signal },
    )
}
