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
 * Everything the scanner found in the content.
 */
final class ScanResult
{
    /** @var Heading[] Headings outside scripts, styles, textareas and comments, in source order. */
    public array $headings = [];

    /** @var array<int, array{0: int, 1: int}> Byte ranges (offset, length) of the content tags to remove. */
    public array $tagRemovals = [];

    /** @var array<int, array{0: int, 1: int}> Byte ranges (start, end) where headings are ignored. */
    public array $excludedRanges = [];

    /** @var array<int, array{0: int, 1: int}> Content area ranges: when not empty, headings outside them are ignored. */
    public array $includedRanges = [];

    /** Whether a regular expression failed: the content must then be left untouched. */
    public bool $failed = false;

    /** Start of the selected index section (byte offset). */
    public int $scopeStart = 0;

    /** End of the selected index section (byte offset). */
    public int $scopeEnd = 0;

    /** @var array<string, string> Attributes of the selected index tag. */
    public array $overrides = [];

    /** @var array<string, true> Ids already used in the content. */
    public array $existingIds = [];

    /**
     * Tells whether an offset falls inside the selected section, inside the content
     * area (when one was found) and outside every excluded range.
     *
     * @param   int  $offset  Byte offset.
     *
     * @return  bool
     */
    public function isIncluded(int $offset): bool
    {
        if ($offset < $this->scopeStart || $offset >= $this->scopeEnd) {
            return false;
        }

        if ($this->includedRanges !== []) {
            $inside = false;

            foreach ($this->includedRanges as [$start, $end]) {
                if ($offset >= $start && $offset < $end) {
                    $inside = true;
                    break;
                }
            }

            if (!$inside) {
                return false;
            }
        }

        foreach ($this->excludedRanges as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return false;
            }
        }

        return true;
    }
}
