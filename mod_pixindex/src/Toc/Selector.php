<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pixed\Module\Pixindex\Site\Toc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A simple CSS selector matching a single element: tag name, #id, .class and
 * [attribute] / [attribute="value"], in any combination (e.g. div#main.text).
 *
 * Combinators (spaces, >, +, ~) and pseudo-classes are not supported.
 */
final class Selector
{
    /** Valid simple selector: optional tag followed by ids, classes and attributes. */
    private const PATTERN = '/^([a-z][a-z0-9-]*|\*)?((?:#[\w-]+|\.[\w-]+|\[\s*[\w:-]+\s*(?:=\s*(?:"[^"]*"|\'[^\']*\'|[^\]\s]+)\s*)?\])*)$/iu';

    /** One part of the selector after the tag name. */
    private const PART_PATTERN = '/#([\w-]+)|\.([\w-]+)|\[\s*([\w:-]+)\s*(?:=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]+))\s*)?\]/u';

    /**
     * @param   string                                    $tag         Tag name ('' = any).
     * @param   ?string                                   $id          Required id.
     * @param   string[]                                  $classes     Required classes.
     * @param   array<int, array{0: string, 1: ?string}>  $attributes  Required attributes (name, value or null for any value).
     */
    private function __construct(
        public readonly string $tag,
        public readonly ?string $id,
        public readonly array $classes,
        public readonly array $attributes
    ) {
    }

    /**
     * Parses a simple selector.
     *
     * @param   string  $selector  Selector, e.g. "#main", ".text", "div.text", "[data-toc]".
     *
     * @return  ?self  Null when the selector is empty or not supported.
     */
    public static function parse(string $selector): ?self
    {
        $selector = trim($selector);

        if ($selector === '' || !preg_match(self::PATTERN, $selector, $match)) {
            return null;
        }

        $tag  = strtolower($match[1] ?? '');
        $tag  = $tag === '*' ? '' : $tag;
        $rest = $match[2] ?? '';

        if ($tag === '' && $rest === '') {
            return null;
        }

        $id         = null;
        $classes    = [];
        $attributes = [];

        preg_match_all(self::PART_PATTERN, $rest, $parts, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($parts as $part) {
            if ($part[1] !== null) {
                $id = $part[1];
            } elseif ($part[2] !== null) {
                $classes[] = $part[2];
            } else {
                $attributes[] = [strtolower($part[3]), $part[4] ?? $part[5] ?? $part[6] ?? null];
            }
        }

        return new self($tag, $id, $classes, $attributes);
    }

    /**
     * Parses a comma separated list of selectors, skipping the unsupported ones.
     *
     * @param   string[]  $selectors  Selectors.
     *
     * @return  self[]
     */
    public static function parseList(array $selectors): array
    {
        return array_values(array_filter(array_map([self::class, 'parse'], $selectors)));
    }

    /**
     * Cheap test on the raw tag, before parsing its attributes.
     *
     * @param   string  $tag     Lowercase tag name.
     * @param   string  $source  Raw attribute string of the tag.
     *
     * @return  bool  False when the element surely does not match.
     */
    public function mayMatch(string $tag, string $source): bool
    {
        if ($this->tag !== '' && $this->tag !== $tag) {
            return false;
        }

        if ($this->id !== null) {
            return str_contains($source, $this->id);
        }

        if ($this->classes !== []) {
            return str_contains($source, $this->classes[0]);
        }

        if ($this->attributes !== []) {
            return stripos($source, $this->attributes[0][0]) !== false;
        }

        return true;
    }

    /**
     * Tells whether an element matches the selector.
     *
     * @param   string                                                          $tag         Lowercase tag name.
     * @param   array<int, array{name: string, value: ?string, quote: string}>  $attributes  Parsed attributes (see ContentScanner::parseAttributes()).
     *
     * @return  bool
     */
    public function matches(string $tag, array $attributes): bool
    {
        if ($this->tag !== '' && $this->tag !== $tag) {
            return false;
        }

        $values = [];

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute['name']);

            // The first occurrence wins, like in browsers.
            if (!\array_key_exists($name, $values)) {
                $values[$name] = $attribute['value'] === null ? '' : html_entity_decode($attribute['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if ($this->id !== null && ($values['id'] ?? null) !== $this->id) {
            return false;
        }

        if ($this->classes !== []) {
            $classes = preg_split('/\s+/', trim($values['class'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (array_diff($this->classes, $classes) !== []) {
                return false;
            }
        }

        foreach ($this->attributes as [$name, $value]) {
            if (!\array_key_exists($name, $values) || ($value !== null && $values[$name] !== $value)) {
                return false;
            }
        }

        return true;
    }
}
