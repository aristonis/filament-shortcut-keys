// Renders a small key badge on the element each shortcut fires, so the keys are discoverable in place.
// Only navigation links, header actions and custom bindings get a badge: they map to one stable
// element each. Table keys are behaviours (no single element) and row actions repeat per row, so
// those live in the cheatsheet overlay instead.

const HINT_CLASS = 'fi-hotkey-hint'

// An allowlist, so a handler added later has to opt in rather than start painting badges on markup
// nobody checked.
const HINTED_HANDLERS = ['navigation', 'global', 'custom']

const codeToKey = (code) => (code.startsWith('Key') ? code.slice(3) : code)

function badge(text) {
    const el = document.createElement('span')
    el.className = HINT_CLASS
    el.textContent = text
    el.setAttribute('aria-hidden', 'true')
    el.style.cssText =
        'margin-inline-start:.35em;padding:0 .3em;border-radius:.25rem;font-size:.7em;line-height:1.5;opacity:.55;border:1px solid currentColor;'
    return el
}

// Gate on how the binding fires, not on which handler owns it. A header action is a Livewire control
// to click or an anchor to a page depending on whether the resource registers that page, so Create,
// Edit and View arrive here as `navigate` under the global handler and would otherwise go unbadged
// while still firing on the key.
function hostFor(group, binding) {
    const activation = binding.activation

    if (!HINTED_HANDLERS.includes(group.handler)) {
        return null
    }

    if (activation?.kind === 'navigate') {
        return document.querySelector(`a[href="${activation.url}"]`)
    }

    if (activation?.kind === 'click') {
        return document.querySelector(activation.selector)
    }

    return null
}

export function paintHints(groups) {
    document.querySelectorAll('.' + HINT_CLASS).forEach((el) => el.remove())

    for (const group of groups) {
        for (const binding of group.bindings) {
            const host = hostFor(group, binding)

            if (host && !host.querySelector('.' + HINT_CLASS)) {
                host.appendChild(badge(codeToKey(binding.code)))
            }
        }
    }
}
