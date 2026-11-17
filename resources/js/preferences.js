// The user's own switch for single-key shortcuts (the bare keys and the g sequences), which assistive
// technology and voice control need to be able to turn off. It lives in the browser rather than the
// database because it is about the person at this keyboard, not about their keymap.

const STORAGE_KEY = 'filament-shortcut-keys:single-keys'

// Storage can be missing or refuse access (a private window, blocked site data). The shortcuts then
// stay on, which is the default every user starts with anyway.
export function singleKeysEnabled() {
    try {
        return globalThis.localStorage?.getItem(STORAGE_KEY) !== 'off'
    } catch {
        return true
    }
}

export function setSingleKeysEnabled(enabled) {
    try {
        if (enabled) {
            globalThis.localStorage?.removeItem(STORAGE_KEY)
        } else {
            globalThis.localStorage?.setItem(STORAGE_KEY, 'off')
        }
    } catch {
        // Nothing to save to; the switch still works for the rest of this page.
    }
}
