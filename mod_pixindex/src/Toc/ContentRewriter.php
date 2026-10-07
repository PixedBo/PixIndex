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
 * Writes ids, classes and back links into the content headings and removes the content tags.
 */
final class ContentRewriter
{
    /**
     * @param   Options  $options  Engine options.
     */
    public function __construct(private readonly Options $options)
    {
    }

    /**
     * Rewrites the content.
     *
     * @param   string      $html      Original content HTML.
     * @param   Heading[]   $headings  Headings to convert (an empty array only removes the tags).
     * @param   ScanResult  $scan      Scan result of the same HTML.
     *
     * @return  string
     */
    public function rewrite(string $html, array $headings, ScanResult $scan): string
    {
        $edits = [];

        foreach ($headings as $heading) {
            $edits[] = [$heading->offset, $heading->length(), $this->renderHeading($heading, $this->hasBackLinkBefore($html, $heading->offset))];
        }

        foreach ($scan->tagRemovals as [$offset, $length]) {
            if (!$this->overlaps($offset, $length, $headings)) {
                $edits[] = [$offset, $length, ''];
            }
        }

        // Splice from the end so earlier offsets stay valid.
        usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        $lastStart = PHP_INT_MAX;

        foreach ($edits as [$offset, $length, $replacement]) {
            // Never apply overlapping edits.
            if ($offset + $length > $lastStart) {
                continue;
            }

            $html      = substr_replace($html, $replacement, $offset, $length);
            $lastStart = $offset;
        }

        return $html;
    }

    /**
     * Renders a converted heading.
     *
     * @param   Heading  $heading      Heading.
     * @param   bool     $hasBackLink  Whether a back link already precedes the heading (content converted before).
     *
     * @return  string
     */
    public function renderHeading(Heading $heading, bool $hasBackLink = false): string
    {
        $attributes = $heading->attributes;

        if ($heading->existingId() === null && $heading->id !== '') {
            $attributes = $this->setAttribute($attributes, 'id', htmlspecialchars($heading->id, ENT_QUOTES, 'UTF-8'));
        }

        if (trim($this->options->headingClass) !== '') {
            $classes = array_unique(array_merge($heading->classes(), preg_split('/\s+/', trim($this->options->headingClass))));

            // The existing classes come from the source and may already hold entities.
            $attributes = $this->setAttribute($attributes, 'class', htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8', false));
        }

        $before = '';

        if ($heading->backLink && !$hasBackLink && $this->options->backLinkTarget !== '') {
            $class  = trim('pixindex-back ' . $this->options->backLinkClass);
            $before = '<p class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><a href="#'
                . htmlspecialchars($this->options->backLinkTarget, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($this->options->backLinkLabel, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }

        return $before . '<h' . $heading->level . $this->renderAttributes($attributes) . '>' . $heading->innerHtml . '</h' . $heading->level . '>';
    }

    /**
     * Sets (or replaces) an attribute, keeping the source order.
     *
     * @param   array<int, array{name: string, value: ?string, quote: string}>  $attributes  Attributes.
     * @param   string                                                           $name        Attribute name.
     * @param   string                                                           $value       Already escaped value.
     *
     * @return  array<int, array{name: string, value: ?string, quote: string}>
     */
    private function setAttribute(array $attributes, string $name, string $value): array
    {
        foreach ($attributes as $index => $attribute) {
            if (strcasecmp($attribute['name'], $name) === 0) {
                $attributes[$index]['value'] = $value;
                $attributes[$index]['quote'] = '"';

                return $attributes;
            }
        }

        $attributes[] = ['name' => $name, 'value' => $value, 'quote' => '"'];

        return $attributes;
    }

    /**
     * Serializes attributes, keeping the source values untouched.
     *
     * @param   array<int, array{name: string, value: ?string, quote: string}>  $attributes  Attributes.
     *
     * @return  string
     */
    private function renderAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $attribute) {
            if ($attribute['value'] === null) {
                $html .= ' ' . $attribute['name'];

                continue;
            }

            $quote = $attribute['quote'] === "'" ? "'" : '"';
            $html .= ' ' . $attribute['name'] . '=' . $quote . $attribute['value'] . $quote;
        }

        return $html;
    }

    /**
     * Tells whether a range overlaps one of the headings.
     *
     * @param   int        $offset    Range offset.
     * @param   int        $length    Range length.
     * @param   Heading[]  $headings  Headings.
     *
     * @return  bool
     */
    private function overlaps(int $offset, int $length, array $headings): bool
    {
        foreach ($headings as $heading) {
            if ($offset < $heading->offset + $heading->length() && $heading->offset < $offset + $length) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a back link written by this module sits right before an offset.
     *
     * Happens when the content was already converted (e.g. restored from a cache).
     *
     * @param   string  $html    Content HTML.
     * @param   int     $offset  Heading offset.
     *
     * @return  bool
     */
    private function hasBackLinkBefore(string $html, int $offset): bool
    {
        $before = substr($html, max(0, $offset - 300), min(300, $offset));

        return (bool) preg_match('~<p class="pixindex-back[^"]*"><a [^>]*>[^<]*</a></p>\s*$~', $before);
    }
}
