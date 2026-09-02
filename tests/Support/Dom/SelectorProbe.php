<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Dom;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

/**
 * Counts how many elements in a blob of HTML a package-emitted selector would match.
 *
 * The point is to assert against real Filament markup rather than against the string the adapter
 * happened to build. A selector test that only compares two PHP strings passes even when Filament
 * renders the control in a shape the selector cannot reach, which is exactly how link-shaped actions
 * (View, Edit, Create) stayed unreachable while the suite was green.
 *
 * Only the two attribute forms the package emits are supported — `[name*="value"]` and
 * `[name^="value"]`, with the CSS `\:` escape in the attribute name. Anything else throws rather than
 * silently reporting zero matches, so a typo in a test reads as a broken test, not a failing feature.
 */
final class SelectorProbe
{
    private const PATTERN = '/^\[(?P<name>[A-Za-z0-9_\-\\\\:]+)(?P<operator>[*^])="(?P<value>.*)"\]$/';

    public static function countMatches(string $html, string $selector): int
    {
        if (! preg_match(self::PATTERN, $selector, $parts)) {
            throw new InvalidArgumentException("Unsupported selector for probing: {$selector}");
        }

        $attribute = str_replace('\\:', ':', $parts['name']);
        $value = $parts['value'];

        // `@wire:key` would be read as a namespace prefix, and wire/x-on attributes are not bound to
        // one, so the attribute is matched by literal name instead.
        $test = $parts['operator'] === '*'
            ? sprintf('contains(., %s)', self::xpathLiteral($value))
            : sprintf('starts-with(., %s)', self::xpathLiteral($value));

        $query = sprintf('//*[@*[name()=%s][%s]]', self::xpathLiteral($attribute), $test);

        return self::xpath($html)->query($query)?->count() ?? 0;
    }

    private static function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        // Filament emits HTML5 and unbound Alpine attributes; libxml complains about both and neither
        // affects attribute lookup, so the errors are collected and dropped rather than surfaced.
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return new DOMXPath($document);
    }

    /** XPath 1.0 has no escape character, so a literal containing a quote has to be concat()-ed. */
    private static function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        $parts = array_map(
            fn (string $chunk): string => "'{$chunk}'",
            explode("'", $value),
        );

        return 'concat(' . implode(', "\'", ', $parts) . ')';
    }
}
