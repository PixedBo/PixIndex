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
 * A heading tag found in the content.
 */
final class Heading
{
    /** Id written to the heading (existing or generated). */
    public string $id = '';

    /** Slug of the heading text, without prefixes (used for children ids). */
    public string $slug = '';

    /** Depth inside the tree of processed headings (1 based). */
    public int $depth = 0;

    /** @var int[] Raw counters of the hierarchical number (e.g. [1, 2]). */
    public array $numberParts = [];

    /** Formatted hierarchical number, separator included (e.g. "1.2."). */
    public string $number = '';


    /** Whether the heading is converted (id, classes, back link). */
    public bool $processed = false;

    /** Whether the heading appears in the index. */
    public bool $indexed = false;

    /** Whether the heading is shown as a title inside the index. */
    public bool $isTitle = false;

    /** Whether a back link is written before the heading. */
    public bool $backLink = false;

    /** Parent heading inside the tree of processed headings. */
    public ?Heading $parent = null;

    /**
     * @param   int                                                   $level       Heading level (1-6).
     * @param   int                                                   $offset      Byte offset of the tag in the content.
     * @param   string                                                $source      Full source of the tag.
     * @param   array<int, array{name: string, value: ?string, quote: string}>  $attributes  Parsed attributes, in source order.
     * @param   string                                                $innerHtml   Inner HTML of the tag.
     * @param   string                                                $text        Plain text of the tag.
     */
    public function __construct(
        public readonly int $level,
        public readonly int $offset,
        public readonly string $source,
        public readonly array $attributes,
        public readonly string $innerHtml,
        public readonly string $text
    ) {
    }

    /**
     * Returns the raw value of an attribute (null when missing).
     *
     * @param   string  $name  Attribute name (case insensitive).
     *
     * @return  ?string
     */
    public function attribute(string $name): ?string
    {
        foreach ($this->attributes as $attribute) {
            if (strcasecmp($attribute['name'], $name) === 0) {
                return $attribute['value'] ?? '';
            }
        }

        return null;
    }

    /**
     * Tells whether the heading has an attribute, optionally with a given value.
     *
     * @param   string   $name   Attribute name (case insensitive).
     * @param   ?string  $value  Expected value (null = any value).
     *
     * @return  bool
     */
    public function hasAttribute(string $name, ?string $value = null): bool
    {
        $current = $this->attribute($name);

        if ($current === null) {
            return false;
        }

        return $value === null || html_entity_decode($current, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $value;
    }

    /**
     * Returns the class names of the heading.
     *
     * @return  string[]
     */
    public function classes(): array
    {
        $class = $this->attribute('class');

        return $class === null ? [] : preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Returns the id already present in the source, if any.
     *
     * @return  ?string
     */
    public function existingId(): ?string
    {
        $id = $this->attribute('id');

        if ($id === null || trim($id) === '') {
            return null;
        }

        // The source value may hold entities (&amp;): links and data need the real id.
        return html_entity_decode(trim($id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Byte length of the tag in the content.
     *
     * @return  int
     */
    public function length(): int
    {
        return \strlen($this->source);
    }
}
