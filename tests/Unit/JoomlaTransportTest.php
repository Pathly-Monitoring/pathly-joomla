<?php

namespace Pathly\Tests\Unit;

use Joomla\CMS\Http\HttpFactory;
use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Api\JoomlaTransport;
use Pathly\Tests\FakeHttp;
use Pathly\Tests\FakeHttpResponse;
use PHPUnit\Framework\TestCase;

final class JoomlaTransportTest extends TestCase
{
    public function testMethodsAndAnswer(): void
    {
        $http           = new FakeHttp();
        $http->response = new FakeHttpResponse(201, '{"id":"x"}', ['Retry-After' => ['3'], 'X-Two' => ['a', 'b']]);
        $transport      = new JoomlaTransport(static function () use ($http) {
            return $http;
        });

        $answer = $transport->send('GET', 'https://api.pathlyhq.com/v1/account', ['A' => 'b'], null, 10);
        $transport->send('POST', 'https://api.pathlyhq.com/v1/scenarios', [], '{"a":1}', 10);
        $transport->send('PATCH', 'https://api.pathlyhq.com/v1/status-pages/1', [], null, 7);

        $this->assertSame(201, $answer->status);
        $this->assertSame('{"id":"x"}', $answer->body);
        $this->assertSame('3', $answer->header('retry-after'));
        $this->assertSame('a, b', $answer->header('x-two'));
        $this->assertSame(['get', ['https://api.pathlyhq.com/v1/account', ['A' => 'b'], 10]], $http->calls[0]);
        $this->assertSame('post', $http->calls[1][0]);
        $this->assertSame('{"a":1}', $http->calls[1][1][1]);
        $this->assertSame(['patch', ['https://api.pathlyhq.com/v1/status-pages/1', '', [], 7]], $http->calls[2]);
    }

    public function testNetworkErrorHidesTheTransportMessage(): void
    {
        $http        = new FakeHttp();
        $http->throw = true;
        $transport   = new JoomlaTransport(static function () use ($http) {
            return $http;
        });
        try {
            $transport->send('GET', 'https://api.pathlyhq.com/v1/account', [], null, 10);
            $this->fail('expected a network error');
        } catch (ApiException $e) {
            $this->assertSame(0, $e->status());
            $this->assertSame('network', $e->apiCode());
            $this->assertStringNotContainsString('resolve', $e->getMessage());
        }
    }

    public function testDefaultFactoryUsesJoomlaWithoutRedirects(): void
    {
        HttpFactory::$http = new FakeHttp();
        (new JoomlaTransport())->send('GET', 'https://api.pathlyhq.com/v1/account', [], null, 10);
        $this->assertSame(['follow_location' => false], HttpFactory::$options);
        $this->assertCount(1, HttpFactory::$http->calls);
    }
}
