<?php

namespace Pathly\Tests\Unit;

use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Api\Client;
use Pathly\Plugin\System\Pathly\Service\Connection;
use Pathly\Plugin\System\Pathly\Service\Notices;
use Pathly\Tests\FakePlatform;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase
{
    private function make(FakePlatform $p, FakeTransport $t): Connection
    {
        return new Connection($p, new Client($t, $p->settings['api_key'], 'ua'));
    }

    public function testNoKeyNothingSent(): void
    {
        $p = new FakePlatform();
        $t = new FakeTransport();
        $c = $this->make($p, $t);
        $this->assertFalse($c->hasKey());
        $this->assertSame([Notices::make('warning', 'NO_KEY')], $c->connect());
        $this->assertSame([], $t->requests);
    }

    public function testMalformedKeyNothingSent(): void
    {
        $p = (new FakePlatform())->withKey('hello');
        $t = new FakeTransport();
        $c = $this->make($p, $t);
        $this->assertTrue($c->hasKey());
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_KEY_FORMAT', $c->connect()[0]['key']);
        $this->assertSame([], $t->requests);
        $this->assertFalse($p->state['connected']);
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_KEY_FORMAT', $p->state['last_error']['key']);
    }

    public function testCreatesThePingWhenNoneWatchesTheSite(): void
    {
        $p = (new FakePlatform())->withKey();
        $p->state = ['last_error' => ['x']];
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, ['organization' => ['name' => 'Studio'], 'space' => ['name' => 'Client A']])
            ->on('GET /v1/scenarios', 200, ['items' => [['id' => 'x', 'type' => 'browser', 'url' => 'https://example.test']], 'nextCursor' => 'c2'])
            ->on('GET /v1/scenarios', 200, ['items' => [['id' => 'y', 'type' => 'http', 'url' => 'https://other.test']]])
            ->on('POST /v1/scenarios', 201, ['id' => 'ping-new']);

        $notices = $this->make($p, $t)->connect();

        $this->assertSame([Notices::make('success', 'CONNECTED', ['Studio'])], $notices);
        $this->assertSame(['GET /v1/account', 'GET /v1/scenarios', 'GET /v1/scenarios', 'POST /v1/scenarios'], $t->routes());
        $body = $t->last()['body'];
        $this->assertSame(
            ['name' => 'Ping · example.test', 'type' => 'http', 'url' => 'https://www.example.test', 'intervalSec' => 300, 'tags' => ['joomla']],
            $body
        );
        $this->assertSame('joomla-ping-' . substr(hash('sha256', 'example.test'), 0, 40), $t->last()['headers']['Idempotency-Key']);
        $this->assertSame('ping-new', $p->state['ping_id']);
        $this->assertSame('Client A', $p->state['space']);
        $this->assertTrue($p->state['connected']);
        $this->assertArrayNotHasKey('last_error', $p->state);
    }

    public function testAttachesToAnExistingPingWithoutDuplicate(): void
    {
        $p = (new FakePlatform())->withKey();
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, ['organization' => ['name' => 'Studio']])
            ->on('GET /v1/scenarios', 200, ['items' => [['type' => 'http', 'url' => 'https://example.test/'], ['id' => 'ping-old', 'type' => 'http', 'url' => 'http://EXAMPLE.test/']]]);

        $this->make($p, $t)->connect();

        $this->assertNotContains('POST /v1/scenarios', $t->routes());
        $this->assertSame('ping-old', $p->state['ping_id']);
        $this->assertNull($p->state['space']);
    }

    public function testKeepsTheKnownPing(): void
    {
        $p = (new FakePlatform())->withKey();
        $p->state = ['ping_id' => 'ping-1'];
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/scenarios/ping-1', 200, ['id' => 'ping-1']);

        $this->make($p, $t)->connect();
        $this->assertSame(['GET /v1/account', 'GET /v1/scenarios/ping-1'], $t->routes());
        $this->assertSame('', $p->state['organization']);
    }

    public function testForgetsADeletedPing(): void
    {
        $p = (new FakePlatform())->withKey();
        $p->state = ['ping_id' => 'gone'];
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/scenarios/gone', 404, ['error' => 'Introuvable'])
            ->on('GET /v1/scenarios', 200, ['items' => []])
            ->on('POST /v1/scenarios', 201, []);

        $this->make($p, $t)->connect();
        $this->assertSame('', $p->state['ping_id']);
    }

    public function testStopsAfterTenPages(): void
    {
        $p = (new FakePlatform())->withKey();
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/scenarios', 200, ['items' => [], 'nextCursor' => 'more'])
            ->on('POST /v1/scenarios', 201, ['id' => 'p']);
        $this->make($p, $t)->connect();
        $this->assertSame(Connection::MAX_PAGES, \count(array_keys($t->routes(), 'GET /v1/scenarios', true)));
    }

    public function testRefusalIsKeptForTheDashboard(): void
    {
        $p = (new FakePlatform())->withKey();
        $t = (new FakeTransport())->on('GET /v1/account', 401, ['error' => 'Clé inconnue']);
        $notices = $this->make($p, $t)->connect();
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_KEY', $notices[0]['key']);
        $this->assertFalse($p->state['connected']);
    }

    public function testOtherErrorOnKnownPingIsRaised(): void
    {
        $p = (new FakePlatform())->withKey();
        $p->state = ['ping_id' => 'ping-1'];
        $t = (new FakeTransport())->on('GET /v1/scenarios/ping-1', 500, []);
        $this->expectException(ApiException::class);
        $this->make($p, $t)->ensurePing('https://example.test');
    }

    public function testDisconnectForgetsKeyAndState(): void
    {
        $p = (new FakePlatform())->connected();
        $p->settings['badge'] = true;
        $notices = $this->make($p, new FakeTransport())->disconnect();
        $this->assertSame('PLG_SYSTEM_PATHLY_DISCONNECTED', $notices[0]['key']);
        $this->assertSame('', $p->settings['api_key']);
        $this->assertFalse($p->settings['badge']);
        $this->assertSame([], $p->state);
    }

    public function testNormalize(): void
    {
        $this->assertSame('example.test/shop', Connection::normalize(' HTTPS://www.Example.test/shop/ '));
        $this->assertSame('example.test', Connection::normalize('http://example.test'));
        $this->assertSame('', Connection::normalize('not an address'));
        $this->assertSame('', Connection::normalize('http:///x'));
    }
}
