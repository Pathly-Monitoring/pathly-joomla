<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Api;

\defined('_JEXEC') or die;

/**
 * Sends one HTTP request. The only seam between the extension and the network.
 */
interface Transport
{
    /**
     * @param string                $method  GET, POST or PATCH.
     * @param string                $url     Absolute address.
     * @param array<string, string> $headers Headers.
     * @param string|null           $body    JSON body, or null.
     * @param int                   $timeout Seconds.
     *
     * @throws ApiException When the request does not complete (status 0).
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): Response;
}
