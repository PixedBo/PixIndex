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
 * Value object holding every setting used by the table of contents engine.
 *
 * The object is framework agnostic: the module helper fills it from the module
 * parameters and the content tag may override part of it for a single page.
 */
final class Options
{
    /** Allowed ordered list styles, keyed by every alias accepted in the content tag. */
    private const STYLE_ALIASES = [
        '1'                 => '1',
        'numbers'           => '1',
        'decimal'           => '1',
        'a'                 => 'a',
        'letters_lowercase' => 'a',
        'lower-alpha'       => 'a',
        'A'                 => 'A',
        'letters'           => 'A',
        'upper-alpha'       => 'A',
        'i'                 => 'i',
        'roman_lowercase'   => 'i',
        'lower-roman'       => 'i',
        'I'                 => 'I',
        'roman'             => 'I',
        'upper-roman'       => 'I',
    ];

    /** Lowest heading level included in the index (1-6). */
    public int $indexMin = 2;

    /** Highest heading level included in the index (1-6). */
    public int $indexMax = 6;

    /** @var int[] Heading levels always ignored, whatever the ranges (e.g. [1] skips every h1). */
    public array $excludedLevels = [];

    /** Cleaning applied to the index labels and ids: none, emoji or strict (see TextSanitizer). */
    public string $sanitize = 'emoji';

    /** @var array<int, bool> Ordered list flag per depth (1-6). */
    public array $ordered = [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true];

    /** @var array<int, string> Ordered list style per depth (1, a, A, i, I). */
    public array $orderedStyles = [1 => '1', 2 => '1', 3 => '1', 4 => '1', 5 => '1', 6 => '1'];


    /** Character placed after the generated number. */
    public string $numberSeparator = '.';


    /** Prepend the generated number to the ids. */
    public bool $numberInIds = false;

    /** Prepend the parent headings to the ids. */
    public bool $parentsInIds = false;

    /** Prefix added to every generated id. */
    public string $idPrefix = '';

    /** Prefix added to generated ids starting with a digit ('' = disabled). */
    public string $numericIdPrefix = 'h-';

    /** @var string[] Words removed from the text before building the ids. */
    public array $removeWords = [];

    /** Strip every HTML tag from the index entries text. */
    public bool $removeHtml = true;

    /** @var string[] Headings having one of these classes are ignored. */
    public array $noIndexClasses = ['noindex', 'pixindex-skip'];

    /** @var array<int, array{0: string, 1: ?string}> Headings having one of these attributes are ignored. */
    public array $noIndexAttributes = [];

    /** @var string[] Headings inside elements matching one of these selectors (e.g. .page-header) are ignored. */
    public array $noIndexContainers = [];

    /** @var string[] Only headings inside elements matching one of these selectors (e.g. #main) are indexed, when at least one exists. */
    public array $contentContainers = [];

    /** Maximum number of characters of an index entry (0 = no limit). */
    public int $truncateLength = 0;

    /** Never cut a word: the ellipsis comes at the end of the word reaching the limit. */
    public bool $truncateWholeWords = true;

    /** @var string[] Headings having one of these classes are shown as titles in the index. */
    public array $titleClasses = [];

    /** Word used by the index tag. */
    public string $tagWord = 'pixindex';

    /** Word used by the no-index tag. */
    public string $noIndexWord = 'pixindex-skip';

    /** Opening characters of the content tags. */
    public string $tagOpen = '{';

    /** Closing characters of the content tags. */
    public string $tagClose = '}';

    /** Extra classes added to the processed content headings. */
    public string $headingClass = '';


    /** Id of the index element targeted by the back links ('' = no back links). */
    public string $backLinkTarget = '';

    /** Text of the back links. */
    public string $backLinkLabel = 'Back to index';

    /** Extra classes added to the back link wrapper. */
    public string $backLinkClass = '';

    /** Title override coming from the content tag (null = no override). */
    public ?string $titleOverride = null;

    /**
     * Returns a copy of the options with the overrides found in the content tag.
     *
     * Supported attributes: min, max, exclude, ordered, ordered-N, ordered-style,
     * ordered-style-N, sanitize and title.
     *
     * @param   array<string, string>  $attributes  Attributes of the index tag.
     *
     * @return  self
     */
    public function withOverrides(array $attributes): self
    {
        $clone = clone $this;

        if ($attributes === []) {
            return $clone;
        }

        $attributes = array_change_key_case($attributes, CASE_LOWER);

        if (isset($attributes['min'])) {
            $clone->indexMin = self::clampLevel($attributes['min'], $clone->indexMin);
        }

        if (isset($attributes['max'])) {
            $clone->indexMax = self::clampLevel($attributes['max'], $clone->indexMax);
        }

        if (isset($attributes['exclude'])) {
            $clone->excludedLevels = array_map(
                static fn (string $level): int => self::clampLevel($level, 0),
                explode(',', $attributes['exclude'])
            );
        }

        if (isset($attributes['sanitize'])) {
            $clone->sanitize = strtolower(trim($attributes['sanitize']));
        }

        if (isset($attributes['ordered'])) {
            $values = array_map('trim', explode(',', $attributes['ordered']));

            if (\count($values) === 1) {
                $clone->ordered = array_fill(1, 6, self::toBool($values[0]));
            } else {
                foreach ($values as $index => $value) {
                    if ($index < 6 && $value !== '') {
                        $clone->ordered[$index + 1] = self::toBool($value);
                    }
                }
            }
        }

        if (isset($attributes['ordered-style'])) {
            $values = array_map('trim', explode(',', $attributes['ordered-style']));

            if (\count($values) === 1) {
                $clone->orderedStyles = array_fill(1, 6, self::normalizeStyle($values[0]));
            } else {
                foreach ($values as $index => $value) {
                    if ($index < 6 && $value !== '') {
                        $clone->orderedStyles[$index + 1] = self::normalizeStyle($value);
                    }
                }
            }
        }

        for ($depth = 1; $depth <= 6; $depth++) {
            if (isset($attributes['ordered-' . $depth])) {
                $clone->ordered[$depth] = self::toBool($attributes['ordered-' . $depth]);
            }

            if (isset($attributes['ordered-style-' . $depth])) {
                $clone->orderedStyles[$depth] = self::normalizeStyle($attributes['ordered-style-' . $depth]);
            }
        }


        if (isset($attributes['title'])) {
            $clone->titleOverride = $attributes['title'];
        }

        $clone->normalize();

        return $clone;
    }

    /**
     * Fixes inconsistent values (swapped ranges, invalid styles).
     *
     * @return  void
     */
    public function normalize(): void
    {
        $this->indexMin = self::clampLevel($this->indexMin, 1);
        $this->indexMax = self::clampLevel($this->indexMax, 6);

        if ($this->indexMin > $this->indexMax) {
            [$this->indexMin, $this->indexMax] = [$this->indexMax, $this->indexMin];
        }

        for ($depth = 1; $depth <= 6; $depth++) {
            $this->ordered[$depth]       = (bool) ($this->ordered[$depth] ?? false);
            $this->orderedStyles[$depth] = self::normalizeStyle((string) ($this->orderedStyles[$depth] ?? '1'));
        }


        if (!\in_array($this->sanitize, ['none', 'emoji', 'strict'], true)) {
            $this->sanitize = 'emoji';
        }

        $this->excludedLevels = array_values(array_unique(array_filter(
            array_map('intval', $this->excludedLevels),
            static fn (int $level): bool => $level >= 1 && $level <= 6
        )));

    }

    /**
     * Tells whether a heading level is included in the index.
     *
     * @param   int  $level  Heading level (1-6).
     *
     * @return  bool
     */
    public function isIndexLevel(int $level): bool
    {
        return $level >= $this->indexMin && $level <= $this->indexMax && !\in_array($level, $this->excludedLevels, true);
    }

    /**
     * Returns the ordered flag for a depth (depths deeper than 6 use level 6).
     *
     * @param   int  $depth  List depth (1 based).
     *
     * @return  bool
     */
    public function isOrdered(int $depth): bool
    {
        return $this->ordered[max(1, min(6, $depth))];
    }

    /**
     * Returns the ordered list style for a depth (depths deeper than 6 use level 6).
     *
     * @param   int  $depth  List depth (1 based).
     *
     * @return  string
     */
    public function orderedStyle(int $depth): string
    {
        return $this->orderedStyles[max(1, min(6, $depth))];
    }

    /**
     * Converts any accepted style alias to one of 1, a, A, i, I.
     *
     * @param   string  $style  Style or alias.
     *
     * @return  string
     */
    public static function normalizeStyle(string $style): string
    {
        $style = trim($style);

        if (isset(self::STYLE_ALIASES[$style])) {
            return self::STYLE_ALIASES[$style];
        }

        $lower = strtolower($style);

        return self::STYLE_ALIASES[$lower] ?? '1';
    }

    /**
     * Converts a mixed value to a heading level between 1 and 6.
     *
     * @param   mixed  $value     Raw value (h2, 2, ...).
     * @param   int    $fallback  Value used when the input is invalid.
     *
     * @return  int
     */
    private static function clampLevel(mixed $value, int $fallback): int
    {
        $level = (int) ltrim(strtolower((string) $value), 'h');

        return $level >= 1 && $level <= 6 ? $level : $fallback;
    }

    /**
     * Converts a tag attribute value to a boolean.
     *
     * @param   string  $value  Raw value.
     *
     * @return  bool
     */
    private static function toBool(string $value): bool
    {
        return \in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
