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
 * Framework free markup, styled only by the module stylesheet and the template.
 *
 * Extracted variables
 * -----------------
 * @var   array     $toc          Index data built by the helper.
 * @var   string    $config       JSON configuration for the script.
 * @var   string    $panelIcon    SVG icon of the mobile button.
 * @var   \Closure  $renderItems  Renders a list of entries.
 */

$titleClass = PixindexHelper::classes($toc['classes']['title'], 'pixindex-title');
$panelId    = $toc['id'] . '-panel';
?>
<nav<?php echo PixindexHelper::attributes([
    'id'            => $toc['id'],
    'class'         => $toc['classes']['container'],
    'style'         => $toc['style'],
    'aria-label'    => $toc['title'],
    'data-pixindex' => true,
]); ?>>
    <?php if ($toc['collapsible']) : ?>
        <details class="pixindex-details"<?php echo $toc['collapsed'] ? '' : ' open'; ?>>
            <summary class="<?php echo htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></summary>
    <?php elseif ($toc['showTitle']) : ?>
        <<?php echo $toc['titleTag']; ?> class="<?php echo htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></<?php echo $toc['titleTag']; ?>>
    <?php endif; ?>
    <div class="<?php echo htmlspecialchars(PixindexHelper::classes($toc['classes']['body'], 'pixindex-body'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php $renderItems($toc['entries'], 1); ?>
    </div>
    <?php if ($toc['collapsible']) : ?>
        </details>
    <?php endif; ?>
    <?php if ($toc['panel']) : ?>
        <?php // Moved to <body> by the script; the list is cloned into the panel body. ?>
        <button<?php echo PixindexHelper::attributes([
            'type'                => 'button',
            'class'               => 'pixindex-fab pixindex-fab--' . $toc['panelSide'] . ' pixindex-fab--plain',
            'aria-controls'       => $panelId,
            'aria-label'          => $toc['title'],
            'data-pixindex-open'  => $panelId,
            'hidden'              => true,
        ]); ?>><?php echo $panelIcon; ?></button>
        <dialog<?php echo PixindexHelper::attributes([
            'id'                  => $panelId,
            'class'               => 'pixindex pixindex--panel pixindex--none pixindex-dialog pixindex-dialog--' . $toc['panelSide'],
            'aria-labelledby'     => $panelId . '-title',
            'data-pixindex-panel' => true,
        ]); ?>>
            <div class="pixindex-dialog-header">
                <p class="pixindex-panel-title" id="<?php echo $panelId; ?>-title"><?php echo htmlspecialchars($toc['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                <button type="button" class="pixindex-dialog-close" data-pixindex-close aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8'); ?>">&times;</button>
            </div>
            <div class="pixindex-panel-body"></div>
        </dialog>
    <?php endif; ?>
    <script type="application/json" class="pixindex-config"><?php echo $config; ?></script>
</nav>
