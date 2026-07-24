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

// A bare-key shortcut (table set) must not fire while the user is typing, or "/" would never reach a
// search box. Modifier shortcuts are still allowed through; the dispatcher decides per group.
export function isTyping(target) {
    if (!target) {
        return false
    }

    if (target.isContentEditable) {
        return true
    }

    const tag = target.tagName
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
}

// Shift+Slash ("?") is reserved for the cheatsheet overlay, so the dispatcher never treats it as a
// normal shortcut even though "/" alone is the table search key.
export function isReservedOverlayKey(event) {
    return event.code === 'Slash' && event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey
}
