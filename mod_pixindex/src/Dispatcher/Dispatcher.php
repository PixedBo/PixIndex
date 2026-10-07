<?php

/**
 * @package     PixIndex
 * @subpackage  mod_pixindex
 *
 * @copyright   (C) 2026 Gianluca Gabella
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pixed\Module\Pixindex\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_pixindex.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * Inside the component output (e.g. {loadmodule}) only a placeholder is printed:
     * the helper replaces it once the component output is complete.
     *
     * @return  void
     */
    public function dispatch()
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        if ($displayData === false) {
            return;
        }

        if (isset($displayData['placeholder'])) {
            echo $displayData['placeholder'];

            return;
        }

        echo $this->render($displayData);
    }

    /**
     * Returns the layout data, or false to render nothing.
     *
     * @return  array|false
     */
    protected function getLayoutData()
    {
        $data   = parent::getLayoutData();
        $app    = $this->getApplication();
        $helper = $this->getHelperFactory()->getHelper('PixindexHelper');

        if ($helper->isInsideComponent($app)) {
            $placeholder = $helper->deferIndex(
                $data['params'],
                $app,
                $this->module,
                fn (array $toc): string => $this->render(['toc' => $toc] + $data)
            );

            if ($placeholder === null) {
                return false;
            }

            $data['placeholder'] = $placeholder;

            return $data;
        }

        $toc = $helper->getIndex($data['params'], $app, $this->module);

        if ($toc === null) {
            return false;
        }

        $data['toc'] = $toc;

        return $data;
    }

    /**
     * Renders the module layout and returns its output.
     *
     * @param   array  $displayData  Layout data.
     *
     * @return  string
     */
    private function render(array $displayData): string
    {
        // Execute the layout without the dispatcher context, like the parent class does.
        $loader = static function (array $displayData): void {
            extract($displayData);
            unset($displayData);

            /**
             * Extracted variables
             * -----------------
             * @var   \stdClass                  $module
             * @var   \Joomla\Registry\Registry  $params
             */
            require ModuleHelper::getLayoutPath($module->module, $params->get('layout', 'default'));
        };

        ob_start();

        try {
            $loader($displayData);
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
