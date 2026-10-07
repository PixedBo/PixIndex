<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Pixed\Module\Pixindex\Site\Helper\PixindexHelper;

/**
 * Extracted variables
 * -----------------
 * @var   \Pixed\Module\Pixindex\Site\Toc\IndexEntry[]  $entries      Entries of this list.
 * @var   int                                            $depth        List depth (1 based).
 * @var   string                                         $listId       Id of this list ('' for the first level).
 * @var   array                                          $toc          Index data built by the helper.
 * @var   \Closure                                       $renderItems  Renders a nested list.
 */

use Joomla\CMS\Language\Text;

/** @var \Pixed\Module\Pixindex\Site\Toc\Options $options */
$options = $toc['options'];
$ordered = $options->isOrdered($depth);
$style   = $options->orderedStyle($depth);

$markers = [
    '1' => 'decimal',
    'a' => 'lower-alpha',
    'A' => 'upper-alpha',
    'i' => 'lower-roman',
    'I' => 'upper-roman',
];

if (!$toc['markers'] || $toc['hierarchical']) {
    $marker = 'none';
} else {
    $marker = $ordered ? $markers[$style] : ($toc['bullets'] ? 'disc' : 'none');
}

$listTag   = $ordered ? 'ol' : 'ul';
$listClass = PixindexHelper::classes(
    $depth === 1 ? $toc['classes']['list'] : $toc['classes']['sublist'],
    'pixindex-list',
    'pixindex-list--level-' . $depth,
    'pixindex-list--' . $marker
);
$typeAttribute = $ordered && $marker !== 'none' ? $style : null;
?>
<<?php echo $listTag . PixindexHelper::attributes(['id' => $listId, 'class' => $listClass, 'type' => $typeAttribute]); ?>>
    <?php foreach ($entries as $entry) : ?>
        <?php
        // Collapsible sub-levels: parent entries get a toggle, shown by the script.
        $isParent  = $toc['compact'] && $entry->children;
        $subListId = $isParent ? $toc['id'] . '-sub-' . $entry->id() : '';
        $itemClass = PixindexHelper::classes(
            $toc['classes']['item'],
            'pixindex-item',
            $entry->isTitle() ? 'pixindex-item--title' : '',
            $isParent ? 'pixindex-item--parent' : ''
        );
        $number    = $toc['hierarchical'] && $ordered && $entry->number() !== ''
            ? '<span class="pixindex-index-number">' . htmlspecialchars($entry->number(), ENT_QUOTES, 'UTF-8') . '</span> '
            : '';
        ?>
        <li class="<?php echo htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($entry->isTitle()) : ?>
                <?php // Titles are plain text: real headings here would pollute the page outline. ?>
                <span class="<?php echo htmlspecialchars(PixindexHelper::classes($toc['classes']['listTitle'], 'pixindex-item-title'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $entry->label; ?></span>
            <?php else : ?>
                <a<?php echo PixindexHelper::attributes([
                    'class' => PixindexHelper::classes($toc['classes']['link'], 'pixindex-link'),
                    'href'  => '#' . $entry->id(),
                    // Truncated entries keep their full text as a tooltip.
                    'title' => $entry->fullText !== '' ? $entry->fullText : null,
                ]); ?>><?php echo $number . $entry->label; ?></a>
            <?php endif; ?>
            <?php if ($isParent) : ?>
                <button<?php echo PixindexHelper::attributes([
                    'type'          => 'button',
                    'class'         => 'pixindex-branch-toggle',
                    'aria-controls' => $subListId,
                    'aria-expanded' => 'true',
                    'aria-label'    => Text::sprintf('MOD_PIXINDEX_TOGGLE_SUBLEVELS', $entry->plainLabel()),
                    'hidden'        => true,
                ]); ?>></button>
            <?php endif; ?>
            <?php if ($entry->children) : ?>
                <?php $renderItems($entry->children, $depth + 1, $subListId); ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</<?php echo $listTag; ?>>
