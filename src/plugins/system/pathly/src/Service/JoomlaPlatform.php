<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Joomla implementation of the platform.
 *
 * Settings (API key, badge switch) are the parameters of the system plugin,
 * edited in the plugin manager. The state (Ping, Flow, trial, badge) is kept
 * in the `custom_data` column of the same row, which the plugin form never
 * touches. Both go away with the extension when it is uninstalled.
 */
final class JoomlaPlatform implements Platform
{
    private const SESSION_PREFIX = 'plg_system_pathly.';

    /** Joomla message types. */
    private const LEVELS = ['success' => 'message', 'info' => 'info', 'warning' => 'warning', 'error' => 'error'];

    /** @var array<string, mixed>|null */
    private $row;

    public function siteUrl(): string
    {
        return rtrim(Uri::root(), '/');
    }

    public function cmsVersion(): string
    {
        return \JVERSION;
    }

    public function language(): string
    {
        $two = strtolower(substr((string) Factory::getApplication()->getLanguage()->getTag(), 0, 2));

        return \in_array($two, ['fr', 'es', 'de'], true) ? $two : 'en';
    }

    public function settings(): array
    {
        $row    = $this->row();
        $params = self::decode((string) $row['params']);

        return [
            'api_key'      => trim((string) ($params['api_key'] ?? '')),
            'badge'        => !empty($params['badge']),
            'extension_id' => (int) $row['extension_id'],
        ];
    }

    public function reload(): void
    {
        $this->row = null;
    }

    public function saveSettings(string $apiKey, bool $badge): void
    {
        $params            = self::decode((string) $this->row()['params']);
        $params['api_key'] = $apiKey;
        $params['badge']   = $badge ? '1' : '0';
        $this->update('params', (string) json_encode($params));
    }

    public function state(): array
    {
        return self::decode((string) $this->row()['custom_data']);
    }

    public function saveState(array $state): void
    {
        $this->update('custom_data', (string) json_encode($state));
    }

    public function cacheGet(string $key): ?array
    {
        try {
            $value = Factory::getApplication()->getSession()->get(self::SESSION_PREFIX . $key);
        } catch (\Throwable $e) {
            return null;
        }

        return \is_array($value) ? $value : null;
    }

    public function cacheSet(string $key, ?array $value): void
    {
        try {
            Factory::getApplication()->getSession()->set(self::SESSION_PREFIX . $key, $value);
        } catch (\Throwable $e) {
            // No session (command line): nothing to keep.
        }
    }

    public function componentEnabled(string $element): bool
    {
        $db = $this->db();
        $db->setQuery(
            'SELECT ' . $db->quoteName('enabled') . ' FROM ' . $db->quoteName('#__extensions')
            . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('component')
            . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote($element)
        );

        return (int) $db->loadResult() === 1;
    }

    public function now(): int
    {
        return time();
    }

    public function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }

    public function defer(callable $task): void
    {
        register_shutdown_function([self::class, 'runQuietly'], $task);
    }

    /**
     * Runs a deferred task. The page is already sent: a failure is not shown
     * here, a failed replay is shown on the dashboard.
     */
    public static function runQuietly(callable $task): void
    {
        try {
            $task();
        } catch (\Throwable $e) {
            // Nothing to do: see above.
        }
    }

    public function isAdministrator(): bool
    {
        return (bool) Factory::getApplication()->isClient('administrator');
    }

    public function canConfigure(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && (bool) $user->authorise('core.admin');
    }

    public function validPost(): bool
    {
        return Factory::getApplication()->getInput()->getMethod() === 'POST' && (bool) Session::checkToken('post');
    }

    public function inputCmd(string $name): string
    {
        return (string) Factory::getApplication()->getInput()->getCmd($name, '');
    }

    public function inputInt(string $name): int
    {
        return (int) Factory::getApplication()->getInput()->getInt($name, 0);
    }

    public function notify(array $notices): void
    {
        $app = Factory::getApplication();
        foreach ($notices as $notice) {
            $args = $notice['args'] ?? [];
            $text = $args ? Text::sprintf($notice['key'], ...$args) : Text::_($notice['key']);
            $app->enqueueMessage($text, self::LEVELS[$notice['level']] ?? 'info');
        }
    }

    public function redirect(string $url): void
    {
        Factory::getApplication()->redirect($url);
    }

    /**
     * @return array{extension_id: int|string, params: string, custom_data: string}
     */
    private function row(): array
    {
        if ($this->row === null) {
            $db = $this->db();
            $db->setQuery(
                'SELECT ' . $db->quoteName('extension_id') . ', ' . $db->quoteName('params') . ', '
                . $db->quoteName('custom_data') . ' FROM ' . $db->quoteName('#__extensions')
                . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('plugin')
                . ' AND ' . $db->quoteName('folder') . ' = ' . $db->quote('system')
                . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('pathly')
            );
            $row       = $db->loadAssoc();
            $this->row = \is_array($row) ? $row : ['extension_id' => 0, 'params' => '', 'custom_data' => ''];
        }

        return $this->row;
    }

    private function update(string $column, string $value): void
    {
        $id = (int) $this->row()['extension_id'];
        $db = $this->db();
        $db->setQuery(
            'UPDATE ' . $db->quoteName('#__extensions') . ' SET ' . $db->quoteName($column) . ' = ' . $db->quote($value)
            . ' WHERE ' . $db->quoteName('extension_id') . ' = ' . $id
        );
        $db->execute();
        $this->row[$column] = $value;
    }

    private function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $raw): array
    {
        $data = json_decode($raw, true);

        return \is_array($data) ? $data : [];
    }
}
