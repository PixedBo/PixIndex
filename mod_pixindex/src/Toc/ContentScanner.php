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
 * Finds headings, content tags and excluded areas inside an HTML fragment.
 *
 * The HTML is never re-serialized: everything is located by byte offset so the
 * rewriter can splice the changes into the original markup untouched.
 */
final class ContentScanner
{
    /** Matches a heading tag with its content. */
    private const HEADING_PATTERN = '#<h([1-6])(\s[^>]*)?>(.*?)</h\1\s*>#is';

    /** Matches areas where headings and tags must never be touched (code samples included). */
    private const MASK_PATTERN = '#<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>'
        . '|<pre\b.*?</pre\s*>|<code\b.*?</code\s*>|<!--.*?-->#is';

    /** Matches a single HTML attribute. */
    private const ATTRIBUTE_PATTERN = '#([^\s=/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?#';


    /** Elements without content: they can never contain headings. */
    private const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /** Whitespace allowed around a tag that sits alone in a paragraph. */
    private const BLANK = '(?:\s|&nbsp;|&#160;|<br\s*/?>)*';

    /** Set when a regular expression fails (backtrack limit, invalid UTF-8...). */
    private bool $failed = false;

    /**
     * Scans the content.
     *
     * @param   string   $html     Content HTML.
     * @param   Options  $options  Engine options.
     *
     * @return  ScanResult
     */
    public function scan(string $html, Options $options): ScanResult
    {
        $this->failed = false;

        $result           = new ScanResult();
        $result->scopeEnd = \strlen($html);

        $masked = $this->findMaskedRanges($html);

        $this->scanTags($html, $options, $masked, $result);

        // Never index the title of another index already printed inside the content.
        $excluded = $this->findContainerRanges($html, array_unique([...$options->noIndexContainers, '.pixindex']), $masked);

        array_push($result->excludedRanges, ...$excluded);

        $result->includedRanges = $this->findContainerRanges($html, $options->contentContainers, $masked);

        $this->scanExistingIds($html, $result);

        $result->headings = $this->scanHeadings($html, $masked);
        $result->failed   = $this->failed;

        return $result;
    }

    /**
     * Parses the attributes of an HTML tag.
     *
     * @param   string  $source  Attribute string (e.g. ' class="a" id="b"').
     *
     * @return  array<int, array{name: string, value: ?string, quote: string}>
     */
    public static function parseAttributes(string $source): array
    {
        $attributes = [];

        if (trim($source) === '' || !preg_match_all(self::ATTRIBUTE_PATTERN, $source, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
            return $attributes;
        }

        foreach ($matches as $match) {
            if ($match[2] !== null) {
                $attributes[] = ['name' => $match[1], 'value' => $match[2], 'quote' => '"'];
            } elseif (($match[3] ?? null) !== null) {
                $attributes[] = ['name' => $match[1], 'value' => $match[3], 'quote' => "'"];
            } elseif (($match[4] ?? null) !== null) {
                $attributes[] = ['name' => $match[1], 'value' => $match[4], 'quote' => ''];
            } else {
                $attributes[] = ['name' => $match[1], 'value' => null, 'quote' => ''];
            }
        }

        return $attributes;
    }

    /**
     * Converts an HTML fragment to normalized plain text.
     *
     * @param   string  $html  HTML fragment.
     *
     * @return  string
     */
    public static function toText(string $html): string
    {
        $html = preg_replace('#<br\s*/?>#i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Runs preg_match_all and records failures instead of silently returning nothing.
     *
     * @param   string  $pattern  Pattern.
     * @param   string  $subject  Subject.
     * @param   int     $flags    preg_match_all flags.
     * @param   int     $offset   Start offset.
     *
     * @return  array
     */
    private function matchAll(string $pattern, string $subject, int $flags, int $offset = 0): array
    {
        $count = preg_match_all($pattern, $subject, $matches, $flags, $offset);

        if ($count === false) {
            $this->failed = true;

            return [];
        }

        return $count ? $matches : [];
    }

    /**
     * Finds scripts, styles, textareas, code samples and comments.
     *
     * @param   string  $html  Content HTML.
     *
     * @return  array<int, array{0: int, 1: int}>
     */
    private function findMaskedRanges(string $html): array
    {
        $ranges = [];

        foreach ($this->matchAll(self::MASK_PATTERN, $html, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)[0] ?? [] as [$source, $offset]) {
            $ranges[] = [$offset, $offset + \strlen($source)];
        }

        return $ranges;
    }

    /**
     * Tells whether an offset is inside one of the given ranges.
     *
     * @param   int                                 $offset  Byte offset.
     * @param   array<int, array{0: int, 1: int}>   $ranges  Ranges (start, end).
     *
     * @return  bool
     */
    private function inRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether an offset falls inside an HTML tag (e.g. in an attribute value).
     *
     * @param   string  $html    Content HTML.
     * @param   int     $offset  Byte offset.
     *
     * @return  bool
     */
    private function isInsideTag(string $html, int $offset): bool
    {
        if ($offset === 0) {
            return false;
        }

        // Search backwards from the offset, without copying the string.
        $lastOpen  = strrpos($html, '<', $offset - \strlen($html) - 1);
        $lastClose = strrpos($html, '>', $offset - \strlen($html) - 1);

        return $lastOpen !== false && ($lastClose === false || $lastOpen > $lastClose);
    }

    /**
     * Finds the content tags, the indexed section and the tags to remove.
     *
     * Only the first opening tag counts: it starts the indexed section, which ends at
     * the next closing tag (or at the end of the content).
     *
     * @param   string                              $html     Content HTML.
     * @param   Options                             $options  Engine options.
     * @param   array<int, array{0: int, 1: int}>   $masked   Masked ranges.
     * @param   ScanResult                          $result   Result being filled.
     *
     * @return  void
     */
    private function scanTags(string $html, Options $options, array $masked, ScanResult $result): void
    {
        $open    = preg_quote($options->tagOpen, '#');
        $close   = preg_quote($options->tagClose, '#');
        $index   = preg_quote($options->tagWord, '#');
        $noIndex = preg_quote($options->noIndexWord, '#');

        if ($open === '' || $close === '' || $index === '' || $noIndex === '') {
            return;
        }

        $pattern = '#' . $open . '\s*(/?)\s*(' . $index . '|' . $noIndex . ')(?=\s|&nbsp;|' . $close . ')(.*?)' . $close . '#su';
        $matches = $this->matchAll($pattern, $html, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $first       = null;
        $closes      = [];
        $noIndexOpen = null;
        $wordIndex   = mb_strtolower($options->tagWord);
        $wordNoIndex = mb_strtolower($options->noIndexWord);

        foreach ($matches as $match) {
            $offset = $match[0][1];

            // Tags in code samples or inside attribute values are left alone.
            if ($this->inRanges($offset, $masked) || $this->isInsideTag($html, $offset)) {
                continue;
            }

            $isClose = $match[1][0] === '/';
            $word    = mb_strtolower($match[2][0]);
            $end     = $offset + \strlen($match[0][0]);

            $result->tagRemovals[] = $this->expandToParagraph($html, $offset, $end);

            if ($word === $wordNoIndex && $word !== $wordIndex) {
                if (!$isClose && $noIndexOpen === null) {
                    $noIndexOpen = $offset;
                } elseif ($isClose && $noIndexOpen !== null) {
                    $result->excludedRanges[] = [$noIndexOpen, $end];
                    $noIndexOpen              = null;
                }

                continue;
            }

            if ($isClose) {
                $closes[] = $offset;
            } elseif ($first === null) {
                $first = ['offset' => $offset, 'end' => $end, 'attributes' => $this->parseTagAttributes($match[3][0])];
            }
        }

        // An unclosed no-index block runs to the end of the content.
        if ($noIndexOpen !== null) {
            $result->excludedRanges[] = [$noIndexOpen, \strlen($html)];
        }

        if ($first === null) {
            // Only a closing tag: index everything before it.
            if ($closes !== []) {
                $result->scopeEnd = min($closes);
            }

            return;
        }

        $result->scopeStart = $first['end'];
        $result->overrides  = $first['attributes'];

        foreach ($closes as $closeOffset) {
            if ($closeOffset > $first['offset']) {
                $result->scopeEnd = $closeOffset;
                break;
            }
        }
    }

    /**
     * Expands a tag range to its paragraph when the tag is the only thing inside it.
     *
     * @param   string  $html   Content HTML.
     * @param   int     $start  Tag start offset.
     * @param   int     $end    Tag end offset.
     *
     * @return  array{0: int, 1: int}  Range as (offset, length).
     */
    private function expandToParagraph(string $html, int $start, int $end): array
    {
        $before = substr($html, max(0, $start - 300), min(300, $start));
        $after  = substr($html, $end, 300);

        if (
            preg_match('~<p\b[^>]*>' . self::BLANK . '$~i', $before, $opening)
            && preg_match('~^' . self::BLANK . '</p\s*>~i', $after, $closing)
        ) {
            $start -= \strlen($opening[0]);
            $end   += \strlen($closing[0]);
        }

        return [$start, $end - $start];
    }

    /**
     * Parses the attributes written inside an index tag.
     *
     * @param   string  $source  Attribute string.
     *
     * @return  array<string, string>
     */
    private function parseTagAttributes(string $source): array
    {
        // Editors often encode quotes and spaces inside text.
        $source = html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $source = str_replace(['“', '”', '„', '″'], '"', $source);

        $attributes = [];

        foreach (self::parseAttributes($source) as $attribute) {
            $attributes[strtolower($attribute['name'])] = (string) $attribute['value'];
        }

        return $attributes;
    }

    /**
     * Finds the byte ranges of the elements matching one of the given selectors.
     *
     * @param   string                              $html       Content HTML.
     * @param   string[]                            $selectors  Simple CSS selectors (see Selector).
     * @param   array<int, array{0: int, 1: int}>   $masked     Masked ranges (scripts, comments...).
     *
     * @return  array<int, array{0: int, 1: int}>
     */
    private function findContainerRanges(string $html, array $selectors, array $masked): array
    {
        $selectors = Selector::parseList($selectors);
        $ranges    = [];

        if ($selectors === []) {
            return $ranges;
        }

        // One pass over every opening tag; attributes are parsed only for likely candidates.
        foreach ($this->matchAll('#<([a-z][a-z0-9-]*)(\s[^>]*)?>#i', $html, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) as $match) {
            $tag   = strtolower($match[1][0]);
            $start = $match[0][1];

            if (\in_array($tag, self::VOID_ELEMENTS, true) || $this->inRanges($start, $masked)) {
                continue;
            }

            $source     = $match[2][0] ?? '';
            $attributes = null;

            foreach ($selectors as $selector) {
                if (!$selector->mayMatch($tag, $source)) {
                    continue;
                }

                $attributes ??= self::parseAttributes($source);

                if ($selector->matches($tag, $attributes)) {
                    $ranges[] = [$start, $this->findClosingTag($html, $tag, $start + \strlen($match[0][0]))];
                    break;
                }
            }
        }

        return $ranges;
    }

    /**
     * Finds the end offset of the element opened right before the given offset.
     *
     * @param   string  $html    Content HTML.
     * @param   string  $tag     Tag name.
     * @param   int     $offset  Offset right after the opening tag.
     *
     * @return  int
     */
    private function findClosingTag(string $html, string $tag, int $offset): int
    {
        $depth = 1;

        foreach ($this->matchAll('#<(/?)' . preg_quote($tag, '#') . '\b[^>]*>#i', $html, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $offset) as $match) {
            $depth += $match[1][0] === '/' ? -1 : 1;

            if ($depth === 0) {
                return $match[0][1] + \strlen($match[0][0]);
            }
        }

        return \strlen($html);
    }

    /**
     * Collects the ids already used in the content.
     *
     * @param   string      $html    Content HTML.
     * @param   ScanResult  $result  Result being filled.
     *
     * @return  void
     */
    private function scanExistingIds(string $html, ScanResult $result): void
    {
        foreach ($this->matchAll('#\sid\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i', $html, PREG_SET_ORDER) as $match) {
            // Only one of the three alternatives can match.
            $id = trim(($match[1] ?? '') . ($match[2] ?? '') . ($match[3] ?? ''));

            if ($id !== '') {
                $result->existingIds[html_entity_decode($id, ENT_QUOTES | ENT_HTML5, 'UTF-8')] = true;
            }
        }
    }

    /**
     * Finds every heading outside the masked ranges.
     *
     * @param   string                              $html    Content HTML.
     * @param   array<int, array{0: int, 1: int}>   $masked  Masked ranges.
     *
     * @return  Heading[]
     */
    private function scanHeadings(string $html, array $masked): array
    {
        $headings = [];

        foreach ($this->matchAll(self::HEADING_PATTERN, $html, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) as $match) {
            $offset = $match[0][1];

            if ($this->inRanges($offset, $masked)) {
                continue;
            }

            $inner = $match[3][0];

            $headings[] = new Heading(
                (int) $match[1][0],
                $offset,
                $match[0][0],
                self::parseAttributes($match[2][0] ?? ''),
                $inner,
                self::toText($inner)
            );
        }

        return $headings;
    }
}
