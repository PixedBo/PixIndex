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
 * Builds the heading hierarchy, the hierarchical numbers and the index tree.
 */
final class TocBuilder
{
    /** Inline tags kept in the index labels when HTML is not removed. */
    private const ALLOWED_TAGS = '<em><strong><b><i><u><s><code><small><sup><sub><mark><span><abbr>';

    /**
     * @param   Options  $options  Engine options.
     */
    public function __construct(private readonly Options $options)
    {
    }

    /**
     * Sets depth, parent and number of every processed heading.
     *
     * Skipped levels are normalized: an h4 right after an h2 becomes its direct child.
     *
     * @param   Heading[]  $headings  Processed headings in source order.
     *
     * @return  void
     */
    public function buildHierarchy(array $headings): void
    {
        $stack    = [];
        $counters = [];

        foreach ($headings as $heading) {
            while ($stack !== [] && end($stack)->level >= $heading->level) {
                array_pop($stack);
            }

            $heading->parent = $stack === [] ? null : end($stack);
            $heading->depth  = \count($stack) + 1;
            $stack[]         = $heading;

            // Titles are part of the tree but never numbered.
            if ($heading->isTitle) {
                continue;
            }

            $depth    = $heading->depth;
            $counters = \array_slice($counters, 0, $depth);

            for ($i = 0; $i < $depth - 1; $i++) {
                $counters[$i] ??= 0;
            }

            $counters[$depth - 1] = ($counters[$depth - 1] ?? 0) + 1;
            ksort($counters);

            $heading->numberParts = array_values($counters);
            $heading->number      = $this->formatNumber($heading->numberParts);
        }
    }

    /**
     * Builds the index tree from the indexed headings.
     *
     * @param   Heading[]  $headings  Indexed headings in source order.
     *
     * @return  IndexEntry[]  Root entries.
     */
    public function buildIndex(array $headings): array
    {
        $roots = [];
        /** @var IndexEntry[] $stack */
        $stack = [];

        foreach ($headings as $heading) {
            while ($stack !== [] && end($stack)->heading->level >= $heading->level) {
                array_pop($stack);
            }

            [$label, $fullText] = $this->label($heading);

            $entry = new IndexEntry($heading, \count($stack) + 1, $label, $fullText);

            if ($stack === []) {
                $roots[] = $entry;
            } else {
                end($stack)->children[] = $entry;
            }

            $stack[] = $entry;
        }

        return $roots;
    }

    /**
     * Formats a single counter with an ordered list style.
     *
     * @param   int     $value  Counter value (1 based).
     * @param   string  $style  Style (1, a, A, i, I).
     *
     * @return  string
     */
    public static function formatCounter(int $value, string $style): string
    {
        return match ($style) {
            'A'     => self::toAlpha($value),
            'a'     => strtolower(self::toAlpha($value)),
            'I'     => self::toRoman($value),
            'i'     => strtolower(self::toRoman($value)),
            default => (string) $value,
        };
    }

    /**
     * Formats the counters of a heading (e.g. [1, 2] => "1.2.").
     *
     * Unordered depths use decimal numbers.
     *
     * @param   int[]  $parts  Counters.
     *
     * @return  string
     */
    private function formatNumber(array $parts): string
    {
        $formatted = [];

        foreach ($parts as $index => $value) {
            if ($value < 1) {
                continue;
            }

            $depth       = $index + 1;
            $style       = $this->options->isOrdered($depth) ? $this->options->orderedStyle($depth) : '1';
            $formatted[] = self::formatCounter($value, $style);
        }

        return $formatted === [] ? '' : implode('.', $formatted) . $this->options->numberSeparator;
    }

    /**
     * Shortens a text to a number of characters, adding an ellipsis.
     *
     * With whole words the cut moves forward to the end of the word reaching the
     * limit, so no word is ever cut in half.
     *
     * @param   string  $text        Plain text.
     * @param   int     $length      Maximum number of characters (0 = no limit).
     * @param   bool    $wholeWords  Never cut a word.
     *
     * @return  ?string  Shortened text, or null when the text does not need it.
     */
    public static function truncate(string $text, int $length, bool $wholeWords): ?string
    {
        if ($length <= 0 || mb_strlen($text, 'UTF-8') <= $length) {
            return null;
        }

        $cut = mb_substr($text, 0, $length, 'UTF-8');

        if ($wholeWords) {
            // Complete the word the limit falls in (nothing to add when it falls on a space).
            preg_match('/^\S*/u', mb_substr($text, $length, null, 'UTF-8'), $match);
            $cut .= $match[0] ?? '';

            if (mb_strlen($cut, 'UTF-8') >= mb_strlen($text, 'UTF-8')) {
                return null;
            }
        }

        // No dangling spaces or punctuation before the ellipsis.
        $cut = preg_replace('/[\s,;:.\-\x{2013}\x{2014}]+$/u', '', $cut) ?? $cut;

        return $cut . '…';
    }

    /**
     * Builds the label of an index entry.
     *
     * @param   Heading  $heading  Heading.
     *
     * @return  array{0: string, 1: string}  Label (escaped text or sanitized inline HTML) and,
     *                                        when the label is truncated, the full plain text.
     */
    private function label(Heading $heading): array
    {
        $text = TextSanitizer::clean($heading->text, $this->options->sanitize);

        // A heading made only of emoji keeps its original text rather than an empty entry.
        if ($text === '') {
            $text = $heading->text;
        }

        $short = self::truncate($text, $this->options->truncateLength, $this->options->truncateWholeWords);

        // A truncated entry is plain text: cutting HTML could break the markup.
        if ($short !== null) {
            return [htmlspecialchars($short, ENT_QUOTES, 'UTF-8'), $text];
        }

        if ($this->options->removeHtml) {
            return [htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), ''];
        }

        $html = preg_replace('#<br\s*/?>#i', ' ', $heading->innerHtml) ?? $heading->innerHtml;
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Drop every attribute from the kept tags.
        $html = preg_replace('#<([a-z]+)\b[^>]*>#i', '<$1>', $html) ?? $html;
        $html = TextSanitizer::cleanHtml($html, $this->options->sanitize);

        // Empty inline tags may remain once their emoji are gone.
        $html = trim(preg_replace('#<([a-z]+)>\s*</\1>#i', '', $html) ?? $html);

        return [trim(strip_tags($html)) === '' ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $html, ''];
    }

    /**
     * Converts a number to letters (1 => A, 27 => AA).
     *
     * @param   int  $value  Number.
     *
     * @return  string
     */
    private static function toAlpha(int $value): string
    {
        $result = '';

        while ($value > 0) {
            $value--;
            $result = \chr(65 + ($value % 26)) . $result;
            $value  = intdiv($value, 26);
        }

        return $result;
    }

    /**
     * Converts a number to roman numerals.
     *
     * @param   int  $value  Number.
     *
     * @return  string
     */
    private static function toRoman(int $value): string
    {
        $map    = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $result = '';

        foreach ($map as $roman => $number) {
            while ($value >= $number) {
                $result .= $roman;
                $value  -= $number;
            }
        }

        return $result;
    }
}
