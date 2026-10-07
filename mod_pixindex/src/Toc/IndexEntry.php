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
 * A node of the index tree, ready to be rendered by the layouts.
 */
final class IndexEntry
{
    /** @var IndexEntry[] Child entries. */
    public array $children = [];

    /**
     * @param   Heading  $heading   Related content heading.
     * @param   int      $depth     Depth inside the index (1 based).
     * @param   string   $label     Entry label, already escaped or sanitized HTML.
     * @param   string   $fullText  Full plain text when the label is truncated, '' otherwise.
     */
    public function __construct(
        public readonly Heading $heading,
        public readonly int $depth,
        public readonly string $label,
        public readonly string $fullText = ''
    ) {
    }

    /**
     * Anchor of the related heading.
     *
     * @return  string
     */
    public function id(): string
    {
        return $this->heading->id;
    }

    /**
     * Hierarchical number of the related heading (e.g. "1.2.").
     *
     * @return  string
     */
    public function number(): string
    {
        return $this->heading->number;
    }

    /**
     * Whether the entry is shown as a title instead of a link.
     *
     * @return  bool
     */
    public function isTitle(): bool
    {
        return $this->heading->isTitle;
    }

    /**
     * Full plain text of the label (used in accessible labels), never truncated.
     *
     * @return  string
     */
    public function plainLabel(): string
    {
        if ($this->fullText !== '') {
            return $this->fullText;
        }

        return trim(html_entity_decode(strip_tags($this->label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
