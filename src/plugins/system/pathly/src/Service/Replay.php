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
 * Replays the Flow (or the Ping, without a Flow) after an update of Joomla
 * or of an extension.
 *
 * Updating several extensions at once happens in one request: the replay is
 * asked once, at the end of the request, when every update is installed.
 */
final class Replay
{
    /** @var Platform */
    private $platform;

    /** @var Client */
    private $client;

    /** @var bool */
    private $pending = false;

    public function __construct(Platform $platform, Client $client)
    {
        $this->platform = $platform;
        $this->client   = $client;
    }

    /**
     * Asks for a replay at the end of the request.
     *
     * @param string $reason `extension` or `core`.
     */
    public function request(string $reason): bool
    {
        if ($this->pending || $this->target() === '' || $this->platform->settings()['api_key'] === '') {
            return false;
        }
        $this->pending = true;
        $this->platform->defer(function () use ($reason) {
            $this->flush($reason);
        });

        return true;
    }

    /**
     * Triggers the run now.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function flush(string $reason): array
    {
        $this->pending = false;
        $id            = $this->target();
        if ($id === '') {
            return [Notices::make('warning', 'NOT_CONNECTED')];
        }
        $state = $this->platform->state();
        try {
            $this->client->runScenario($id);
        } catch (ApiException $e) {
            $state['last_error'] = Notices::fromApi($e);
            $this->platform->saveState($state);

            return [$state['last_error']];
        }
        $state['last_replay_at']     = $this->platform->now();
        $state['last_replay_reason'] = $reason;
        unset($state['last_error']);
        $this->platform->saveState($state);

        return [Notices::make('success', 'REPLAY_QUEUED')];
    }

    /** Scenario to replay: the Flow, else the Ping, else ''. */
    private function target(): string
    {
        $state = $this->platform->state();
        $flow  = (string) ($state['flow_id'] ?? '');

        return $flow !== '' ? $flow : (string) ($state['ping_id'] ?? '');
    }
}
