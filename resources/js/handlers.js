// One handler per client-handler key. Each takes the matched binding and returns true only when it
// actually fired something, so the dispatcher knows whether to swallow the key. A handler that can't
// act (missing element, wrong activation kind) returns false and lets the next candidate try.

function navigate(url) {
    // Prefer Livewire's SPA navigation so the shortcut feels like a normal in-panel click; fall back
    // to a full load when Livewire isn't present (e.g. a page outside the SPA shell).
    if (window.Livewire && typeof window.Livewire.navigate === 'function') {
        window.Livewire.navigate(url)
        return
    }

    window.location.assign(url)
}

function clickSelector(selector) {
    const element = document.querySelector(selector)

    if (!element) {
        return false
    }

    element.click()
    return true
}

export const handlers = {
    navigation(binding) {
        const activation = binding.activation

        if (!activation || activation.kind !== 'navigate') {
            return false
        }

        navigate(activation.url)
        return true
    },

    global(binding) {
        const activation = binding.activation

        if (!activation || activation.kind !== 'click') {
            return false
        }

        return clickSelector(activation.selector)
    },

    // A user's custom binding carries its own action: navigate a route or click a selector.
    custom(binding) {
        const activation = binding.activation

        if (activation?.kind === 'navigate') {
            navigate(activation.url)
            return true
        }

        if (activation?.kind === 'click') {
            return clickSelector(activation.selector)
        }

        return false
    },
}
