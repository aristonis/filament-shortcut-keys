import { linkTo, safeQuery } from './query.js'

// Renders a small key badge on the element each shortcut fires, so the keys are discoverable in place.
// Only navigation links, header actions and custom bindings get a badge: they map to one stable
// element each. Table keys are behaviours (no single element) and row actions repeat per row, so
// those live in the cheatsheet overlay instead.

const HINT_CLASS = 'fi-hotkey-hint'

// An allowlist, so a handler added later has to opt in rather than start painting badges on markup
// nobody checked.
const HINTED_HANDLERS = ['navigation', 'global', 'custom']

const codeToKey = (code) => (code.startsWith('Key') ? code.slice(3) : code)

// Spelled as aria-keyshortcuts requires: Control, not Ctrl.
const ARIA_MODIFIERS = {
    ctrl: 'Control',
    alt: 'Alt',
    shift: 'Shift',
    meta: 'Meta',
}

// Marks an aria-keyshortcuts this script wrote, so a repaint takes back exactly those, including any
// an earlier copy of the script left after an upgrade without a reload, and never one the host set.
const OWNED = 'data-fsk-keyshortcuts'

let annotated = []

// Only letters have a spec name this can produce with certainty; anything else is left unannounced
// rather than announced wrong.
function ariaShortcut(modifier, code) {
    if (!/^Key[A-Z]$/.test(code)) {
        return null
    }

    const parts = modifier
        .split('+')
        .map((name) => ARIA_MODIFIERS[name])
        .filter(Boolean)

    return [...parts, codeToKey(code)].join('+')
}

function annotate(host, shortcut) {
    if (shortcut === null) {
        return
    }

    if (host.hasAttribute('aria-keyshortcuts') && !host.hasAttribute(OWNED)) {
        return
    }

    host.setAttribute('aria-keyshortcuts', shortcut)
    host.setAttribute(OWNED, '')
    annotated.push(host)
}

function releaseAnnotations() {
    const owned = [...annotated, ...document.querySelectorAll(`[${OWNED}]`)]

    owned.forEach((host) => {
        host.removeAttribute('aria-keyshortcuts')
        host.removeAttribute(OWNED)
    })
    annotated = []
}

function badge(text) {
    const el = document.createElement('span')
    el.className = HINT_CLASS
    el.textContent = text
    el.setAttribute('aria-hidden', 'true')
    el.style.cssText =
        'margin-inline-start:.35em;padding:0 .3em;border-radius:.25rem;font-size:.7em;line-height:1.5;opacity:.55;border:1px solid currentColor;'
    return el
}

// Pick the host from the activation kind, since Create, Edit and View can arrive as `navigate` under
// the global handler: a header action is a Livewire control to click or an anchor to a page depending
// on whether the resource registers that page, and either shape fires on the key.
function hostFor(group, binding, leader, singleKeys) {
    const activation = binding.activation

    if (!HINTED_HANDLERS.includes(group.handler)) {
        return null
    }

    // With single-key shortcuts off, a badge would advertise a key that does nothing.
    if (!singleKeys && group.modifier === '') {
        return null
    }

    // A g shortcut's badge appears only while g waits for its letter, so the sidebar is not covered
    // in letters the rest of the time.
    if ((group.leader ?? null) !== null && group.leader !== leader) {
        return null
    }

    if (activation?.kind === 'navigate') {
        return linkTo(activation.url)
    }

    if (activation?.kind === 'click') {
        return safeQuery(document, activation.selector)
    }

    return null
}

export function paintHints(groups, { leader = null, singleKeys = true } = {}) {
    document.querySelectorAll('.' + HINT_CLASS).forEach((el) => el.remove())
    releaseAnnotations()

    for (const group of groups) {
        for (const binding of group.bindings) {
            const host = hostFor(group, binding, leader, singleKeys)

            if (!host) {
                continue
            }

            if (!host.querySelector('.' + HINT_CLASS)) {
                host.appendChild(badge(codeToKey(binding.code)))
            }

            // aria-keyshortcuts lists alternatives separated by spaces, so a g sequence cannot be
            // written in it at all; the cheatsheet describes those instead.
            if (!group.leader) {
                annotate(host, ariaShortcut(group.modifier, binding.code))
            }
        }
    }
}
