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
 * Raw HTTP answer: status code, body and headers (lower-case names).
 */
final class Response
{
    /** @var int */
    public $status;

    /** @var string */
    public $body;

    /** @var array<string, string> */
    public $headers;

    /**
     * @param int                   $status  HTTP status code, 0 when the request did not complete.
     * @param string                $body    Body.
     * @param array<string, string> $headers Headers.
     */
    public function __construct(int $status, string $body, array $headers = [])
    {
        $this->status  = $status;
        $this->body    = $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    /**
     * Value of a header, or an empty string.
     */
    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }
}
