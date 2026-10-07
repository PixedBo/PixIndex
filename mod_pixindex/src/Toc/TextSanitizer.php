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
 * Cleans heading texts before they are used as index labels or ids.
 *
 * Modes:
 * - none:   text is left untouched;
 * - emoji:  removes emoji, pictographs, icon font glyphs and invisible characters;
 * - strict: emoji mode plus every other symbol, and leading/trailing bullets,
 *           dashes and separators.
 */
final class TextSanitizer
{
    /** Emoji, pictographs and the characters used to combine them. */
    private const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23FA}'
        . '\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2934}\x{2935}\x{3030}\x{303D}\x{3297}\x{3299}'
        . '\x{FE00}-\x{FE0F}\x{20E3}\x{200D}\x{E0020}-\x{E007F}]/u';

    /** Invisible and control characters (tabs and new lines excluded), private use glyphs (icon fonts). */
    private const INVISIBLE = '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\p{Co}\x{00AD}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u';

    /** Other symbols (So): stars, check marks, arrows, box drawing... */
    private const SYMBOLS = '/\p{So}/u';

    /** Bullets, dashes and separators at the start of the text. */
    private const LEADING = '/^[\s\p{Pd}\x{2022}\x{2023}\x{2043}\x{00B7}\x{2219}|:;,*#~_>]+/u';

    /** Dashes and separators at the end of the text. */
    private const TRAILING = '/[\s\p{Pd}\x{2022}\x{00B7}|:;,*#~_]+$/u';

    /**
     * Cleans a plain text.
     *
     * @param   string  $text  Text.
     * @param   string  $mode  none, emoji or strict.
     *
     * @return  string
     */
    public static function clean(string $text, string $mode): string
    {
        if ($mode !== 'emoji' && $mode !== 'strict') {
            return $text;
        }

        $text = self::removeCharacters($text, $mode);

        if ($mode === 'strict') {
            $text = preg_replace(self::LEADING, '', $text) ?? $text;
            $text = preg_replace(self::TRAILING, '', $text) ?? $text;
        }

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }

    /**
     * Cleans the text of an HTML fragment, leaving the tags untouched.
     *
     * Only non ASCII characters are removed, so the markup cannot break.
     *
     * @param   string  $html  HTML fragment.
     * @param   string  $mode  none, emoji or strict.
     *
     * @return  string
     */
    public static function cleanHtml(string $html, string $mode): string
    {
        if ($mode !== 'emoji' && $mode !== 'strict') {
            return $html;
        }

        // Decode entities so encoded emoji (&#128512;) are removed too, but keep the markup characters encoded.
        $html = preg_replace_callback(
            '/&(?!(?:lt|gt|amp|quot|apos|#0*60|#0*62|#0*38|#0*34|#0*39|#x0*3c|#x0*3e|#x0*26|#x0*22|#x0*27);)[a-z0-9#]+;/i',
            static fn (array $match): string => html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $html
        ) ?? $html;

        $html = self::removeCharacters($html, $mode);

        return trim(preg_replace('/\s+/u', ' ', $html) ?? $html);
    }

    /**
     * Removes the characters matching a mode.
     *
     * @param   string  $text  Text.
     * @param   string  $mode  emoji or strict.
     *
     * @return  string
     */
    private static function removeCharacters(string $text, string $mode): string
    {
        // Invalid UTF-8 makes every /u pattern fail: drop the broken bytes first.
        if (!preg_match('//u', $text)) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = preg_replace(self::EMOJI, '', $text) ?? $text;
        $text = preg_replace(self::INVISIBLE, '', $text) ?? $text;

        if ($mode === 'strict') {
            $text = preg_replace(self::SYMBOLS, '', $text) ?? $text;
        }

        return $text;
    }
}
