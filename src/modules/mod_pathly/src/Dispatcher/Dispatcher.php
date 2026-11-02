<?php

/**
 * @package     Pathly
 * @subpackage  mod_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Module\Pathly\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Pathly\Plugin\System\Pathly\Service\Services;

\defined('_JEXEC') or die;

/**
 * Dashboard box of the administrator: state of the Ping and of the Flow, last
 * failure, plan and quotas, Lumi. Shown to Super Users only.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /** Address of the actions, handled by the system plugin. */
    public const ACTION_URL = 'index.php?option=com_ajax&group=system&plugin=pathly&format=raw';

    /** @var Services|null */
    private static $services;

    /** Services to use instead of those of the site (tests). */
    public static function useServices(?Services $services): void
    {
        self::$services = $services;
    }

    protected function getLayoutData(): array
    {
        $data           = parent::getLayoutData();
        $data['pathly'] = null;
        $data['state']  = 'ok';

        if (!PluginHelper::isEnabled('system', 'pathly')) {
            $data['state'] = 'plugin_off';

            return $data;
        }
        $services = self::$services ?? Services::joomla();
        if (!$services->platform->canConfigure()) {
            $data['state'] = 'forbidden';

            return $data;
        }
        $data['pathly'] = $services->dashboard->view();
        $data['token']  = Session::getFormToken();
        $data['action'] = self::ACTION_URL;

        return $data;
    }
}
