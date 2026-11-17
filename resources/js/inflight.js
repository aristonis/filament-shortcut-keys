// A shortcut that clicks a Livewire control starts a request, and whatever it opens (a confirm, an
// edit modal) only appears once the response is rendered. Until then the page looks unchanged, so a
// quick second key would land on the row behind the modal that is about to open. Bare keys wait for
// that response instead.

// Longer than any normal round trip; a request that never answers must not lock the keyboard.
const WAIT_MS = 2000

let waitUntil = 0

// Any wire:click form counts, modifiers included (wire:click.prevent.stop).
export function noteClick(element, now = Date.now()) {
    const names = element?.getAttributeNames?.() ?? []

    if (names.some((name) => name.startsWith('wire:click'))) {
        waitUntil = now + WAIT_MS
    }
}

export function isAwaitingResponse(now = Date.now()) {
    return now < waitUntil
}

export function settle() {
    waitUntil = 0
}
