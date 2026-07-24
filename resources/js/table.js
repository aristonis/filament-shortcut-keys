// The table handler owns a "focused row" cursor because table shortcuts (select, edit, delete, custom
// row actions) all act on one row, and Filament renders no such cursor of its own. Arrow keys move it;
// row-scoped actions read it. State is per-page, so index.js resets it on navigation.

const ROW_SELECTOR = 'tr.fi-ta-row'
const SEARCH_SELECTOR = '.fi-ta-search-field input, input[type="search"]'
const FOCUS_CLASS = 'fi-hotkey-row-focused'

function rows() {
    return [...document.querySelectorAll(ROW_SELECTOR)]
}

function paginationLink(direction) {
    const label = direction === 'prev' ? 'previous' : 'next'
    return document.querySelector(
        `.fi-pagination a[aria-label*="${label}" i], .fi-pagination button[aria-label*="${label}" i]`,
    )
}

export function createTableHandler() {
    let focusedIndex = -1

    function paint(all) {
        all.forEach((row, index) => {
            const focused = index === focusedIndex
            row.classList.toggle(FOCUS_CLASS, focused)
            // Inline outline so the cursor is visible without shipping a stylesheet.
            row.style.outline = focused ? '2px solid currentColor' : ''
            row.style.outlineOffset = focused ? '-2px' : ''
        })
    }

    function moveFocus(step) {
        const all = rows()
        if (all.length === 0) {
            return false
        }

        // From "no row", down starts at the top and up starts at the bottom.
        const start = focusedIndex < 0 ? (step > 0 ? 0 : all.length - 1) : focusedIndex + step
        focusedIndex = Math.max(0, Math.min(all.length - 1, start))
        paint(all)
        all[focusedIndex].scrollIntoView({ block: 'nearest' })
        return true
    }

    function focusedRow() {
        return focusedIndex < 0 ? null : rows()[focusedIndex] ?? null
    }

    function clickInRow(selector) {
        const row = focusedRow()
        const target = row && row.querySelector(selector)
        if (!target) {
            return false
        }

        target.click()
        return true
    }

    function runBehavior(behavior) {
        switch (behavior) {
            case 'search': {
                const input = document.querySelector(SEARCH_SELECTOR)
                if (!input) return false
                input.focus()
                return true
            }
            case 'row-up':
                return moveFocus(-1)
            case 'row-down':
                return moveFocus(1)
            case 'select':
                return clickInRow('input[type="checkbox"]')
            case 'edit':
                return clickInRow('[wire\\:click^="mountAction(\'edit\'"]')
            case 'delete':
                return clickInRow('[wire\\:click^="mountAction(\'delete\'"]')
            case 'page-prev':
            case 'page-next': {
                const link = paginationLink(behavior === 'page-prev' ? 'prev' : 'next')
                if (!link) return false
                link.click()
                return true
            }
            default:
                return false
        }
    }

    function handle(binding) {
        const activation = binding.activation

        if (!activation) {
            return false
        }

        // A custom row action arrives as a click, but must fire on the focused row rather than the
        // first matching button in the whole table.
        if (activation.kind === 'click') {
            return clickInRow(activation.selector)
        }

        if (activation.kind === 'table') {
            return runBehavior(activation.behavior)
        }

        return false
    }

    handle.reset = () => {
        focusedIndex = -1
    }

    return handle
}
