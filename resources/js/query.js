// Custom selectors and routes come from an admin form that checks only their shape, so the browser can
// still reject one as CSS. Every lookup built from server or user input goes through here: a broken
// binding finds nothing and does nothing, rather than throwing out of a paint or a key press and
// taking every other shortcut down with it.

export function safeQuery(scope, selector) {
    try {
        return scope.querySelector(selector)
    } catch {
        return null
    }
}

// The anchor that links to a url. Escaped where the browser can, so a quote or backslash in the url
// still finds its link; without CSS.escape such a url simply finds nothing.
export function linkTo(url) {
    const value = globalThis.CSS?.escape ? globalThis.CSS.escape(url) : url

    return safeQuery(document, `a[href="${value}"]`)
}
