<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Pathly\Plugin\System\Pathly\Service\Notices;
use Pathly\Plugin\System\Pathly\Service\Services;

\defined('_JEXEC') or die;

/**
 * System plugin: connection when the settings are saved, replay of the Flow
 * after an update, and the actions of the dashboard box (com_ajax).
 *
 * Nothing is sent to Pathly before an administrator saved an API key.
 */
final class Pathly extends CMSPlugin implements SubscriberInterface
{
    /** @var bool */
    protected $autoloadLanguage = true;

    /** @var Services|null */
    private $services;

    public static function getSubscribedEvents(): array
    {
        return [
            'onExtensionBeforeSave'   => 'onExtensionBeforeSave',
            'onExtensionAfterSave'    => 'onExtensionAfterSave',
            'onExtensionAfterUpdate'  => 'onExtensionAfterUpdate',
            'onExtensionAfterInstall' => 'onExtensionAfterInstall',
            'onJoomlaAfterUpdate'     => 'onJoomlaAfterUpdate',
            'onAjaxPathly'            => 'onAjaxPathly',
        ];
    }

    /** Services to use instead of those of the site (tests). */
    public function setServices(Services $services): void
    {
        $this->services = $services;
    }

    /**
     * Keeps the saved key when the field is left empty: the key is never
     * sent back to the browser, so an empty field means "unchanged".
     */
    public function onExtensionBeforeSave(EventInterface $event): void
    {
        $table = $this->ownTable($event);
        if ($table === null) {
            return;
        }
        $params = json_decode((string) $table->params, true);
        $params = \is_array($params) ? $params : [];
        $key    = trim((string) ($params['api_key'] ?? ''));

        $params['api_key'] = $key !== '' ? $key : $this->services()->platform->settings()['api_key'];
        $table->params     = json_encode($params);
    }

    /** Connects to Pathly with the saved key. */
    public function onExtensionAfterSave(EventInterface $event): void
    {
        if ($this->ownTable($event) === null) {
            return;
        }
        // The key may have changed: build the services again.
        $this->services = $this->services()->reload();
        $this->services->platform->notify($this->services->connect());
    }

    /** An extension was updated (Joomla update manager). */
    public function onExtensionAfterUpdate(EventInterface $event): void
    {
        $this->services()->replay->request('extension');
    }

    /**
     * An extension was installed, which is also how a newer version uploaded
     * by hand is installed.
     */
    public function onExtensionAfterInstall(EventInterface $event): void
    {
        if (self::argument($event, 'eid', 1)) {
            $this->services()->replay->request('extension');
        }
    }

    /** Joomla itself was updated. */
    public function onJoomlaAfterUpdate(EventInterface $event): void
    {
        $this->services()->replay->request('core');
    }

    /**
     * Actions of the dashboard box: `index.php?option=com_ajax&group=system&plugin=pathly&format=raw`.
     * Administrator only, Super User rights, POST with the form token.
     */
    public function onAjaxPathly(EventInterface $event): void
    {
        $platform = $this->services()->platform;
        if (!$platform->isAdministrator() || !$platform->canConfigure()) {
            $platform->notify([Notices::make('error', 'ERR_FORBIDDEN')]);
        } elseif (!$platform->validPost()) {
            $platform->notify([Notices::make('error', 'ERR_TOKEN')]);
        } else {
            $platform->notify($this->services()->act($platform->inputCmd('action'), $platform->inputInt('index')));
        }
        $platform->redirect('index.php');
    }

    private function services(): Services
    {
        return $this->services ?? Services::joomla();
    }

    /**
     * Row of this plugin when the event is the save of its settings.
     *
     * @return object|null
     */
    private function ownTable(EventInterface $event)
    {
        $table = self::argument($event, 'subject', 1);
        if (
            self::argument($event, 'context', 0) !== 'com_plugins.plugin'
            || !\is_object($table)
            || ($table->type ?? '') !== 'plugin'
            || ($table->folder ?? '') !== 'system'
            || ($table->element ?? '') !== 'pathly'
        ) {
            return null;
        }

        return $table;
    }

    /**
     * Argument of an event: named in Joomla 5, positional in Joomla 4.
     *
     * @return mixed
     */
    public static function argument(EventInterface $event, string $name, int $position)
    {
        $value = $event->getArgument($name);

        return $value ?? $event->getArgument($position);
    }
}
