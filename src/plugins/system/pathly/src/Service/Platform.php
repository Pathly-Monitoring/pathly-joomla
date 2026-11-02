<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Service;

\defined('_JEXEC') or die;

/**
 * What the extension needs from Joomla. The services only talk to this
 * interface, so they are tested without a Joomla site.
 */
interface Platform
{
    /** Home page of the site, without a trailing slash. */
    public function siteUrl(): string;

    /** Joomla version (`5.2.1`). */
    public function cmsVersion(): string;

    /** Two-letter language of the administrator: fr, en, es or de. */
    public function language(): string;

    /**
     * Settings of the system plugin.
     *
     * @return array{api_key: string, badge: bool, extension_id: int}
     */
    public function settings(): array;

    /** Forgets what was read from the database (the settings were just saved). */
    public function reload(): void;

    /** Replaces the API key and the badge switch. */
    public function saveSettings(string $apiKey, bool $badge): void;

    /**
     * State kept by the extension (Ping, Flow, Lumi trial, badge).
     *
     * @return array<string, mixed>
     */
    public function state(): array;

    /** @param array<string, mixed> $state */
    public function saveState(array $state): void;

    /** Short-lived value of the current administrator session, or null. */
    public function cacheGet(string $key): ?array;

    /** @param array<string, mixed>|null $value Null forgets the value. */
    public function cacheSet(string $key, ?array $value): void;

    /** Whether an installed component is enabled (`com_virtuemart`). */
    public function componentEnabled(string $element): bool;

    /** Unix time. */
    public function now(): int;

    /** Server environment variable, or null. */
    public function env(string $name): ?string;

    /** Runs a task once the current request is over. */
    public function defer(callable $task): void;

    /** Whether the request is in the administrator. */
    public function isAdministrator(): bool;

    /** Whether the current user may configure the extension (Super User rights). */
    public function canConfigure(): bool;

    /** Whether the request is a POST carrying a valid form token (CSRF). */
    public function validPost(): bool;

    /** Request variable, letters, digits, dot, dash and underscore only. */
    public function inputCmd(string $name): string;

    /** Request variable as an integer. */
    public function inputInt(string $name): int;

    /**
     * Shows messages to the administrator.
     *
     * @param list<array{level: string, key: string, args?: list<scalar>}> $notices
     */
    public function notify(array $notices): void;

    /** Sends the administrator back to a page of the administrator. */
    public function redirect(string $url): void;
}
