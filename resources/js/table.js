// The table handler owns a "focused row" cursor because table shortcuts (select, edit, delete, custom
// row actions) all act on one row, and Filament renders no such cursor of its own. j and k start and
// move it, the arrows move it once it is live, Escape releases it; row-scoped actions read it. State
// is per-page, so index.js resets it on navigation.

// Record rows only: Filament also renders a column-search row and group headers as tr.fi-ta-row. A
// record row's wire:key ends in its record key, which is how the cursor remembers it.
const ROW_SELECTOR = 'tr.fi-ta-row[wire\\:key*=".table.records."]'
const SEARCH_SELECTOR = '.fi-ta-search-field input, input[type="search"]'
const FOCUS_CLASS = 'fi-hotkey-row-focused'

function rows() {
    return [...document.querySelectorAll(ROW_SELECTOR)]
}

function keyOf(row) {
    return row.getAttribute('wire:key')
}

// Match on rel, which Filament emits on both the simple and the numbered pagination. The visible
// label and the aria-label are translated, so matching on those breaks in any non-English panel.
function paginationLink(direction) {
    return document.querySelector(`.fi-pagination [rel="${direction}"]`)
}

export function createTableHandler() {
    // The cursor remembers a record by its key. A sort, a search or a poll re-renders the rows, so a
    // remembered position would point a row action at a different record.
    let focusedKey = null

    function paint(all) {
        all.forEach((row) => {
            const focused = focusedKey !== null && keyOf(row) === focusedKey
            row.classList.toggle(FOCUS_CLASS, focused)
            // Inline outline so the cursor is visible without shipping a stylesheet.
            row.style.outline = focused ? '2px solid currentColor' : ''
            row.style.outlineOffset = focused ? '-2px' : ''
        })
    }

    // The marked record's current row, repainted because a re-render strips the mark. A record that
    // left the table takes the cursor with it.
    function focusedRow() {
        if (focusedKey === null) {
            return null
        }

        const all = rows()
        const row = all.find((candidate) => keyOf(candidate) === focusedKey)

        if (!row) {
            focusedKey = null
        }

        paint(all)
        return row ?? null
    }

    function moveFocus(step) {
        const all = rows()
        if (all.length === 0) {
            return false
        }

        // From "no row", down starts at the top and up starts at the bottom.
        const current = all.indexOf(focusedRow())
        const start =
            current < 0 ? (step > 0 ? 0 : all.length - 1) : current + step
        const row = all[Math.max(0, Math.min(all.length - 1, start))]
        focusedKey = keyOf(row)
        paint(all)

        // The cursor takes real focus, or Enter and Space would press whatever was clicked last
        // instead of acting on the marked row. -1 keeps rows out of the Tab order.
        if (!row.hasAttribute('tabindex')) {
            row.setAttribute('tabindex', '-1')
        }
        row.focus({ preventScroll: true })
        row.scrollIntoView({ block: 'nearest' })
        return true
    }

    // The arrows belong to the page (scrolling it, or a wide table sideways) until j or k starts the
    // cursor, and go back to it once the user focuses something else. Focus that fell back to the
    // page, as when a re-render replaced the focused row, keeps them; a click on the page releases
    // the cursor through onPointerDown instead.
    function isCursorLive() {
        const row = focusedRow()
        if (!row) {
            return false
        }

        const active = document.activeElement
        if (!active || active.tagName === 'BODY') {
            return true
        }

        return active === row || Boolean(active.closest?.(ROW_SELECTOR))
    }

    function drop() {
        focusedKey = null
        paint(rows())
    }

    // Escape counts only while the cursor is live. A stale cursor goes away and Escape goes to the
    // page.
    function release() {
        const row = focusedRow()
        if (!row) {
            return false
        }

        const live = isCursorLive()
        drop()

        if (live) {
            row.blur?.()
        }
        return live
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
                return isCursorLive() && moveFocus(-1)
            case 'row-down':
                return isCursorLive() && moveFocus(1)
            case 'cursor-up':
                return moveFocus(-1)
            case 'cursor-down':
                return moveFocus(1)
            case 'cursor-release':
                return release()
            case 'select':
                return clickInRow('input[type="checkbox"]')
            // Keyed on wire:key, not wire:click: an Edit action on a resource that registers an edit
            // page renders as an anchor with no wire:click, while a modal Edit renders as a button.
            // wire:key is emitted for a record action in either shape, with the record hash appended.
            case 'edit':
                return clickInRow('[wire\\:key*=".actions.edit."]')
            case 'delete':
                return clickInRow('[wire\\:key*=".actions.delete."]')
            case 'page-prev':
            case 'page-next': {
                if (!isCursorLive()) return false
                // Which arrow key carries which direction is decided server-side (mirrored in an
                // RTL panel); by the time it reaches here the behavior is already the real one.
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
        focusedKey = null
    }

    handle.onPointerDown = (event) => {
        if (focusedKey !== null && !event.target?.closest?.(ROW_SELECTOR)) {
            drop()
        }
    }

    return handle
}
