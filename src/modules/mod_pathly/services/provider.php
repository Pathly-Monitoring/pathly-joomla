<?php

/**
 * @package     Pathly
 * @subpackage  mod_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\Pathly\Module\Pathly'));
        $container->registerServiceProvider(new Module());
    }
};
