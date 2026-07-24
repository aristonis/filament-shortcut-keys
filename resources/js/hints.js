// Renders a small key badge on the element each shortcut fires, so the keys are discoverable in place.
// Only navigation links and global action buttons get a badge: they map to one stable element each.
// Table keys are behaviours (no single element) and row actions repeat per row, so those live in the
// cheatsheet overlay instead.

const HINT_CLASS = 'fi-hotkey-hint'

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

function hostFor(group, binding) {
    const activation = binding.activation

    if (group.handler === 'navigation' && activation?.kind === 'navigate') {
        return document.querySelector(`a[href="${activation.url}"]`)
    }

    if (group.handler === 'global' && activation?.kind === 'click') {
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
