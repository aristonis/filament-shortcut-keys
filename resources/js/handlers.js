import { noteClick } from './inflight.js'
import { safeQuery } from './query.js'

// One handler per client-handler key. Each takes the matched binding and returns true only when it
// actually fired something, so the dispatcher knows whether to swallow the key. A handler that can't
// act (missing element, wrong activation kind) returns false and lets the next candidate try.

function navigate(url, newTab = false) {
    // The action asked for a new tab, so it leaves the panel exactly as a click on its link would.
    if (newTab) {
        window.open(url, '_blank', 'noopener')
        return
    }

    // Prefer Livewire's SPA navigation so the shortcut feels like a normal in-panel click; fall back
    // to a full load when Livewire isn't present (e.g. a page outside the SPA shell).
    if (window.Livewire && typeof window.Livewire.navigate === 'function') {
        window.Livewire.navigate(url)
        return
    }

    window.location.assign(url)
}

function clickSelector(selector) {
    const element = safeQuery(document, selector)

    if (!element) {
        return false
    }

    noteClick(element)
    element.click()
    return true
}

function fireActivation(activation) {
    if (activation?.kind === 'navigate') {
        navigate(activation.url, activation.newTab === true)
        return true
    }

    if (activation?.kind === 'click') {
        return clickSelector(activation.selector)
    }

    return false
}

export const handlers = {
    navigation(binding) {
        const activation = binding.activation

        if (!activation || activation.kind !== 'navigate') {
            return false
        }

        navigate(activation.url, activation.newTab === true)
        return true
    },

    // A header action is either a Livewire control to click or an anchor to a page. Filament gives
    // Create, Edit and View a url whenever the resource registers that page, so both arrive here.
    global(binding) {
        return fireActivation(binding.activation)
    },

    custom(binding) {
        return fireActivation(binding.activation)
    },
}
