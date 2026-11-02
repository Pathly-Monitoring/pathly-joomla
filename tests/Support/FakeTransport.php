<?php

namespace Pathly\Tests;

use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Api\Response;
use Pathly\Plugin\System\Pathly\Api\Transport;

/**
 * Simulated Pathly API: answers by "METHOD /path" (query string ignored),
 * records every request.
 */
final class FakeTransport implements Transport
{
    /** @var array<string, list<Response|ApiException>> */
    public $answers = [];

    /** @var list<array{method: string, url: string, headers: array, body: ?array, timeout: int}> */
    public $requests = [];

    /**
     * @param array<string, mixed>|string $body
     */
    public function on(string $route, int $status, $body = [], array $headers = []): self
    {
        $raw                     = \is_string($body) ? $body : (string) json_encode($body);
        $this->answers[$route][] = new Response($status, $raw, $headers);

        return $this;
    }

    public function fail(string $route): self
    {
        $this->answers[$route][] = new ApiException(0, 'network', 'Pathly cannot be reached.');

        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): Response
    {
        $this->requests[] = [
            'method'  => $method,
            'url'     => $url,
            'headers' => $headers,
            'body'    => $body === null ? null : json_decode($body, true),
            'raw'     => $body,
            'timeout' => $timeout,
        ];
        $path  = (string) parse_url($url, PHP_URL_PATH);
        $route = $method . ' ' . $path;
        if (empty($this->answers[$route])) {
            throw new \LogicException('No answer for ' . $route);
        }
        $answer = \count($this->answers[$route]) > 1 ? array_shift($this->answers[$route]) : $this->answers[$route][0];
        if ($answer instanceof ApiException) {
            throw $answer;
        }

        return $answer;
    }

    /** Routes called, in order. */
    public function routes(): array
    {
        return array_map(static function (array $r): string {
            return $r['method'] . ' ' . parse_url($r['url'], PHP_URL_PATH);
        }, $this->requests);
    }

    public function last(): array
    {
        return $this->requests[\count($this->requests) - 1];
    }
}
