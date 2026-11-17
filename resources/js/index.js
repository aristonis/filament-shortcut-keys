import { createDispatcher } from './dispatcher.js'
import { handlers as baseHandlers } from './handlers.js'
import { paintHints } from './hints.js'
import { readKeymap } from './keymap.js'
import { initOverlay } from './overlay.js'
import { singleKeysEnabled } from './preferences.js'
import { createTableHandler } from './table.js'

// The keydown listener is attached once to the document, which survives Livewire's SPA navigation.
// Only the keymap it reads changes per page, so on each navigation we just re-read the freshly
// injected map instead of rebinding the listener.
let currentGroups = []
let dispatcher = null

const tableHandler = createTableHandler()
const handlers = { ...baseHandlers, table: tableHandler }

function repaint(leader = null) {
    paintHints(currentGroups, { leader, singleKeys: singleKeysEnabled() })
}

function refreshKeymap() {
    currentGroups = readKeymap()
    // The focused-row cursor and a pending g belong to the page that was left behind.
    tableHandler.reset()
    dispatcher?.cancelLeader()
    repaint()
}

function start(signal) {
    dispatcher = createDispatcher(() => currentGroups, handlers, {
        onLeader: repaint,
        singleKeysEnabled,
    })
    document.addEventListener('keydown', dispatcher, { signal })

    initOverlay(signal, { onSingleKeysChange: () => repaint() })

    refreshKeymap()
    document.addEventListener('livewire:navigated', refreshKeymap, { signal })
    document.addEventListener('pointerdown', tableHandler.onPointerDown, {
        signal,
    })
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
