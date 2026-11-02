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
 * Client of the public Pathly API (`/v1`). One method per route used by the
 * extension, so the whole contract can be read in this file.
 *
 * Scopes needed by the key: org:read, scenarios:read, scenarios:write,
 * runs:read, runs:trigger, assistant:run, assistant:save, status:read,
 * status:write.
 */
final class Client
{
    /** The only host the extension ever calls. */
    public const BASE_URL = 'https://api.pathlyhq.com';

    /** Default timeout, in seconds. */
    public const TIMEOUT = 10;

    /** @var Transport */
    private $transport;

    /** @var string */
    private $key;

    /** @var string */
    private $userAgent;

    /** @var string */
    private $base;

    public function __construct(Transport $transport, string $key, string $userAgent, string $base = self::BASE_URL)
    {
        $this->transport = $transport;
        $this->key       = $key;
        $this->userAgent = $userAgent;
        $this->base      = rtrim($base, '/');
    }

    /**
     * API address. The override (environment variable `PATHLY_API_BASE`) is
     * only accepted for a local test host, so a site never sends its key
     * anywhere but to Pathly.
     */
    public static function resolveBase(?string $override): string
    {
        $override = trim((string) $override);
        if ($override === '') {
            return self::BASE_URL;
        }
        $parts = parse_url($override);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        $local = \in_array($host, ['localhost', '127.0.0.1'], true)
            || preg_match('/\.(test|localhost)$/', $host) === 1
            || preg_match('/^[a-z0-9-]+$/', $host) === 1;

        return $local && \in_array($parts['scheme'] ?? '', ['http', 'https'], true) ? $override : self::BASE_URL;
    }

    /**
     * Whether a string looks like a Pathly API key.
     */
    public static function looksLikeKey(string $key): bool
    {
        return preg_match('/^sp_[A-Za-z0-9_-]{20,200}$/', $key) === 1;
    }

    /** `GET /v1/account` (org:read). */
    public function account(): array
    {
        return $this->call('GET', '/v1/account');
    }

    /** `GET /v1/scenarios` (scenarios:read), one page. */
    public function listScenarios(string $cursor = ''): array
    {
        $query = ['limit' => 100];
        if ($cursor !== '') {
            $query['cursor'] = $cursor;
        }

        return $this->call('GET', '/v1/scenarios?' . http_build_query($query));
    }

    /** `GET /v1/scenarios/{id}` (scenarios:read). */
    public function getScenario(string $id): array
    {
        return $this->call('GET', '/v1/scenarios/' . rawurlencode($id));
    }

    /** `POST /v1/scenarios` (scenarios:write), replayed for 24 h with the same idempotency key. */
    public function createScenario(array $body, string $idempotencyKey): array
    {
        return $this->call('POST', '/v1/scenarios', $body, ['Idempotency-Key' => $idempotencyKey]);
    }

    /** `POST /v1/scenarios/{id}/run` (runs:trigger). */
    public function runScenario(string $id): array
    {
        return $this->call('POST', '/v1/scenarios/' . rawurlencode($id) . '/run', []);
    }

    /** `GET /v1/runs?scenarioId=` (runs:read). */
    public function listRuns(string $scenarioId, int $limit = 20): array
    {
        return $this->call('GET', '/v1/runs?' . http_build_query(['scenarioId' => $scenarioId, 'limit' => $limit]));
    }

    /** `GET /v1/runs/{id}` (runs:read), with the signed link of the failed step screenshot. */
    public function getRun(string $id): array
    {
        return $this->call('GET', '/v1/runs/' . rawurlencode($id));
    }

    /** `POST /v1/flow-assistant/detect` (assistant:run). Does not use a trial. */
    public function detect(string $url): array
    {
        return $this->call('POST', '/v1/flow-assistant/detect', ['url' => $url]);
    }

    /** `POST /v1/flow-assistant/trials` (assistant:run). Answers 202 `{id}`. */
    public function startTrial(string $url): array
    {
        return $this->call('POST', '/v1/flow-assistant/trials', ['url' => $url, 'confirmOwner' => true]);
    }

    /** `GET /v1/flow-assistant/trials/{id}` (assistant:run). */
    public function getTrial(string $id): array
    {
        return $this->call('GET', '/v1/flow-assistant/trials/' . rawurlencode($id));
    }

    /** `POST /v1/flow-assistant/trials/{id}/save` (assistant:save). */
    public function saveTrial(string $id, int $index, string $name): array
    {
        return $this->call(
            'POST',
            '/v1/flow-assistant/trials/' . rawurlencode($id) . '/save',
            ['index' => $index, 'name' => $name]
        );
    }

    /** `POST /v1/status-pages` (status:write): creates the page, or returns the existing one. */
    public function createStatusPage(array $body): array
    {
        return $this->call('POST', '/v1/status-pages', $body);
    }

    /** `PATCH /v1/status-pages/{id}` (status:write): publishes or unpublishes. */
    public function patchStatusPage(string $id, array $body): array
    {
        return $this->call('PATCH', '/v1/status-pages/' . rawurlencode($id), $body);
    }

    /**
     * @param array<string, mixed>|null $body    JSON body.
     * @param array<string, string>     $headers Extra headers.
     *
     * @throws ApiException
     */
    private function call(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $headers = array_merge(
            [
                'Authorization' => 'Bearer ' . $this->key,
                'Accept'        => 'application/json',
                'User-Agent'    => $this->userAgent,
            ],
            $headers
        );
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload                 = json_encode($body === [] ? new \stdClass() : $body);
        }

        $answer  = $this->transport->send($method, $this->base . $path, $headers, $payload, self::TIMEOUT);
        $decoded = json_decode($answer->body, true);
        $data    = \is_array($decoded) ? $decoded : [];

        if ($answer->status < 200 || $answer->status >= 300) {
            throw new ApiException(
                $answer->status,
                \is_string($data['code'] ?? null) ? $data['code'] : '',
                \is_string($data['error'] ?? null) ? $data['error'] : 'HTTP ' . $answer->status,
                max(0, (int) $answer->header('Retry-After')),
                $data
            );
        }

        return $data;
    }
}
