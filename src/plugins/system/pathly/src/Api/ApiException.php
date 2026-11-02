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
 * Refusal of the Pathly API, or a request that did not complete (status 0).
 */
final class ApiException extends \RuntimeException
{
    /** @var int */
    private $status;

    /** @var string */
    private $apiCode;

    /** @var int */
    private $retryAfter;

    /** @var array<string, mixed> */
    private $payload;

    /**
     * @param int                  $status     HTTP status, 0 for a network error.
     * @param string               $apiCode    Machine code sent by the API (`assistant_quota`…), or ''.
     * @param string               $message    Message sent by the API, never shown with a key in it.
     * @param int                  $retryAfter Seconds from `Retry-After`, 0 when absent.
     * @param array<string, mixed> $payload    Decoded body.
     */
    public function __construct(int $status, string $apiCode, string $message, int $retryAfter = 0, array $payload = [])
    {
        parent::__construct($message, $status);
        $this->status     = $status;
        $this->apiCode    = $apiCode;
        $this->retryAfter = $retryAfter;
        $this->payload    = $payload;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function apiCode(): string
    {
        return $this->apiCode;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
