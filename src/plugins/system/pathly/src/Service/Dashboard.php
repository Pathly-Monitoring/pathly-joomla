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
 * What the dashboard box shows: Ping and Flow state, last failure with its
 * screenshot, plan and quotas, Lumi trial, links to Pathly.
 *
 * Read when the dashboard is displayed, kept two minutes in the session of
 * the administrator. Never while a trial is running, to show its progress.
 */
final class Dashboard
{
    public const CACHE_KEY = 'dashboard';

    public const CACHE_TTL = 120;

    /** Address of the Pathly app. */
    public const APP_URL = 'https://pathlyhq.com';

    /** @var Platform */
    private $platform;

    /** @var Client */
    private $client;

    /** @var Lumi */
    private $lumi;

    public function __construct(Platform $platform, Client $client, Lumi $lumi)
    {
        $this->platform = $platform;
        $this->client   = $client;
        $this->lumi     = $lumi;
    }

    /** Link to create a free account, with the source of the sign-up. */
    public function signupUrl(): string
    {
        return self::APP_URL . '/' . $this->platform->language()
            . '/login?mode=signup&ref=joomla&utm_source=joomla&utm_medium=module';
    }

    /** Forgets the cached view. */
    public function clear(): void
    {
        $this->platform->cacheSet(self::CACHE_KEY, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function view(): array
    {
        $settings = $this->platform->settings();
        $state    = $this->platform->state();
        $base     = [
            'configured'  => $settings['api_key'] !== '',
            'connected'   => !empty($state['connected']),
            'error'       => $state['last_error'] ?? null,
            'signupUrl'   => $this->signupUrl(),
            'settingsUrl' => 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . $settings['extension_id'],
            'appUrl'      => self::APP_URL . '/dashboard',
            'store'       => $this->lumi->store(),
        ];
        if (!$base['connected']) {
            return $base;
        }

        $cached = $this->platform->cacheGet(self::CACHE_KEY);
        if ($cached !== null && $this->platform->now() - (int) ($cached['at'] ?? 0) < self::CACHE_TTL) {
            return (array) $cached['view'];
        }

        $view = $base + [
            'organization' => (string) ($state['organization'] ?? ''),
            'space'        => $state['space'] ?? null,
            'badge'        => $settings['badge'] && !empty($state['badge_url']),
            'lastReplayAt' => (int) ($state['last_replay_at'] ?? 0),
        ];
        try {
            $view = $this->read($view, $state);
        } catch (ApiException $e) {
            $view['error'] = Notices::fromApi($e);

            return $view;
        }
        if (($view['trial']['status'] ?? '') !== 'pending') {
            $this->platform->cacheSet(self::CACHE_KEY, ['at' => $this->platform->now(), 'view' => $view]);
        }

        return $view;
    }

    /**
     * @param array<string, mixed> $view
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    private function read(array $view, array $state): array
    {
        $account         = $this->client->account();
        $view['plan']    = (string) ($account['plan']['name'] ?? '');
        $view['billing'] = (string) ($account['billingUrl'] ?? self::APP_URL . '/billing');
        $view['quotas']  = [];
        foreach (['ping', 'flow', 'runs', 'assistantTrials'] as $name) {
            if (isset($account['quotas'][$name]) && \is_array($account['quotas'][$name])) {
                $view['quotas'][$name] = [
                    'used'  => (int) ($account['quotas'][$name]['used'] ?? 0),
                    'limit' => isset($account['quotas'][$name]['limit']) ? (int) $account['quotas'][$name]['limit'] : null,
                ];
            }
        }

        $view['ping']    = $this->scenario((string) ($state['ping_id'] ?? ''));
        $view['flow']    = $this->scenario((string) ($state['flow_id'] ?? ''));
        $view['failure'] = $this->lastFailure($view['flow']) ?? $this->lastFailure($view['ping']);
        $view['trial']   = $this->lumi->view();

        return $view;
    }

    /**
     * @return array{id: string, name: string, status: string, url: string}|null
     *
     * @throws ApiException
     */
    private function scenario(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        try {
            $item = $this->client->getScenario($id);
        } catch (ApiException $e) {
            if ($e->status() === 404) {
                return null;
            }
            throw $e;
        }

        return [
            'id'     => $id,
            'name'   => (string) ($item['name'] ?? ''),
            'status' => (string) ($item['lastStatus'] ?? 'pending'),
            'url'    => self::APP_URL . '/monitors/' . rawurlencode($id),
        ];
    }

    /**
     * Last failed run of a scenario, with the signed link of its screenshot.
     *
     * @param array{id: string, name: string}|null $scenario
     *
     * @return array{at: string, message: string, screenshotUrl: string, scenario: string}|null
     *
     * @throws ApiException
     */
    private function lastFailure(?array $scenario): ?array
    {
        if ($scenario === null) {
            return null;
        }
        $runs = $this->client->listRuns($scenario['id']);
        foreach ((array) ($runs['items'] ?? []) as $run) {
            if (($run['status'] ?? 'ok') !== 'ok' && isset($run['id'])) {
                $detail = $this->client->getRun((string) $run['id']);

                return [
                    'at'            => (string) ($detail['created_at'] ?? $run['created_at'] ?? ''),
                    'message'       => (string) ($detail['message'] ?? ''),
                    'screenshotUrl' => (string) ($detail['screenshotUrl'] ?? ''),
                    'scenario'      => $scenario['name'],
                ];
            }
        }

        return null;
    }
}
