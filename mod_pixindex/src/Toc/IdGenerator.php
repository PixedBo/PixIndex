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
 * Builds unique ids for the processed headings.
 */
final class IdGenerator
{
    /** @var callable(string): string */
    private $slugger;

    /** @var array<string, true> Ids already taken. */
    private array $used;

    /**
     * @param   Options                $options      Engine options.
     * @param   ?callable              $slugger      Converts a text to a URL safe slug (null = built-in slugger).
     * @param   array<string, true>    $existingIds  Ids already used in the content.
     */
    public function __construct(private readonly Options $options, ?callable $slugger = null, array $existingIds = [])
    {
        $this->slugger = $slugger ?? [self::class, 'defaultSlug'];
        $this->used    = $existingIds;
    }

    /**
     * Assigns the id of a heading. Existing ids are kept untouched.
     *
     * @param   Heading  $heading  Processed heading (number and parent already set).
     *
     * @return  void
     */
    public function assign(Heading $heading): void
    {
        // Ids never contain emoji or symbols, whatever the label sanitize mode.
        $heading->slug = $this->slugify($this->removeWords(TextSanitizer::clean($heading->text, 'strict')));

        $existing = $heading->existingId();

        if ($existing !== null) {
            $heading->id = $existing;

            return;
        }

        $parts = [];

        if ($this->options->numberInIds && $heading->numberParts !== []) {
            $parts[] = implode('-', $heading->numberParts);
        }

        if ($this->options->parentsInIds) {
            $parents = [];

            for ($parent = $heading->parent; $parent !== null; $parent = $parent->parent) {
                if ($parent->slug !== '') {
                    array_unshift($parents, $parent->slug);
                }
            }

            array_push($parts, ...$parents);
        }

        $parts[] = $heading->slug !== '' ? $heading->slug : 'heading';

        $id = $this->options->idPrefix . implode('-', $parts);

        if ($this->options->numericIdPrefix !== '' && preg_match('/^[0-9]/', $id)) {
            $id = $this->options->numericIdPrefix . $id;
        }

        // Ids cannot contain whitespace.
        $id = preg_replace('/\s+/u', '-', $id) ?? $id;

        $heading->id = $this->unique($id);
    }

    /**
     * Built-in slugger used when no slugger is injected.
     *
     * @param   string  $text  Text.
     *
     * @return  string
     */
    public static function defaultSlug(string $text): string
    {
        // Strip accents first: iconv transliteration differs between platforms.
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);

            if ($decomposed !== false) {
                $text = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $text;
            }
        }

        if (\function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

            if ($ascii !== false) {
                $text = $ascii;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }

    /**
     * Removes the configured words from a text.
     *
     * @param   string  $text  Text.
     *
     * @return  string
     */
    private function removeWords(string $text): string
    {
        if ($this->options->removeWords === []) {
            return $text;
        }

        $words   = array_map(static fn (string $word): string => preg_quote($word, '/'), $this->options->removeWords);
        $pattern = '/(?<![\p{L}\p{N}])(?:' . implode('|', $words) . ')(?![\p{L}\p{N}])/iu';

        return preg_replace($pattern, ' ', $text) ?? $text;
    }

    /**
     * Converts a text to a slug.
     *
     * @param   string  $text  Text.
     *
     * @return  string
     */
    private function slugify(string $text): string
    {
        return trim((string) ($this->slugger)($text), '-');
    }

    /**
     * Makes an id unique by appending a counter.
     *
     * @param   string  $id  Candidate id.
     *
     * @return  string
     */
    private function unique(string $id): string
    {
        $candidate = $id;

        for ($counter = 2; isset($this->used[$candidate]); $counter++) {
            $candidate = $id . '-' . $counter;
        }

        $this->used[$candidate] = true;

        return $candidate;
    }
}
