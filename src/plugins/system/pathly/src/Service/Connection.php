<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Service;

use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Api\Client;

\defined('_JEXEC') or die;

/**
 * Checks the API key, then creates the Ping of the site, or attaches to the
 * one that already watches the same address.
 */
final class Connection
{
    /** Every five minutes: allowed on every plan, the free one included. */
    public const PING_INTERVAL_SEC = 300;

    /** Pages of scenarios read when looking for an existing Ping. */
    public const MAX_PAGES = 10;

    /** @var Platform */
    private $platform;

    /** @var Client */
    private $client;

    public function __construct(Platform $platform, Client $client)
    {
        $this->platform = $platform;
        $this->client   = $client;
    }

    /** Whether an API key is saved. */
    public function hasKey(): bool
    {
        return $this->platform->settings()['api_key'] !== '';
    }

    /**
     * Checks the key and makes sure the site has its Ping.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function connect(): array
    {
        $key = $this->platform->settings()['api_key'];
        if ($key === '') {
            return [Notices::make('warning', 'NO_KEY')];
        }
        if (!Client::looksLikeKey($key)) {
            return [$this->fail(Notices::make('error', 'ERR_KEY_FORMAT'))];
        }

        $site = $this->platform->siteUrl();
        try {
            $account = $this->client->account();
            $pingId  = $this->ensurePing($site);
        } catch (ApiException $e) {
            return [$this->fail(Notices::fromApi($e))];
        }

        $state                 = $this->platform->state();
        $state['connected']    = true;
        $state['organization'] = (string) ($account['organization']['name'] ?? '');
        $state['space']        = isset($account['space']['name']) ? (string) $account['space']['name'] : null;
        $state['site_url']     = $site;
        $state['ping_id']      = $pingId;
        unset($state['last_error']);
        $this->platform->saveState($state);

        return [Notices::make('success', 'CONNECTED', [$state['organization']])];
    }

    /**
     * Forgets the key and the state. Nothing is deleted in Pathly.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function disconnect(): array
    {
        $this->platform->saveSettings('', false);
        $this->platform->saveState([]);

        return [Notices::make('success', 'DISCONNECTED')];
    }

    /**
     * Comparable form of an address: host without `www.`, path without trailing slash.
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url(strtolower(trim($url)));
        if (!\is_array($parts) || empty($parts['host'])) {
            return '';
        }

        return preg_replace('/^www\./', '', $parts['host']) . rtrim($parts['path'] ?? '', '/');
    }

    /**
     * Id of the Ping of the site, created when none watches it yet.
     *
     * @throws ApiException
     */
    public function ensurePing(string $site): string
    {
        $known = (string) ($this->platform->state()['ping_id'] ?? '');
        if ($known !== '') {
            try {
                $this->client->getScenario($known);

                return $known;
            } catch (ApiException $e) {
                if ($e->status() !== 404) {
                    throw $e;
                }
            }
        }

        $id = $this->find($site);
        if ($id !== '') {
            return $id;
        }

        $target  = self::normalize($site);
        $created = $this->client->createScenario(
            [
                'name'        => 'Ping · ' . $target,
                'type'        => 'http',
                'url'         => $site,
                'intervalSec' => self::PING_INTERVAL_SEC,
                'tags'        => ['joomla'],
            ],
            'joomla-ping-' . substr(hash('sha256', $target), 0, 40)
        );

        return (string) ($created['id'] ?? '');
    }

    /**
     * Id of a Ping already watching the same address, or ''.
     *
     * @throws ApiException
     */
    private function find(string $site): string
    {
        $target = self::normalize($site);
        $cursor = '';
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->client->listScenarios($cursor);
            foreach ((array) ($result['items'] ?? []) as $item) {
                if (
                    ($item['type'] ?? '') === 'http'
                    && isset($item['id'])
                    && self::normalize((string) ($item['url'] ?? '')) === $target
                ) {
                    return (string) $item['id'];
                }
            }
            $cursor = \is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : '';
            if ($cursor === '') {
                break;
            }
        }

        return '';
    }

    /**
     * Keeps the refusal for the dashboard and returns it.
     *
     * @param array{level: string, key: string, args: list<scalar>} $notice
     *
     * @return array{level: string, key: string, args: list<scalar>}
     */
    private function fail(array $notice): array
    {
        $state               = $this->platform->state();
        $state['connected']  = false;
        $state['last_error'] = $notice;
        $this->platform->saveState($state);

        return $notice;
    }
}
