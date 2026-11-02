<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Api;

use Joomla\CMS\Http\HttpFactory;

\defined('_JEXEC') or die;

/**
 * Transport over the Joomla HTTP client (cURL or streams, as configured by the site).
 */
final class JoomlaTransport implements Transport
{
    /** @var callable */
    private $httpFactory;

    /**
     * @param callable|null $httpFactory Returns a Joomla HTTP client. Tests pass their own.
     */
    public function __construct(?callable $httpFactory = null)
    {
        $this->httpFactory = $httpFactory ?? static function () {
            // Redirects are not followed: the API never redirects, and the key must not leave it.
            return HttpFactory::getHttp(['follow_location' => false]);
        };
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): Response
    {
        try {
            $http = ($this->httpFactory)();
            if ($method === 'GET') {
                $answer = $http->get($url, $headers, $timeout);
            } elseif ($method === 'PATCH') {
                $answer = $http->patch($url, (string) $body, $headers, $timeout);
            } else {
                $answer = $http->post($url, (string) $body, $headers, $timeout);
            }
        } catch (\Throwable $e) {
            // The message of the transport may carry the address: never shown, never logged.
            throw new ApiException(0, 'network', 'Pathly cannot be reached.');
        }

        $flat = [];
        foreach ($answer->getHeaders() as $name => $values) {
            $flat[(string) $name] = implode(', ', (array) $values);
        }

        return new Response((int) $answer->getStatusCode(), (string) $answer->getBody(), $flat);
    }
}
