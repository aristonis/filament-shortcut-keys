import { createDispatcher } from './dispatcher.js'
import { handlers as baseHandlers } from './handlers.js'
import { paintHints } from './hints.js'
import { readKeymap } from './keymap.js'
import { isAwaitingResponse, settle } from './inflight.js'
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
    settle()
    repaint()
}

// Livewire re-renders a page in place after any action, and the server's markup carries none of the
// badges or announcements added here; the keymap can change with it too. Several components can
// morph in one update, so the repaint runs once per batch.
let morphRepaintQueued = false

function onMorphed(signal) {
    if (signal.aborted || morphRepaintQueued) {
        return
    }

    morphRepaintQueued = true
    queueMicrotask(() => {
        morphRepaintQueued = false
        currentGroups = readKeymap()
        repaint()
    })
}

// Livewire offers no way to remove a hook, so an older copy's hooks stay registered; they do nothing
// once its signal is aborted.
function listenToLivewire(signal) {
    const register = () => {
        window.Livewire?.hook?.('morphed', () => onMorphed(signal))

        // The end of a request releases the keys a shortcut held back. A response can render nothing
        // (a download, a dispatched event), and then no morph follows to say it is over.
        window.Livewire?.hook?.('commit', ({ succeed, fail }) => {
            const release = () => {
                if (!signal.aborted) {
                    settle()
                }
            }
            succeed(release)
            fail(release)
        })
    }

    if (window.Livewire) {
        register()
    } else {
        document.addEventListener('livewire:init', register, {
            signal,
            once: true,
        })
    }
}

function start(signal) {
    dispatcher = createDispatcher(() => currentGroups, handlers, {
        onLeader: repaint,
        singleKeysEnabled,
        isAwaitingResponse,
    })
    document.addEventListener('keydown', dispatcher, { signal })

    initOverlay(signal, { onSingleKeysChange: () => repaint() })

    refreshKeymap()
    listenToLivewire(signal)
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
