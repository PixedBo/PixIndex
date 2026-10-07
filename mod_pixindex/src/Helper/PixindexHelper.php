<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pixed\Module\Pixindex\Site\Helper;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Priority;
use Joomla\Registry\Registry;
use Pixed\Module\Pixindex\Site\Toc\Options;
use Pixed\Module\Pixindex\Site\Toc\TocProcessor;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for mod_pixindex: reads the component output, builds the index and
 * schedules the rewrite of the content headings.
 */
class PixindexHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /** Opening and closing characters of every tag syntax option. */
    private const TAG_CHARACTERS = [
        'braces'          => ['{', '}'],
        'brackets'        => ['[', ']'],
        'guillemets'      => ['«', '»'],
        'double_braces'   => ['{{', '}}'],
        'double_brackets' => ['[[', ']]'],
        'colon_brackets'  => ['[:', ':]'],
        'percent_brackets' => ['[%', '%]'],
    ];

    /**
     * Default classes per framework and display. Keys: container, title, body, list,
     * sublist, item, link, listTitle.
     */
    private const PRESETS = [
        'bootstrap5' => [
            'card' => [
                'container' => 'card',
                'title'     => 'card-header fs-6 fw-semibold',
                'body'      => 'card-body',
                'list'      => 'mb-0',
                'sublist'   => '',
                'item'      => '',
                'link'      => '',
                'listTitle' => 'fs-6 fw-semibold mt-2 mb-1',
            ],
            'list' => [
                'container' => '',
                'title'     => 'fs-5 mb-2',
                'body'      => '',
                'list'      => 'mb-0',
                'sublist'   => '',
                'item'      => '',
                'link'      => '',
                'listTitle' => 'fs-6 fw-semibold mt-2 mb-1',
            ],
            'nav' => [
                'container' => '',
                'title'     => 'fs-6 fw-semibold mb-2',
                'body'      => '',
                'list'      => 'nav nav-pills flex-column',
                'sublist'   => 'nav nav-pills flex-column',
                'item'      => 'nav-item',
                'link'      => 'nav-link py-1',
                'listTitle' => 'fs-6 fw-semibold mt-2 mb-1 px-3',
            ],
        ],
        'uikit3' => [
            'card' => [
                'container' => 'uk-card uk-card-default uk-card-small',
                'title'     => 'uk-card-header uk-h4 uk-margin-remove',
                'body'      => 'uk-card-body',
                'list'      => 'uk-list uk-margin-remove',
                'sublist'   => 'uk-list',
                'item'      => '',
                'link'      => '',
                'listTitle' => 'uk-h6 uk-margin-small',
            ],
            'list' => [
                'container' => '',
                'title'     => 'uk-h4',
                'body'      => '',
                'list'      => 'uk-list',
                'sublist'   => 'uk-list',
                'item'      => '',
                'link'      => '',
                'listTitle' => 'uk-h6 uk-margin-small',
            ],
            'nav' => [
                'container' => '',
                'title'     => 'uk-h5',
                'body'      => '',
                'list'      => 'uk-nav uk-nav-default',
                'sublist'   => 'uk-nav-sub',
                'item'      => '',
                'link'      => '',
                'listTitle' => 'uk-nav-header',
            ],
        ],
    ];

    /** Classes set on the active entry, per framework: [link class, item class]. */
    private const ACTIVE_CLASSES = [
        'bootstrap5' => ['active', ''],
        'uikit3'     => ['', 'uk-active'],
        'none'       => ['', ''],
    ];

    /**
     * Content rewrite shared by every instance of the request.
     *
     * Keys: original, modified, converted (whether the headings are converted or
     * only the content tags are removed). Null when nothing is scheduled yet.
     *
     * @var ?array{original: string, modified: string, converted: bool}
     */
    private static ?array $rewrite = null;

    /** @var array<int, ?int> Category of the current article, cached per article id. */
    private static array $categoryCache = [];

    /**
     * Builds the data used by the layouts.
     *
     * @param   Registry                 $params  Module parameters.
     * @param   CMSApplicationInterface  $app     Application.
     * @param   \stdClass                $module  Module object.
     *
     * @return  ?array  Null when the module must not be rendered.
     */
    public function getIndex(Registry $params, CMSApplicationInterface $app, \stdClass $module): ?array
    {
        $document = $app->getDocument();

        if (!$document instanceof HtmlDocument || !$this->isAllowedHere($params, $app)) {
            return null;
        }

        $html = $this->getComponentOutput($document);

        if ($html === null) {
            return null;
        }

        $build = $this->buildIndex($params, $app, $module, $html);

        $this->scheduleRewrite($app, $html, $build['content'], $build['toc'] !== null);

        return $build['toc'];
    }

    /**
     * Tells whether the component is still running, i.e. the module is being
     * rendered inside the component output (for example with {loadmodule}).
     *
     * @param   CMSApplicationInterface  $app  Application.
     *
     * @return  bool
     */
    public function isInsideComponent(CMSApplicationInterface $app): bool
    {
        $document = $app->getDocument();

        return $document instanceof HtmlDocument && !isset($document->getBuffer()['component']);
    }

    /**
     * Postpones the index of a module rendered inside the component output.
     *
     * The component output does not exist yet, so a placeholder is printed instead.
     * Once the component is complete (onAfterDispatch) the index is built from the
     * full output and replaces the placeholder. The headings are rewritten in the same step.
     *
     * @param   Registry                 $params  Module parameters.
     * @param   CMSApplicationInterface  $app     Application.
     * @param   \stdClass                $module  Module object.
     * @param   callable(array): string  $render  Renders the layout from the index data.
     *
     * @return  ?string  Placeholder to print, null when the module must not be rendered.
     */
    public function deferIndex(Registry $params, CMSApplicationInterface $app, \stdClass $module, callable $render): ?string
    {
        if (!$this->isAllowedHere($params, $app)) {
            return null;
        }

        $placeholder = '<!-- pixindex:' . (int) $module->id . ':' . bin2hex(random_bytes(8)) . ' -->';

        // Class of the view whose output holds the placeholder: needed to find its cache entry.
        // Nested views finish before the outer one, so the last match is the cached view.
        $viewClass = null;

        $app->getDispatcher()->addListener(
            'onAfterDisplay',
            static function ($event) use (&$viewClass, $placeholder): void {
                $source  = $event->getArgument('source');
                $subject = $event->getArgument('subject');

                if (\is_string($source) && \is_object($subject) && str_contains($source, $placeholder)) {
                    $viewClass = \get_class($subject);
                }
            }
        );

        $app->getDispatcher()->addListener(
            'onAfterDispatch',
            function () use ($params, $app, $module, $render, $placeholder, &$viewClass): void {
                $document = $app->getDocument();
                $html     = $document instanceof HtmlDocument ? $this->getComponentOutput($document) : null;

                if ($html === null || !str_contains($html, $placeholder)) {
                    return;
                }

                $applied = $this->applyDeferred($params, $app, $module, $render, $placeholder, $html);

                // Converted headings must not be converted again by modules rendered later.
                if ($applied['converted']) {
                    self::$rewrite = ['original' => '', 'modified' => '', 'converted' => true];
                }

                $document->setBuffer($applied['content'], ['type' => 'component']);

                // The view cache already stored the output with the placeholder: fix that copy too.
                if ($viewClass !== null) {
                    $this->refreshViewCache(
                        $app,
                        $viewClass,
                        $placeholder,
                        fn (string $body): string => $body === $html
                            ? $applied['content']
                            : $this->applyDeferred($params, $app, $module, $render, $placeholder, $body)['content']
                    );
                }
            }
        );

        return $placeholder;
    }

    /**
     * Builds the index of a deferred module and puts it in place of its placeholder.
     *
     * @param   Registry                 $params       Module parameters.
     * @param   CMSApplicationInterface  $app          Application.
     * @param   \stdClass                $module       Module object.
     * @param   callable(array): string  $render       Renders the layout from the index data.
     * @param   string                   $placeholder  Placeholder printed by the module.
     * @param   string                   $html         HTML holding the placeholder.
     *
     * @return  array{content: string, converted: bool}  Final HTML and whether the headings were converted.
     */
    private function applyDeferred(Registry $params, CMSApplicationInterface $app, \stdClass $module, callable $render, string $placeholder, string $html): array
    {
        $build   = $this->buildIndex($params, $app, $module, $html);
        $index   = $build['toc'] === null ? '' : $render($build['toc']);
        $content = $build['content'];

        // A placeholder alone in a paragraph replaces the whole paragraph: a <nav> cannot live inside a <p>.
        $pattern = '~<p\b[^>]*>(?:\s|&nbsp;)*' . preg_quote($placeholder, '~') . '(?:\s|&nbsp;)*</p>~i';
        $count   = 0;
        $content = preg_replace_callback($pattern, static fn (): string => $index, $content, 1, $count) ?? $content;

        if ($count === 0) {
            $content = str_replace($placeholder, $index, $content);
        }

        return ['content' => $content, 'converted' => $build['toc'] !== null];
    }

    /**
     * Updates the view cache entry stored with the placeholder.
     *
     * With conservative caching Joomla stores the component view output right after
     * the view is displayed, before onAfterDispatch: the stored copy still holds the
     * placeholder and, when served from the cache, the module does not run again.
     * The entry is rewritten with the final HTML and with the current assets
     * (styles and scripts of the index), or removed when it cannot be updated.
     *
     * The id is computed like ViewController::_makeId().
     *
     * @param   CMSApplicationInterface    $app          Application.
     * @param   string                     $viewClass    Class of the cached view.
     * @param   string                     $placeholder  Placeholder printed by the module.
     * @param   callable(string): string   $transform    Turns the cached body into the final HTML.
     *
     * @return  void
     */
    private function refreshViewCache(CMSApplicationInterface $app, string $viewClass, string $placeholder, callable $transform): void
    {
        $group = $app->getInput()->getCmd('option', '');

        if ((int) $app->get('caching', 0) < 1 || $group === '') {
            return;
        }

        try {
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                ->createCacheController('view', ['defaultgroup' => $group])
                ->cache;

            $id  = md5(serialize([Cache::makeId(), $viewClass, 'display']));
            $raw = $cache->get($id, $group);

            // Nothing stored (logged in user, page not cacheable...).
            if (!\is_string($raw)) {
                return;
            }

            $data = unserialize(trim($raw));

            if (!\is_array($data) || !isset($data['body']) || !\is_string($data['body'])) {
                // Unknown format: better no cache than a copy without the index.
                $cache->remove($id, $group);

                return;
            }

            if (!str_contains($data['body'], $placeholder)) {
                return;
            }

            $data['body'] = $transform($data['body']);
            $document     = $app->getDocument();

            if ($document instanceof HtmlDocument && isset($data['head']) && \is_array($data['head'])) {
                $data['head']['assetManager'] = $document->getHeadData()['assetManager'] ?? ($data['head']['assetManager'] ?? []);
            }

            if (!$cache->store(serialize($data), $id, $group)) {
                $cache->remove($id, $group);
            }
        } catch (\Throwable $exception) {
            // A cache problem must never break the page: the visitor already gets the right output.
        }
    }

    /**
     * Builds the index data and the rewritten content from the component output.
     *
     * @param   Registry                 $params  Module parameters.
     * @param   CMSApplicationInterface  $app     Application.
     * @param   \stdClass                $module  Module object.
     * @param   string                   $html    Component output.
     *
     * @return  array{toc: ?array, content: string}  Layout data (null = hide the module) and rewritten content.
     */
    private function buildIndex(Registry $params, CMSApplicationInterface $app, \stdClass $module, string $html): array
    {
        $rootId  = 'pixindex-' . (int) $module->id;
        $options = $this->buildOptions($params, $app, $rootId);
        $tag     = $app->getLanguage()->getTag();
        $slugger = static fn (string $text): string => ApplicationHelper::stringURLSafe($text, $tag);
        $result  = (new TocProcessor($options, $slugger))->process($html);
        $visible = $result->count > 0 && $result->count >= (int) $params->get('min_headings', 1);

        // Content tags are removed even when the index is hidden.
        $content = $result->rewrite($visible);

        if (!$visible) {
            return ['toc' => null, 'content' => $content];
        }

        $framework  = (string) $params->get('framework', 'bootstrap5');
        $framework  = \in_array($framework, ['bootstrap5', 'uikit3', 'none'], true) ? $framework : 'bootstrap5';
        $display    = (string) $params->get('display', 'card');
        $display    = \in_array($display, ['card', 'list', 'nav'], true) ? $display : 'card';
        $title      = Text::_(trim($result->options->titleOverride ?? (string) $params->get('title', 'MOD_PIXINDEX_INDEX_TITLE')));
        $active     = self::ACTIVE_CLASSES[$framework];
        $maxHeight  = max(0, (int) $params->get('max_height', 0));
        $collapse   = (string) $params->get('collapsible', 'no');
        $collapse   = \in_array($collapse, ['no', 'expanded', 'collapsed', 'mobile'], true) ? $collapse : 'no';
        $compact    = (bool) $params->get('collapse_sublevels', 0);
        $panel      = (bool) $params->get('mobile_panel', 0);
        $breakpoint = max(0, (int) $params->get('mobile_breakpoint', 960));

        // Visible mark of the current section. "auto" highlights the row, except for the
        // Bootstrap navigation where the active pill already does it.
        $activeStyle = (string) $params->get('active_style', 'auto');
        $activeStyle = \in_array($activeStyle, ['auto', 'highlight', 'underline', 'bold', 'line', 'none'], true) ? $activeStyle : 'auto';

        if ($activeStyle === 'auto') {
            $activeStyle = $framework === 'bootstrap5' && $display === 'nav' ? 'none' : 'highlight';
        }

        $glass = $params->get('appearance', 'glass') === 'glass';

        $config = [
            'offset'          => max(0, (int) $params->get('scroll_offset', 0)),
            'smooth'          => (bool) $params->get('smooth_scroll', 1),
            'spy'             => (bool) $params->get('scrollspy', 1),
            'compact'         => $compact,
            'sticky'          => (bool) $params->get('sticky', 0),
            'stickyTop'       => max(0, (int) $params->get('sticky_top', 20)),
            'breakpoint'      => $breakpoint,
            'collapseMobile'  => $collapse === 'mobile',
            'panel'           => $panel,
            'framework'       => $framework,
            'activeLinkClass' => $active[0],
            'activeItemClass' => $active[1],

            'fallback'        => $result->fallbackData(),
            'tagPattern'      => $this->tagPatternForJs($result->options),
        ];

        $containerClasses = [
            'pixindex',
            'pixindex--' . $framework,
            'pixindex--' . $display,
            $this->classFor($params, 'container', $framework, $display),
            $maxHeight > 0 ? 'pixindex--scroll' : '',
            $compact ? 'pixindex--compact' : '',
            'pixindex--active-' . $activeStyle,
            $glass ? 'pixindex--glass' : '',
            trim((string) $params->get('moduleclass_sfx', '')),
        ];

        $style = ($maxHeight > 0 ? '--pixindex-max-height: ' . $maxHeight . 'px;' : '');

        $toc = [
            'id'           => $rootId,
            'framework'    => $framework,
            'display'      => $display,
            'entries'      => $result->entries,
            'count'        => $result->count,
            'options'      => $result->options,
            'title'        => $title,
            'showTitle'    => (bool) $params->get('show_title', 1) || $collapse !== 'no',
            'titleTag'     => $this->titleTag((string) $params->get('title_tag', 'h3')),
            'collapsible'  => $collapse !== 'no',
            // "mobile" is printed open: the script closes it on small screens.
            'collapsed'    => $collapse === 'collapsed',
            'panel'        => $panel,
            'panelSide'    => $params->get('mobile_panel_side', 'end') === 'start' ? 'start' : 'end',
            'hierarchical' => $display === 'nav' || $params->get('index_numbering', 'markers') === 'hierarchical',
            'markers'      => $display !== 'nav',
            'bullets'      => (bool) $params->get('list_bullets', 1),
            'compact'      => $compact,
            'comments'     => (bool) $params->get('html_comments', 1),
            'loadCss'      => (bool) $params->get('load_css', 1),
            'style'        => $style,
            'config'       => $config,
            'classes'      => [
                'container' => trim(implode(' ', array_filter($containerClasses))),
                'title'     => $this->classFor($params, 'title', $framework, $display),
                'body'      => $this->classFor($params, 'body', $framework, $display),
                'list'      => $this->classFor($params, 'list', $framework, $display),
                'sublist'   => $this->classFor($params, 'sublist', $framework, $display),
                'item'      => $this->classFor($params, 'item', $framework, $display),
                'link'      => $this->classFor($params, 'link', $framework, $display),
                'listTitle' => $this->classFor($params, 'listTitle', $framework, $display),
            ],
        ];

        return ['toc' => $toc, 'content' => $content];
    }

    /**
     * Renders an array as escaped HTML attributes. Null and false values are skipped.
     *
     * @param   array<string, mixed>  $attributes  Attributes.
     *
     * @return  string  Attribute string starting with a space.
     */
    public static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $html .= $value === true
                ? ' ' . $name
                : ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return $html;
    }

    /**
     * Joins class names, skipping the empty ones.
     *
     * @param   string  ...$classes  Class names.
     *
     * @return  string
     */
    public static function classes(string ...$classes): string
    {
        return trim(implode(' ', array_filter(array_map('trim', $classes))));
    }

    /**
     * Returns the component output already rendered for this request.
     *
     * The buffer is read directly so a module rendered before the component never
     * triggers an empty component render.
     *
     * @param   HtmlDocument  $document  Document.
     *
     * @return  ?string
     */
    private function getComponentOutput(HtmlDocument $document): ?string
    {
        $buffer = $document->getBuffer();
        $html   = $buffer['component'][''][''] ?? null;

        return \is_string($html) && trim($html) !== '' ? $html : null;
    }

    /**
     * Schedules the replacement of the component output inside the final page.
     *
     * One rewrite is shared by every instance of the request: a rewrite that converts
     * the headings replaces one that only removes the content tags (e.g. from an
     * instance hidden because of too few headings), never the other way round.
     *
     * The listener runs with a high priority so it sees the component output before
     * other onAfterRender listeners (SEF, page cache...) change the page.
     *
     * @param   CMSApplicationInterface  $app        Application.
     * @param   string                   $original   Original component output.
     * @param   string                   $modified   Rewritten component output.
     * @param   bool                     $converted  Whether the headings are converted.
     *
     * @return  void
     */
    private function scheduleRewrite(CMSApplicationInterface $app, string $original, string $modified, bool $converted): void
    {
        if ($original === $modified) {
            return;
        }

        $scheduled = self::$rewrite;

        if ($scheduled !== null && ($scheduled['converted'] || !$converted)) {
            return;
        }

        self::$rewrite = ['original' => $original, 'modified' => $modified, 'converted' => $converted];

        // The listener reads the shared rewrite when it runs: register it only once.
        if ($scheduled !== null) {
            return;
        }

        $app->getDispatcher()->addListener(
            'onAfterRender',
            static function () use ($app): void {
                $rewrite = self::$rewrite;
                $body    = $app->getBody();

                if ($rewrite === null || $rewrite['original'] === '' || !\is_string($body)) {
                    return;
                }

                $position = strpos($body, $rewrite['original']);

                // When the output changed in between, the client side fallback takes over.
                if ($position !== false) {
                    $app->setBody(substr_replace($body, $rewrite['modified'], $position, \strlen($rewrite['original'])));
                }
            },
            Priority::HIGH
        );
    }

    /**
     * Converts the module parameters to engine options.
     *
     * @param   Registry                 $params  Module parameters.
     * @param   CMSApplicationInterface  $app     Application.
     * @param   string                   $rootId  Id of the index element.
     *
     * @return  Options
     */
    private function buildOptions(Registry $params, CMSApplicationInterface $app, string $rootId): Options
    {
        $options = new Options();

        $options->indexMin       = (int) $params->get('index_min', 2);
        $options->indexMax       = (int) $params->get('index_max', 6);
        $options->excludedLevels = array_map('intval', (array) $params->get('exclude_levels', []));
        $options->sanitize       = (string) $params->get('sanitize_titles', 'emoji');

        $ordered = (string) $params->get('ordered', 'yes');

        for ($depth = 1; $depth <= 6; $depth++) {
            if ($ordered === 'custom') {
                $options->ordered[$depth]       = (bool) $params->get('ordered_' . $depth, 1);
                $options->orderedStyles[$depth] = (string) $params->get('ordered_style_' . $depth, '1');
            } else {
                $options->ordered[$depth]       = $ordered === 'yes';
                $options->orderedStyles[$depth] = (string) $params->get('ordered_style', '1');
            }
        }


        $options->numberSeparator = (string) $params->get('number_separator', '.');
        $options->removeHtml      = (bool) $params->get('remove_html', 1);


        $options->numberInIds     = (bool) $params->get('id_number', 0);
        $options->parentsInIds    = (bool) $params->get('id_parents', 0);
        $options->idPrefix        = trim((string) $params->get('id_prefix', ''));
        $options->numericIdPrefix = trim((string) $params->get('id_numeric_prefix', 'h-'));
        $options->removeWords     = self::csv((string) $params->get('id_remove_words', ''));

        $options->contentContainers = self::csv((string) $params->get('content_containers', '.com-content-article__body'));
        $options->noIndexClasses    = self::csv((string) $params->get('noindex_classes', 'noindex, pixindex-skip, uk-article-title'));
        $options->noIndexAttributes = self::parseAttributeList((string) $params->get('noindex_attributes', 'itemprop="headline", property="headline"'));
        $options->noIndexContainers = self::csv((string) $params->get('noindex_containers', '.page-header'));
        $options->truncateLength     = max(0, (int) $params->get('truncate_length', 0));
        $options->truncateWholeWords = (bool) $params->get('truncate_whole_words', 1);
        $options->titleClasses      = self::csv((string) $params->get('title_classes', ''));

        $options->tagWord     = trim((string) $params->get('tag_word', 'pixindex')) ?: 'pixindex';
        $options->noIndexWord = trim((string) $params->get('noindex_word', 'pixindex-skip')) ?: 'pixindex-skip';
        [$options->tagOpen, $options->tagClose] = self::TAG_CHARACTERS[(string) $params->get('tag_characters', 'braces')] ?? self::TAG_CHARACTERS['braces'];

        $options->headingClass = trim((string) $params->get('class_heading', ''));


        if ($params->get('back_link', 0)) {
            $options->backLinkTarget = $rootId;
            $options->backLinkLabel  = Text::_((string) $params->get('back_link_text', 'MOD_PIXINDEX_BACK_TO_INDEX'));
        }

        $options->normalize();

        return $options;
    }

    /**
     * Tells whether the module may run on the current page (component and category filters).
     *
     * @param   Registry                 $params  Module parameters.
     * @param   CMSApplicationInterface  $app     Application.
     *
     * @return  bool
     */
    private function isAllowedHere(Registry $params, CMSApplicationInterface $app): bool
    {
        $input  = $app->getInput();
        $option = $input->getCmd('option', '');

        $componentsMode = (string) $params->get('components_mode', 'all');
        $components     = array_filter((array) $params->get('components', []));

        if ($componentsMode === 'include' && !\in_array($option, $components, true)) {
            return false;
        }

        if ($componentsMode === 'exclude' && \in_array($option, $components, true)) {
            return false;
        }

        $categoriesMode = (string) $params->get('categories_mode', 'ignore');

        if ($categoriesMode === 'ignore') {
            return true;
        }

        $selected = array_filter(array_map('intval', (array) $params->get('categories', [])));
        $current  = $this->getCurrentCategoryId($app);
        $matches  = $current !== null && $selected !== []
            && \in_array($current, $this->expandCategories($selected, (string) $params->get('categories_children', 'no')), true);

        return $categoriesMode === 'include' ? $matches : !$matches;
    }

    /**
     * Returns the category of the current article or category page.
     *
     * @param   CMSApplicationInterface  $app  Application.
     *
     * @return  ?int
     */
    private function getCurrentCategoryId(CMSApplicationInterface $app): ?int
    {
        $input = $app->getInput();

        if ($input->getCmd('option') !== 'com_content') {
            return null;
        }

        $view = $input->getCmd('view');
        $id   = $input->getInt('id');

        if ($view === 'category' && $id > 0) {
            return $id;
        }

        if ($view !== 'article' || $id <= 0) {
            return null;
        }

        // Several instances (or the deferred pass) ask for the same article.
        if (\array_key_exists($id, self::$categoryCache)) {
            return self::$categoryCache[$id];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $catid = $db->setQuery($query)->loadResult();

        return self::$categoryCache[$id] = $catid ? (int) $catid : null;
    }

    /**
     * Expands the selected categories according to the children setting.
     *
     * @param   int[]   $selected  Selected category ids.
     * @param   string  $children  no, yes or only.
     *
     * @return  int[]
     */
    private function expandCategories(array $selected, string $children): array
    {
        if ($children !== 'yes' && $children !== 'only') {
            return $selected;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('c.id'))
            ->from($db->quoteName('#__categories', 'c'))
            ->join(
                'INNER',
                $db->quoteName('#__categories', 'p'),
                $db->quoteName('c.lft') . ' > ' . $db->quoteName('p.lft') . ' AND ' . $db->quoteName('c.rgt') . ' < ' . $db->quoteName('p.rgt')
            )
            ->whereIn($db->quoteName('p.id'), $selected);

        $descendants = array_map('intval', $db->setQuery($query)->loadColumn() ?: []);

        return $children === 'only' ? $descendants : array_values(array_unique(array_merge($selected, $descendants)));
    }

    /**
     * Returns the class of an index element: the custom class when set, the preset otherwise.
     *
     * @param   Registry  $params     Module parameters.
     * @param   string    $element    Element key.
     * @param   string    $framework  Framework.
     * @param   string    $display    Display.
     *
     * @return  string
     */
    private function classFor(Registry $params, string $element, string $framework, string $display): string
    {
        $param  = 'class_' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $element));
        $custom = trim((string) $params->get($param, ''));

        if ($custom !== '') {
            return $custom;
        }

        return self::PRESETS[$framework][$display][$element] ?? '';
    }

    /**
     * Validates the index title tag.
     *
     * @param   string  $tag  Tag name.
     *
     * @return  string
     */
    private function titleTag(string $tag): string
    {
        return \in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'], true) ? $tag : 'h3';
    }

    /**
     * Builds the JavaScript regular expression matching the leftover content tags.
     *
     * @param   Options  $options  Engine options.
     *
     * @return  string
     */
    private function tagPatternForJs(Options $options): string
    {
        $quote = static fn (string $value): string => preg_replace('/[.*+?^${}()|[\]\\\\\/-]/', '\\\\$0', $value);
        $open  = $quote($options->tagOpen);
        $close = $quote($options->tagClose);

        return $open . '\\s*\\/?\\s*(?:' . $quote($options->tagWord) . '|' . $quote($options->noIndexWord) . ')(?=\\s|' . $close . ')[\\s\\S]*?' . $close;
    }

    /**
     * Splits a comma separated list.
     *
     * @param   string  $value  Raw value.
     *
     * @return  string[]
     */
    private static function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }

    /**
     * Parses a list of attributes such as: itemprop="headline", data-skip.
     *
     * @param   string  $value  Raw value.
     *
     * @return  array<int, array{0: string, 1: ?string}>
     */
    private static function parseAttributeList(string $value): array
    {
        $attributes = [];

        if (preg_match_all('#([^\s=,"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s,"\']+)))?#', $value, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
            foreach ($matches as $match) {
                $attributes[] = [$match[1], $match[2] ?? $match[3] ?? $match[4] ?? null];
            }
        }

        return $attributes;
    }
}
