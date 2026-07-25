import { isReservedOverlayKey, isTyping } from './keymap.js'

// The cheatsheet the server injects on every page. Listeners live on the document so they survive
// Livewire navigation; the element itself is re-rendered per page, so it is always queried fresh.
const OVERLAY_ID = 'filament-shortcut-keys-overlay'
const FOCUSABLE =
    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'

let lastFocused = null

function overlay() {
    return document.getElementById(OVERLAY_ID)
}

function isOpen(element) {
    return element !== null && !element.hasAttribute('hidden')
}

function open(element) {
    // Remember where focus was so closing can return the user to exactly where they were.
    lastFocused = document.activeElement
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

export function initOverlay() {
    document.addEventListener('keydown', (event) => {
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
    })

    document.addEventListener('click', (event) => {
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
    })
}
