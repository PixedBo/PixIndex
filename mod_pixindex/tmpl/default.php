<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

/**
 * Extracted variables
 * -----------------
 * @var   \Joomla\CMS\Application\SiteApplication  $app
 * @var   \stdClass                                $module
 * @var   \Joomla\Registry\Registry                $params
 * @var   array                                    $toc     Index data built by the helper.
 */

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $app->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('mod_pixindex');
$wa->useScript('mod_pixindex.pixindex');

if ($toc['loadCss']) {
    $wa->useStyle('mod_pixindex.pixindex');
}

/**
 * Renders a list of index entries. Each call gets its own scope, so the items
 * layout can call it again for the children.
 *
 * @param   \Pixed\Module\Pixindex\Site\Toc\IndexEntry[]  $entries  Entries.
 * @param   int                                            $depth    List depth (1 based).
 * @param   string                                         $listId   Id of the list (needed by the sub-level toggles).
 */
$renderItems = static function (array $entries, int $depth, string $listId = '') use (&$renderItems, $toc, $module, $params): void {
    require ModuleHelper::getLayoutPath('mod_pixindex', 'default_items');
};

// The configuration read by media/mod_pixindex/js/pixindex.js.
$config = json_encode($toc['config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Icon of the mobile button: drawn with currentColor, so it follows the button colors.
$panelIcon = '<svg class="pixindex-fab-icon" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
    . '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M7 5h10M7 10h10M7 15h10M3 5h.01M3 10h.01M3 15h.01"/></svg>';

if ($toc['comments']) {
    echo "\n<!-- PixIndex: start -->\n";
}

require ModuleHelper::getLayoutPath('mod_pixindex', 'default_' . $toc['framework']);

if ($toc['comments']) {
    echo "\n<!-- PixIndex: end -->\n";
}
