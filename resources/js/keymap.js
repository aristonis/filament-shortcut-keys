// Reads the keymap the server injects into every panel page and normalises keyboard events so they
// can be compared against it. The modifier order here (ctrl, alt, shift, meta) must match the order
// the PHP ModifierScheme serialises, otherwise "alt+shift" would never match a real Alt+Shift press.

const KEYMAP_ELEMENT_ID = 'filament-shortcut-keys-map'

export function readKeymap() {
    const element = document.getElementById(KEYMAP_ELEMENT_ID)

    if (!element) {
        return []
    }

    try {
        return JSON.parse(element.textContent)
    } catch {
        return []
    }
}

export function eventModifier(event) {
    const parts = []

    if (event.ctrlKey) parts.push('ctrl')
    if (event.altKey) parts.push('alt')
    if (event.shiftKey) parts.push('shift')
    if (event.metaKey) parts.push('meta')

    return parts.join('+')
}

// Inputs that take no text. A clicked row checkbox keeps focus, and counting it as typing left every
// bare key dead until the user clicked somewhere else.
const NON_TEXT_INPUT_TYPES = [
    'checkbox',
    'radio',
    'button',
    'submit',
    'reset',
    'image',
    'file',
    'color',
    'range',
]

function isNonTextInput(target) {
    if (target.tagName !== 'INPUT') {
        return false
    }

    const type = target.type || target.getAttribute?.('type') || 'text'

    return NON_TEXT_INPUT_TYPES.includes(String(type).toLowerCase())
}

// A shortcut with no modifier must not fire while the user is typing, or "/" would never reach a
// search box. Modifier shortcuts are still allowed through; the dispatcher decides per group.
export function isTyping(target) {
    if (!target) {
        return false
    }

    if (target.isContentEditable) {
        return true
    }

    if (isNonTextInput(target)) {
        return false
    }

    const tag = target.tagName
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
}

// While focus is inside a modal, an open select, a menu or a tab list, that widget owns the keyboard:
// Enter confirms the modal, arrows move through the options. Closed Filament modals stay in the DOM,
// so this asks where focus is, and isModalOpen covers the rest.
const KEYBOARD_OWNERS = [
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

export function isInKeyboardOwner(target) {
    if (!target?.closest) {
        return false
    }

    return KEYBOARD_OWNERS.some((selector) => target.closest(selector) !== null)
}

// A Filament modal can open without moving focus, leaving it on the row behind. The page is covered
// either way, so an open modal silences bare keys wherever focus is.
const OPEN_MODALS = [
    '.fi-modal-open[aria-modal="true"]',
    '#filament-shortcut-keys-overlay:not([hidden])',
]

export function isModalOpen() {
    const document = globalThis.document

    if (!document) {
        return false
    }

    return OPEN_MODALS.some(
        (selector) => document.querySelector(selector) !== null,
    )
}

const ACTIVATION_CODES = ['Enter', 'NumpadEnter', 'Space']
const CONTROL_TAGS = ['BUTTON', 'A', 'SUMMARY']
const CONTROL_ROLES = ['button', 'link']

// Enter and Space on a focused button or link mean "press this", so they stay with the control.
export function isControlActivation(event) {
    const target = event.target

    if (!target || !ACTIVATION_CODES.includes(event.code)) {
        return false
    }

    return (
        CONTROL_TAGS.includes(target.tagName) ||
        isNonTextInput(target) ||
        CONTROL_ROLES.includes(target.getAttribute?.('role'))
    )
}

// Shift+Slash ("?") is reserved for the cheatsheet overlay, so the dispatcher never treats it as a
// normal shortcut even though "/" alone is the table search key.
export function isReservedOverlayKey(event) {
    return event.code === 'Slash' && event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey
}
