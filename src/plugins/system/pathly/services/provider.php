<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Pathly\Plugin\System\Pathly\Extension\Pathly;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = new Pathly($dispatcher, (array) PluginHelper::getPlugin('system', 'pathly'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
