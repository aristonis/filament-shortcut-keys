// A stub DOM for the client modules, deliberately not a DOM implementation.
//
// Selectors resolve by exact string lookup against a registry each test supplies, so a test states
// "this selector finds that element" and the module under test is checked on what it asks for and
// what it does with the answer. Whether a selector actually matches Filament's markup is a different
// question, answered against real HTML by SelectorProbe on the PHP side.
//
// The one exception is a bare `.class` lookup, which falls back to scanning appended children. Hints
// append a badge and then re-query for it to avoid painting twice, and a registry alone cannot see a
// node that was added after the fact.

function resolve(registry, selector) {
    const value = registry[selector]

    if (value === undefined) {
        return []
    }

    return Array.isArray(value) ? value : [value]
}

export function createElement(options = {}) {
    const {
        tagName = 'DIV',
        attributes = {},
        selectors = {},
        matches = [],
        textContent = '',
        isContentEditable = false,
    } = options

    return {
        tagName,
        textContent,
        isContentEditable,
        className: '',
        style: {},
        children: [],
        parent: null,
        // Counters instead of spies: a test asserts "this row was clicked once", which is the
        // behaviour, rather than asserting a call happened on some mock object.
        clicks: 0,
        focusCalls: 0,
        scrollCalls: [],
        removed: false,
        attributes: { ...attributes },
        selectors,
        matches,

        classList: {
            names: new Set(),
            toggle(name, force) {
                const on = force === undefined ? !this.names.has(name) : force
                on ? this.names.add(name) : this.names.delete(name)

                return on
            },
            contains(name) {
                return this.names.has(name)
            },
        },

        setAttribute(name, value) {
            this.attributes[name] = value
        },
        getAttribute(name) {
            return name in this.attributes ? this.attributes[name] : null
        },
        hasAttribute(name) {
            return name in this.attributes
        },
        removeAttribute(name) {
            delete this.attributes[name]
        },

        appendChild(child) {
            child.parent = this
            this.children.push(child)

            return child
        },
        remove() {
            this.removed = true

            if (this.parent) {
                this.parent.children = this.parent.children.filter(
                    (c) => c !== this,
                )
                this.parent = null
            }
        },

        querySelector(selector) {
            const registered = resolve(this.selectors, selector)

            if (registered.length > 0) {
                return registered[0]
            }

            if (selector.startsWith('.')) {
                const name = selector.slice(1)

                return (
                    this.children.find((child) => child.className === name) ??
                    null
                )
            }

            return null
        },
        querySelectorAll(selector) {
            return resolve(this.selectors, selector)
        },
        closest(selector) {
            let node = this

            while (node) {
                if (node.matches.includes(selector)) {
                    return node
                }

                node = node.parent
            }

            return null
        },

        click() {
            this.clicks++
        },
        focus() {
            this.focusCalls++

            if (globalThis.document) {
                globalThis.document.activeElement = this
            }
        },
        scrollIntoView(options) {
            this.scrollCalls.push(options)
        },
    }
}

export function installDocument({
    selectors = {},
    elementsById = {},
    activeElement = null,
    readyState = 'complete',
} = {}) {
    const listeners = {}

    const document = {
        readyState,
        activeElement,
        selectors,
        elementsById,
        created: [],

        getElementById(id) {
            return elementsById[id] ?? null
        },
        querySelector(selector) {
            return resolve(selectors, selector)[0] ?? null
        },
        querySelectorAll(selector) {
            return resolve(selectors, selector)
        },
        createElement(tagName) {
            const element = createElement({ tagName: tagName.toUpperCase() })
            document.created.push(element)

            return element
        },
        addEventListener(type, handler) {
            ;(listeners[type] ??= []).push(handler)
        },

        // Test-only: fire everything registered for an event type, in registration order.
        dispatch(type, event) {
            for (const handler of listeners[type] ?? []) {
                handler(event)
            }

            return event
        },
    }

    globalThis.document = document

    return document
}

export function installWindow(overrides = {}) {
    const window = {
        location: {
            assigned: [],
            assign(url) {
                this.assigned.push(url)
            },
        },
        ...overrides,
    }

    globalThis.window = window

    return window
}

export function restoreGlobals() {
    delete globalThis.document
    delete globalThis.window
}

export function createKeyEvent(overrides = {}) {
    return {
        code: '',
        key: '',
        ctrlKey: false,
        altKey: false,
        shiftKey: false,
        metaKey: false,
        target: null,
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true
        },
        ...overrides,
    }
}
