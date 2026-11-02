<?php

namespace Pathly\Tests;

use Pathly\Plugin\System\Pathly\Service\Platform;

/**
 * Platform in memory.
 */
final class FakePlatform implements Platform
{
    public $site = 'https://www.example.test';

    public $settings = ['api_key' => '', 'badge' => false, 'extension_id' => 42];

    public $state = [];

    public $cache = [];

    public $components = [];

    public $time = 1_800_000_000;

    public $env = [];

    public $deferred = [];

    public $admin = true;

    public $allowed = true;

    public $post = true;

    public $input = [];

    public $notices = [];

    public $redirects = [];

    public $lang = 'fr';

    public function withKey(string $key = 'sp_abcdefghijklmnopqrstuvwxyz012345'): self
    {
        $this->settings['api_key'] = $key;

        return $this;
    }

    public function connected(array $extra = []): self
    {
        $this->withKey();
        $this->state = array_merge(
            ['connected' => true, 'organization' => 'Studio', 'site_url' => $this->site, 'ping_id' => 'ping-1'],
            $extra
        );

        return $this;
    }

    public function siteUrl(): string
    {
        return $this->site;
    }

    public function cmsVersion(): string
    {
        return '5.2.1';
    }

    public function language(): string
    {
        return $this->lang;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public $reloads = 0;

    public function reload(): void
    {
        $this->reloads++;
    }

    public function saveSettings(string $apiKey, bool $badge): void
    {
        $this->settings['api_key'] = $apiKey;
        $this->settings['badge']   = $badge;
    }

    public function state(): array
    {
        return $this->state;
    }

    public function saveState(array $state): void
    {
        $this->state = $state;
    }

    public function cacheGet(string $key): ?array
    {
        return $this->cache[$key] ?? null;
    }

    public function cacheSet(string $key, ?array $value): void
    {
        $this->cache[$key] = $value;
    }

    public function componentEnabled(string $element): bool
    {
        return \in_array($element, $this->components, true);
    }

    public function now(): int
    {
        return $this->time;
    }

    public function env(string $name): ?string
    {
        return $this->env[$name] ?? null;
    }

    public function defer(callable $task): void
    {
        $this->deferred[] = $task;
    }

    public function runDeferred(): void
    {
        foreach ($this->deferred as $task) {
            $task();
        }
        $this->deferred = [];
    }

    public function isAdministrator(): bool
    {
        return $this->admin;
    }

    public function canConfigure(): bool
    {
        return $this->allowed;
    }

    public function validPost(): bool
    {
        return $this->post;
    }

    public function inputCmd(string $name): string
    {
        return (string) ($this->input[$name] ?? '');
    }

    public function inputInt(string $name): int
    {
        return (int) ($this->input[$name] ?? 0);
    }

    public function notify(array $notices): void
    {
        foreach ($notices as $notice) {
            $this->notices[] = $notice;
        }
    }

    public function redirect(string $url): void
    {
        $this->redirects[] = $url;
    }

    /** Keys of the notices, without the prefix. */
    public function keys(): array
    {
        return array_map(static function (array $n): string {
            return substr($n['key'], \strlen('PLG_SYSTEM_PATHLY_'));
        }, $this->notices);
    }
}
