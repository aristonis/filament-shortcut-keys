import { createDispatcher } from './dispatcher.js'
import { handlers as baseHandlers } from './handlers.js'
import { paintHints } from './hints.js'
import { readKeymap } from './keymap.js'
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

function start() {
    const dispatcher = createDispatcher(() => currentGroups, handlers)
    document.addEventListener('keydown', dispatcher)

    refreshKeymap()
    document.addEventListener('livewire:navigated', refreshKeymap)
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start)
} else {
    start()
}
