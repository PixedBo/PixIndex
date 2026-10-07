<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Pixed\Module\Pixindex\Site\Helper\PixindexHelper;

/**
 * UIkit 3 markup (YOOtheme Pro and every UIkit based template).
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
                    'type'          => 'button',
                    'class'         => 'pixindex-toggle',
                    'uk-toggle'     => 'target: #' . $bodyId . '; animation: uk-animation-fade',
                    'aria-controls' => $bodyId,
                    'aria-expanded' => $toc['collapsed'] ? 'false' : 'true',
                ]); ?>><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></button>
            <?php else : ?>
                <?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </<?php echo $toc['titleTag']; ?>>
    <?php endif; ?>
    <div<?php echo PixindexHelper::attributes([
        'id'     => $bodyId,
        'class'  => PixindexHelper::classes($toc['classes']['body'], 'pixindex-body'),
        'hidden' => $toc['collapsible'] && $toc['collapsed'],
    ]); ?>>
        <?php $renderItems($toc['entries'], 1); ?>
    </div>
    <?php if ($toc['panel']) : ?>
        <?php // Moved to <body> by the script; the list is cloned into the panel body. ?>
        <button<?php echo PixindexHelper::attributes([
            'type'       => 'button',
            'class'      => 'pixindex-fab pixindex-fab--' . $toc['panelSide'] . ' uk-button uk-button-primary',
            'uk-toggle'  => 'target: #' . $panelId,
            'aria-label' => $toc['title'],
            'hidden'     => true,
        ]); ?>><?php echo $panelIcon; ?></button>
        <div<?php echo PixindexHelper::attributes([
            'id'                  => $panelId,
            'class'               => 'pixindex pixindex--panel pixindex--uikit3',
            'uk-offcanvas'        => 'overlay: true; flip: ' . ($toc['panelSide'] === 'end' ? 'true' : 'false'),
            'data-pixindex-panel' => true,
        ]); ?>>
            <div class="uk-offcanvas-bar">
                <button class="uk-offcanvas-close" type="button" uk-close data-pixindex-close aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8'); ?>"></button>
                <p class="uk-h4 pixindex-panel-title"><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="pixindex-panel-body"></div>
            </div>
        </div>
    <?php endif; ?>
    <script type="application/json" class="pixindex-config"><?php echo $config; ?></script>
</nav>
