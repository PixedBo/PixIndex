<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Pixed\Module\Pixindex\Site\Helper\PixindexHelper;

/**
 * Bootstrap 5 markup (Cassiopeia and every Bootstrap based template).
 *
 * Extracted variables
 * -----------------
 * @var   array     $toc          Index data built by the helper.
 * @var   string    $config       JSON configuration for the script.
 * @var   string    $panelIcon    SVG icon of the mobile button.
 * @var   \Closure  $renderItems  Renders a list of entries.
 */

$bodyId  = $toc['id'] . '-body';
$panelId = $toc['id'] . '-panel';

if ($toc['collapsible']) {
    HTMLHelper::_('bootstrap.collapse');
}

if ($toc['panel']) {
    HTMLHelper::_('bootstrap.offcanvas');
}
?>
<nav<?php echo PixindexHelper::attributes([
    'id'            => $toc['id'],
    'class'         => $toc['classes']['container'],
    'style'         => $toc['style'],
    'aria-label'    => $toc['title'],
    'data-pixindex' => true,
]); ?>>
    <?php if ($toc['showTitle']) : ?>
        <<?php echo $toc['titleTag']; ?> class="<?php echo htmlspecialchars(PixindexHelper::classes($toc['classes']['title'], 'pixindex-title'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($toc['collapsible']) : ?>
                <button<?php echo PixindexHelper::attributes([
                    'type'           => 'button',
                    'class'          => 'pixindex-toggle' . ($toc['collapsed'] ? ' collapsed' : ''),
                    'data-bs-toggle' => 'collapse',
                    'data-bs-target' => '#' . $bodyId,
                    'aria-controls'  => $bodyId,
                    'aria-expanded'  => $toc['collapsed'] ? 'false' : 'true',
                ]); ?>><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></button>
            <?php else : ?>
                <?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </<?php echo $toc['titleTag']; ?>>
    <?php endif; ?>
    <div<?php echo PixindexHelper::attributes([
        'id'    => $bodyId,
        'class' => PixindexHelper::classes(
            $toc['classes']['body'],
            'pixindex-body',
            $toc['collapsible'] ? 'collapse' : '',
            $toc['collapsible'] && !$toc['collapsed'] ? 'show' : ''
        ),
    ]); ?>>
        <?php $renderItems($toc['entries'], 1); ?>
    </div>
    <?php if ($toc['panel']) : ?>
        <?php // Moved to <body> by the script; the list is cloned into the panel body. ?>
        <button<?php echo PixindexHelper::attributes([
            'type'           => 'button',
            'class'          => 'pixindex-fab pixindex-fab--' . $toc['panelSide'] . ' btn btn-primary rounded-pill shadow',
            'data-bs-toggle' => 'offcanvas',
            'data-bs-target' => '#' . $panelId,
            'aria-controls'  => $panelId,
            'aria-label'     => $toc['title'],
            'hidden'         => true,
        ]); ?>><?php echo $panelIcon; ?></button>
        <div<?php echo PixindexHelper::attributes([
            'id'              => $panelId,
            'class'           => 'pixindex pixindex--panel pixindex--bootstrap5 offcanvas offcanvas-' . $toc['panelSide'],
            'tabindex'        => '-1',
            'aria-labelledby' => $panelId . '-title',
            'data-pixindex-panel' => true,
        ]); ?>>
            <div class="offcanvas-header">
                <p class="offcanvas-title fs-5 mb-0" id="<?php echo $panelId; ?>-title"><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-pixindex-close aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="offcanvas-body pixindex-panel-body"></div>
        </div>
    <?php endif; ?>
    <script type="application/json" class="pixindex-config"><?php echo $config; ?></script>
</nav>
