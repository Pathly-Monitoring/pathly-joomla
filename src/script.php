<?php

/**
 * @package     Pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Installer script of the Pathly package.
 *
 * On a first install: turns the two plugins on (nothing is sent to Pathly
 * before an administrator saves an API key) and shows the box on the home
 * dashboard of the administrator. On an update, the choices of the
 * administrator are kept. On uninstall, Joomla removes the settings and the
 * state with the rows of the extensions: the extension creates no table and
 * no scheduled task.
 */
class Pkg_PathlyInstallerScript
{
    public const MINIMUM_PHP = '7.4.0';

    public const MINIMUM_JOOMLA = '4.4.0';

    /**
     * @param string $type   install, update, discover_install or uninstall.
     * @param object $parent Installer adapter.
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        if (version_compare(PHP_VERSION, static::MINIMUM_PHP, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_PATHLY_PHP_TOO_OLD', static::MINIMUM_PHP), 'error');

            return false;
        }
        if (version_compare(\JVERSION, static::MINIMUM_JOOMLA, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_PATHLY_JOOMLA_TOO_OLD', static::MINIMUM_JOOMLA), 'error');

            return false;
        }

        return true;
    }

    /**
     * @param string $type   install, update, discover_install or uninstall.
     * @param object $parent Installer adapter.
     */
    public function postflight($type, $parent): bool
    {
        if ($type !== 'install' && $type !== 'discover_install') {
            return true;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery(
            'UPDATE ' . $db->quoteName('#__extensions') . ' SET ' . $db->quoteName('enabled') . ' = 1'
            . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('plugin')
            . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('pathly')
            . ' AND ' . $db->quoteName('folder') . ' IN (' . $db->quote('system') . ', ' . $db->quote('content') . ')'
        );
        $db->execute();

        // Joomla created an unpublished box without position: put it on the home dashboard.
        $db->setQuery(
            'SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__modules')
            . ' WHERE ' . $db->quoteName('module') . ' = ' . $db->quote('mod_pathly')
            . ' AND ' . $db->quoteName('client_id') . ' = 1 ORDER BY ' . $db->quoteName('id')
        );
        $id = (int) $db->loadResult();
        if ($id === 0) {
            return true;
        }
        $db->setQuery(
            'UPDATE ' . $db->quoteName('#__modules') . ' SET '
            . $db->quoteName('title') . ' = ' . $db->quote('Pathly') . ', '
            . $db->quoteName('position') . ' = ' . $db->quote('cpanel') . ', '
            . $db->quoteName('published') . ' = 1, '
            . $db->quoteName('access') . ' = 3, '
            . $db->quoteName('params') . ' = ' . $db->quote('{"bootstrap_size":"12"}')
            . ' WHERE ' . $db->quoteName('id') . ' = ' . $id
        );
        $db->execute();
        $db->setQuery(
            'DELETE FROM ' . $db->quoteName('#__modules_menu') . ' WHERE ' . $db->quoteName('moduleid') . ' = ' . $id
        );
        $db->execute();
        $db->setQuery(
            'INSERT INTO ' . $db->quoteName('#__modules_menu') . ' (' . $db->quoteName('moduleid') . ', '
            . $db->quoteName('menuid') . ') VALUES (' . $id . ', 0)'
        );
        $db->execute();

        return true;
    }
}
