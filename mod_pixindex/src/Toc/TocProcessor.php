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
 * Entry point of the table of contents engine.
 *
 * The engine has no Joomla dependency: it receives HTML and options and returns
 * the index tree plus the rewritten HTML.
 */
final class TocProcessor
{
    /** Larger component outputs (5 MB) are left untouched. */
    public const MAX_CONTENT_LENGTH = 5242880;

    /** @var ?callable(string): string */
    private $slugger;

    /**
     * @param   Options    $options  Engine options.
     * @param   ?callable  $slugger  Converts a text to a URL safe slug (null = built-in slugger).
     */
    public function __construct(private readonly Options $options, ?callable $slugger = null)
    {
        $this->slugger = $slugger;
    }

    /**
     * Processes an HTML fragment.
     *
     * Oversized content, or content a regular expression cannot handle, is
     * returned untouched with an empty index.
     *
     * @param   string  $html  Content HTML.
     *
     * @return  TocResult
     */
    public function process(string $html): TocResult
    {
        $scan = \strlen($html) <= self::MAX_CONTENT_LENGTH ? (new ContentScanner())->scan($html, $this->options) : null;

        if ($scan === null || $scan->failed) {
            return new TocResult($html, new ScanResult(), $this->options, [], [], 0);
        }

        $options  = $this->options->withOverrides($scan->overrides);
        $builder  = new TocBuilder($options);
        $headings = [];

        foreach ($scan->headings as $heading) {
            if (
                $heading->text === ''
                || !$options->isIndexLevel($heading->level)
                || !$scan->isIncluded($heading->offset)
                || $this->isExcluded($heading, $options)
            ) {
                continue;
            }

            $heading->processed = true;
            $heading->indexed   = true;
            $heading->isTitle   = array_intersect($heading->classes(), $options->titleClasses) !== [];

            $headings[] = $heading;
        }

        $builder->buildHierarchy($headings);

        $ids = new IdGenerator($options, $this->slugger, $scan->existingIds);

        foreach ($headings as $heading) {
            $ids->assign($heading);
        }

        if ($options->backLinkTarget !== '') {
            $first = true;

            foreach ($headings as $heading) {
                if ($heading->isTitle) {
                    continue;
                }

                $heading->backLink = !$first;
                $first             = false;
            }
        }

        $count = \count(array_filter($headings, static fn (Heading $heading): bool => !$heading->isTitle));

        return new TocResult($html, $scan, $options, $headings, $builder->buildIndex($headings), $count);
    }

    /**
     * Tells whether a heading is excluded by its classes or attributes.
     *
     * @param   Heading  $heading  Heading.
     * @param   Options  $options  Engine options.
     *
     * @return  bool
     */
    private function isExcluded(Heading $heading, Options $options): bool
    {
        if (array_intersect($heading->classes(), $options->noIndexClasses) !== []) {
            return true;
        }

        foreach ($options->noIndexAttributes as [$name, $value]) {
            if ($heading->hasAttribute($name, $value)) {
                return true;
            }
        }

        return false;
    }
}
