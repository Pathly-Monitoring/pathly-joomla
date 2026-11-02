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
 * Lumi, the Pathly Flow assistant, prepares the Flow of the site. Lumi
 * recognises Joomla (contact form and login templates). For a VirtueMart or
 * HikaShop store there is no cart template: Lumi then hands over to its AI.
 * Every proposal is replayed once before it is shown, and the administrator
 * saves the one to keep.
 */
final class Lumi
{
    /** Trial states that are still running. */
    public const PENDING = ['waiting', 'exploring', 'queued', 'replaying'];

    /** Store components Lumi has no cart template for. */
    public const STORES = ['com_virtuemart' => 'VirtueMart', 'com_hikashop' => 'HikaShop'];

    /** @var Platform */
    private $platform;

    /** @var Client */
    private $client;

    public function __construct(Platform $platform, Client $client)
    {
        $this->platform = $platform;
        $this->client   = $client;
    }

    /** Name of the store component installed on the site, or ''. */
    public function store(): string
    {
        foreach (self::STORES as $element => $name) {
            if ($this->platform->componentEnabled($element)) {
                return $name;
            }
        }

        return '';
    }

    /**
     * Asks Lumi to look at the site.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function start(): array
    {
        $state = $this->platform->state();
        if (empty($state['connected'])) {
            return [Notices::make('warning', 'NOT_CONNECTED')];
        }
        $site = (string) $state['site_url'];
        try {
            $detect = $this->client->detect($site);
            if (empty($detect['available'])) {
                return [self::unavailable((string) ($detect['reason'] ?? ''))];
            }
            $trial = $this->client->startTrial($site);
        } catch (ApiException $e) {
            return [Notices::fromApi($e)];
        }

        $state['trial_id']   = (string) ($trial['id'] ?? '');
        $state['trial_kind'] = (string) ($detect['siteKind'] ?? 'other');
        $state['trial_ai']   = !empty($detect['ai']);
        $this->platform->saveState($state);

        $notices = [Notices::make('info', 'LUMI_STARTED')];
        $store   = $this->store();
        if ($store !== '') {
            $notices[] = Notices::make('info', 'LUMI_STORE_AI', [$store]);
        }

        return $notices;
    }

    /**
     * State of the current trial, or null without a trial.
     *
     * @return array{status: string, reason: string, siteKind: string, proposals: list<array<string, mixed>>}|null
     *
     * @throws ApiException
     */
    public function view(): ?array
    {
        $id = (string) ($this->platform->state()['trial_id'] ?? '');
        if ($id === '') {
            return null;
        }
        $answer = $this->client->getTrial($id);
        $item   = (array) ($answer['item'] ?? []);
        $status = (string) ($item['status'] ?? 'failed');
        $order  = $this->store() !== '' ? ['cart', 'contact', 'login'] : ['contact', 'login', 'cart'];

        $proposals = [];
        foreach ((array) ($item['proposals'] ?? []) as $proposal) {
            $kind        = (string) ($proposal['kind'] ?? 'custom');
            $rank        = array_search($kind, $order, true);
            $proposals[] = [
                'index'         => (int) ($proposal['index'] ?? 0),
                'kind'          => $kind,
                'source'        => (string) ($proposal['source'] ?? 'template'),
                'steps'         => \count((array) ($proposal['steps'] ?? [])),
                'stoppedBefore' => (string) ($proposal['stoppedBefore'] ?? ''),
                'rank'          => $rank === false ? \count($order) : $rank,
            ];
        }
        usort($proposals, static function (array $a, array $b): int {
            return $a['rank'] <=> $b['rank'] ?: $a['index'] <=> $b['index'];
        });

        return [
            'status'    => \in_array($status, self::PENDING, true) ? 'pending' : $status,
            'reason'    => (string) ($item['reason'] ?? ''),
            'siteKind'  => (string) ($item['siteKind'] ?? 'other'),
            'proposals' => $proposals,
        ];
    }

    /**
     * Saves one proposal as the Flow of the site.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function save(int $index): array
    {
        $state = $this->platform->state();
        $id    = (string) ($state['trial_id'] ?? '');
        if ($id === '') {
            return [Notices::make('warning', 'LUMI_NO_TRIAL')];
        }
        $name = 'Flow · ' . Connection::normalize((string) ($state['site_url'] ?? ''));
        try {
            $saved  = $this->client->saveTrial($id, $index, $name);
            $flowId = (string) ($saved['item']['id'] ?? '');
        } catch (ApiException $e) {
            if ($e->apiCode() !== 'already_saved') {
                return [Notices::fromApi($e)];
            }
            $flowId = (string) ($e->payload()['monitorId'] ?? '');
        }

        $state['flow_id'] = $flowId;
        unset($state['trial_id'], $state['trial_kind'], $state['trial_ai']);
        $this->platform->saveState($state);

        return [Notices::make('success', 'FLOW_SAVED')];
    }

    /**
     * Forgets the current trial.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function cancel(): array
    {
        $state = $this->platform->state();
        unset($state['trial_id'], $state['trial_kind'], $state['trial_ai']);
        $this->platform->saveState($state);

        return [];
    }

    /**
     * Message when Lumi cannot start (`reason` of the detection).
     *
     * @return array{level: string, key: string, args: list<scalar>}
     */
    public static function unavailable(string $reason): array
    {
        $keys = [
            'off'         => 'ERR_ASSISTANT_OFF',
            'quota'       => 'ERR_ASSISTANT_QUOTA',
            'url_blocked' => 'ERR_URL_BLOCKED',
            'unreachable' => 'ERR_UNREACHABLE',
        ];

        return Notices::make('warning', $keys[$reason] ?? 'ERR_ASSISTANT_OFF');
    }
}
