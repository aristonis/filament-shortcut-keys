import { createDispatcher } from './dispatcher.js'
import { handlers as baseHandlers } from './handlers.js'
import { paintHints } from './hints.js'
import { readKeymap } from './keymap.js'
import { initOverlay } from './overlay.js'
import { createTableHandler } from './table.js'

// The keydown listener is attached once to the document, which survives Livewire's SPA navigation.
// Only the keymap it reads changes per page, so on each navigation we just re-read the freshly
// injected map instead of rebinding the listener.
let currentGroups = []

const tableHandler = createTableHandler()
const handlers = { ...baseHandlers, table: tableHandler }

function refreshKeymap() {
    currentGroups = readKeymap()
    // The focused-row cursor belongs to the page that was left behind.
    tableHandler.reset()
    paintHints(currentGroups)
}

function start(signal) {
    const dispatcher = createDispatcher(() => currentGroups, handlers)
    document.addEventListener('keydown', dispatcher, { signal })

    initOverlay(signal)

    refreshKeymap()
    document.addEventListener('livewire:navigated', refreshKeymap, { signal })
}

// Each evaluation of the bundle retires the one before it through a handle on window. A page that
// ends up with the script twice keeps one set of listeners, and an upgraded bundle loaded mid-session
// runs its own code instead of standing down for the old one.
const INSTANCE_KEY = '__filamentShortcutKeys'

function claimKeyboard() {
    window[INSTANCE_KEY]?.abort()

    const controller = new AbortController()
    window[INSTANCE_KEY] = controller

    return controller.signal
}

const signal = claimKeyboard()

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => start(signal), {
        signal,
    })
} else {
    start(signal)
}
