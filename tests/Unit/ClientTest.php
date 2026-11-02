<?php

namespace Pathly\Tests\Unit;

use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Api\Client;
use Pathly\Plugin\System\Pathly\Api\Response;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const KEY = 'sp_abcdefghijklmnopqrstuvwxyz012345';

    private function client(FakeTransport $t): Client
    {
        return new Client($t, self::KEY, 'pathly-joomla/0.1.0 Joomla/5.2.1');
    }

    public function testEveryRouteOfTheContract(): void
    {
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, ['plan' => ['id' => 'solo']])
            ->on('GET /v1/scenarios', 200, ['items' => []])
            ->on('GET /v1/scenarios/a%2Fb', 200, ['id' => 'a/b'])
            ->on('POST /v1/scenarios', 201, ['id' => 'p1'])
            ->on('POST /v1/scenarios/p1/run', 200, ['ok' => true])
            ->on('GET /v1/runs', 200, ['items' => []])
            ->on('GET /v1/runs/r1', 200, ['id' => 'r1'])
            ->on('POST /v1/flow-assistant/detect', 200, ['available' => true])
            ->on('POST /v1/flow-assistant/trials', 202, ['id' => 't1'])
            ->on('GET /v1/flow-assistant/trials/t1', 200, ['item' => []])
            ->on('POST /v1/flow-assistant/trials/t1/save', 201, ['item' => ['id' => 'f1']])
            ->on('POST /v1/status-pages', 201, ['id' => 's1'])
            ->on('PATCH /v1/status-pages/s1', 200, ['id' => 's1']);
        $c = $this->client($t);

        $this->assertSame('solo', $c->account()['plan']['id']);
        $c->listScenarios();
        $c->listScenarios('next');
        $c->getScenario('a/b');
        $c->createScenario(['name' => 'Ping'], 'joomla-ping-x');
        $c->runScenario('p1');
        $c->listRuns('p1', 5);
        $c->getRun('r1');
        $c->detect('https://example.test');
        $this->assertSame('t1', $c->startTrial('https://example.test')['id']);
        $c->getTrial('t1');
        $c->saveTrial('t1', 2, 'Flow · example.test');
        $c->createStatusPage(['public' => true]);
        $c->patchStatusPage('s1', ['public' => false]);

        $r = $t->requests;
        $this->assertSame('https://api.pathlyhq.com/v1/account', $r[0]['url']);
        $this->assertSame('Bearer ' . self::KEY, $r[0]['headers']['Authorization']);
        $this->assertSame('application/json', $r[0]['headers']['Accept']);
        $this->assertSame('pathly-joomla/0.1.0 Joomla/5.2.1', $r[0]['headers']['User-Agent']);
        $this->assertArrayNotHasKey('Content-Type', $r[0]['headers']);
        $this->assertNull($r[0]['body']);
        $this->assertSame(10, $r[0]['timeout']);
        $this->assertSame('https://api.pathlyhq.com/v1/scenarios?limit=100', $r[1]['url']);
        $this->assertSame('https://api.pathlyhq.com/v1/scenarios?limit=100&cursor=next', $r[2]['url']);
        $this->assertSame('joomla-ping-x', $r[4]['headers']['Idempotency-Key']);
        $this->assertSame('application/json', $r[4]['headers']['Content-Type']);
        $this->assertSame('{}', $r[5]['raw'], 'an empty body is a JSON object');
        $this->assertSame('https://api.pathlyhq.com/v1/runs?scenarioId=p1&limit=5', $r[6]['url']);
        $this->assertSame(['url' => 'https://example.test'], $r[8]['body']);
        $this->assertSame(['url' => 'https://example.test', 'confirmOwner' => true], $r[9]['body']);
        $this->assertSame(['index' => 2, 'name' => 'Flow · example.test'], $r[11]['body']);
        $this->assertSame('PATCH', $r[13]['method']);
    }

    public function testRefusalCarriesCodeMessageAndRetryAfter(): void
    {
        $t = (new FakeTransport())->on(
            'POST /v1/flow-assistant/trials',
            429,
            ['error' => 'Très demandé', 'code' => 'assistant_busy'],
            ['Retry-After' => '60']
        );
        try {
            $this->client($t)->startTrial('https://example.test');
            $this->fail('expected a refusal');
        } catch (ApiException $e) {
            $this->assertSame(429, $e->status());
            $this->assertSame(429, $e->getCode());
            $this->assertSame('assistant_busy', $e->apiCode());
            $this->assertSame('Très demandé', $e->getMessage());
            $this->assertSame(60, $e->retryAfter());
            $this->assertSame('assistant_busy', $e->payload()['code']);
        }
    }

    public function testRefusalWithoutJsonBody(): void
    {
        $t = (new FakeTransport())->on('GET /v1/account', 502, '<html>Bad gateway</html>');
        try {
            $this->client($t)->account();
            $this->fail('expected a refusal');
        } catch (ApiException $e) {
            $this->assertSame(502, $e->status());
            $this->assertSame('', $e->apiCode());
            $this->assertSame('HTTP 502', $e->getMessage());
            $this->assertSame(0, $e->retryAfter());
            $this->assertSame([], $e->payload());
        }
    }

    public function testInformationalStatusIsARefusal(): void
    {
        $t = (new FakeTransport())->on('GET /v1/account', 101, '');
        $this->expectException(ApiException::class);
        $this->client($t)->account();
    }

    public function testSuccessWithoutJsonGivesAnEmptyArray(): void
    {
        $t = (new FakeTransport())->on('GET /v1/account', 204, '');
        $this->assertSame([], $this->client($t)->account());
    }

    public function testBaseIsOnlyOverriddenForALocalTestHost(): void
    {
        $this->assertSame(Client::BASE_URL, Client::resolveBase(null));
        $this->assertSame(Client::BASE_URL, Client::resolveBase(' '));
        $this->assertSame('http://pathly-mock:8080', Client::resolveBase('http://pathly-mock:8080'));
        $this->assertSame('http://localhost:9000', Client::resolveBase('http://localhost:9000'));
        $this->assertSame('http://127.0.0.1:9000', Client::resolveBase('http://127.0.0.1:9000'));
        $this->assertSame('https://api.pathly.test', Client::resolveBase('https://api.pathly.test'));
        $this->assertSame('http://mock.localhost', Client::resolveBase('http://mock.localhost'));
        $this->assertSame(Client::BASE_URL, Client::resolveBase('https://evil.example.com'));
        $this->assertSame(Client::BASE_URL, Client::resolveBase('ftp://pathly-mock'));
        $this->assertSame(Client::BASE_URL, Client::resolveBase('not a url'));

        $t = (new FakeTransport())->on('GET /v1/account', 200, []);
        (new Client($t, self::KEY, 'ua', 'http://pathly-mock:8080/'))->account();
        $this->assertSame('http://pathly-mock:8080/v1/account', $t->last()['url']);
    }

    public function testKeyFormat(): void
    {
        $this->assertTrue(Client::looksLikeKey(self::KEY));
        $this->assertFalse(Client::looksLikeKey('sp_short'));
        $this->assertFalse(Client::looksLikeKey('sk_abcdefghijklmnopqrstuvwxyz012345'));
        $this->assertFalse(Client::looksLikeKey('sp_abcdefghijklmnopqrstuvwxyz 012345'));
    }

    public function testResponseHeadersAreCaseInsensitive(): void
    {
        $r = new Response(200, '', ['Retry-After' => '5']);
        $this->assertSame('5', $r->header('retry-after'));
        $this->assertSame('', $r->header('X-Missing'));
    }
}
