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
 * Output of the table of contents engine.
 */
final class TocResult
{
    /**
     * @param   string        $html       Original content HTML.
     * @param   ScanResult    $scan       Scan result.
     * @param   Options       $options    Options after the content tag overrides.
     * @param   Heading[]     $processed  Processed headings in source order.
     * @param   IndexEntry[]  $entries    Root entries of the index.
     * @param   int           $count      Number of linked index entries (titles excluded).
     */
    public function __construct(
        public readonly string $html,
        public readonly ScanResult $scan,
        public readonly Options $options,
        public readonly array $processed,
        public readonly array $entries,
        public readonly int $count
    ) {
    }

    /**
     * Returns the rewritten content.
     *
     * @param   bool  $convertHeadings  False to only remove the content tags.
     *
     * @return  string
     */
    public function rewrite(bool $convertHeadings = true): string
    {
        return (new ContentRewriter($this->options))->rewrite($this->html, $convertHeadings ? $this->processed : [], $this->scan);
    }

    /**
     * Data used by the client side fallback when the server side rewrite cannot be applied.
     *
     * @return  array<int, array{level: int, text: string, id: string}>
     */
    public function fallbackData(): array
    {
        $data = [];

        foreach ($this->processed as $heading) {
            $data[] = [
                'level' => $heading->level,
                'text'  => $heading->text,
                'id'    => $heading->id,
            ];
        }

        return $data;
    }
}
