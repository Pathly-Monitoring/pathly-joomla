<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Service;

use Pathly\Plugin\System\Pathly\Api\ApiException;

\defined('_JEXEC') or die;

/**
 * Messages shown to the administrator, as language keys. The services return
 * them, the plugin translates them.
 */
final class Notices
{
    public const PREFIX = 'PLG_SYSTEM_PATHLY_';

    /**
     * @param list<scalar> $args
     *
     * @return array{level: string, key: string, args: list<scalar>}
     */
    public static function make(string $level, string $key, array $args = []): array
    {
        return ['level' => $level, 'key' => self::PREFIX . $key, 'args' => $args];
    }

    /**
     * Message for a refusal of the API. Never the raw message of the API,
     * which is in French only.
     *
     * @return array{level: string, key: string, args: list<scalar>}
     */
    public static function fromApi(ApiException $e): array
    {
        $code   = $e->apiCode();
        $status = $e->status();
        $known  = [
            'assistant_off'   => 'ERR_ASSISTANT_OFF',
            'assistant_quota' => 'ERR_ASSISTANT_QUOTA',
            'PLAN_LIMIT'      => 'ERR_PLAN_LIMIT',
            'url_blocked'     => 'ERR_URL_BLOCKED',
            'already_saved'   => 'ERR_ALREADY_SAVED',
        ];
        if (isset($known[$code])) {
            return self::make('error', $known[$code]);
        }
        if ($status === 429) {
            return self::make('warning', 'ERR_BUSY', [max(1, $e->retryAfter())]);
        }
        $byStatus = [0 => 'ERR_NETWORK', 401 => 'ERR_KEY', 403 => 'ERR_SCOPE', 404 => 'ERR_NOT_FOUND'];

        return self::make('error', $byStatus[$status] ?? ($status >= 500 ? 'ERR_SERVER' : 'ERR_REQUEST'));
    }
}
