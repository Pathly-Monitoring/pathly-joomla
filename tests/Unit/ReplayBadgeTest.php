<?php

namespace Pathly\Tests\Unit;

use Pathly\Plugin\System\Pathly\Api\Client;
use Pathly\Plugin\System\Pathly\Service\Badge;
use Pathly\Plugin\System\Pathly\Service\Replay;
use Pathly\Tests\FakePlatform;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ReplayBadgeTest extends TestCase
{
    private function client(FakeTransport $t): Client
    {
        return new Client($t, 'sp_abcdefghijklmnopqrstuvwxyz012345', 'ua');
    }

    public function testNothingToReplayBeforeConfiguration(): void
    {
        $p = new FakePlatform();
        $r = new Replay($p, $this->client(new FakeTransport()));
        $this->assertFalse($r->request('extension'));
        $this->assertSame([], $p->deferred);

        $p->state = ['ping_id' => 'ping-1'];
        $this->assertFalse($r->request('extension'), 'a Ping without key is not replayed');
        $this->assertSame([], $p->deferred);
    }

    public function testOneReplayPerRequestAfterTheUpdates(): void
    {
        $p = (new FakePlatform())->connected(['flow_id' => 'flow-1']);
        $t = (new FakeTransport())->on('POST /v1/scenarios/flow-1/run', 200, ['ok' => true]);
        $r = new Replay($p, $this->client($t));

        $this->assertTrue($r->request('extension'));
        $this->assertFalse($r->request('extension'), 'a batch of updates asks once');
        $this->assertSame([], $t->requests, 'nothing is sent before the end of the request');

        $p->runDeferred();
        $this->assertSame(['POST /v1/scenarios/flow-1/run'], $t->routes());
        $this->assertSame(1_800_000_000, $p->state['last_replay_at']);
        $this->assertSame('extension', $p->state['last_replay_reason']);
        $this->assertTrue($r->request('core'), 'a later request asks again');
    }

    public function testReplaysThePingWithoutFlow(): void
    {
        $p = (new FakePlatform())->connected(['last_error' => ['x']]);
        $t = (new FakeTransport())->on('POST /v1/scenarios/ping-1/run', 200, []);
        $notices = (new Replay($p, $this->client($t)))->flush('core');
        $this->assertSame('PLG_SYSTEM_PATHLY_REPLAY_QUEUED', $notices[0]['key']);
        $this->assertArrayNotHasKey('last_error', $p->state);
    }

    public function testFailedReplayIsKept(): void
    {
        $p = (new FakePlatform())->connected();
        $t = (new FakeTransport())->on('POST /v1/scenarios/ping-1/run', 403, ['error' => 'scope']);
        $notices = (new Replay($p, $this->client($t)))->flush('core');
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_SCOPE', $notices[0]['key']);
        $this->assertSame($notices[0], $p->state['last_error']);
    }

    public function testFlushWithoutTarget(): void
    {
        $notices = (new Replay(new FakePlatform(), $this->client(new FakeTransport())))->flush('manual');
        $this->assertSame('PLG_SYSTEM_PATHLY_NOT_CONNECTED', $notices[0]['key']);
    }

    public function testBadgeOffByDefault(): void
    {
        $p = (new FakePlatform())->connected(['badge_url' => 'old', 'status_url' => 'old']);
        $t = new FakeTransport();
        $b = new Badge($p, $this->client($t));
        $this->assertSame([], $b->sync());
        $this->assertSame([], $t->requests);
        $this->assertArrayNotHasKey('badge_url', $p->state);
        $this->assertSame('', $b->html('Status'));
    }

    public function testBadgeNeedsAConnection(): void
    {
        $p                    = (new FakePlatform())->withKey();
        $p->settings['badge'] = true;
        $t                    = new FakeTransport();
        $this->assertSame([], (new Badge($p, $this->client($t)))->sync());
        $this->assertSame([], $t->requests);
    }

    public function testBadgeOnPublishesTheStatusPage(): void
    {
        $p                    = (new FakePlatform())->connected();
        $p->settings['badge'] = true;
        $t                    = (new FakeTransport())->on('POST /v1/status-pages', 201, [
            'id'       => 'org-1',
            'url'      => 'https://pathlyhq.com/status/studio?a=1&b="2"',
            'badgeUrl' => 'https://api.pathlyhq.com/public/status/studio/badge.svg',
        ]);
        $b = new Badge($p, $this->client($t));

        $this->assertSame('PLG_SYSTEM_PATHLY_BADGE_ON', $b->sync()[0]['key']);
        $this->assertSame(['public' => true], $t->last()['body']);
        $this->assertSame('org-1', $p->state['status_page_id']);
        $this->assertSame(
            '<a class="pathly-badge" href="https://pathlyhq.com/status/studio?a=1&amp;b=&quot;2&quot;" target="_blank" rel="noopener">'
            . '<img src="https://api.pathlyhq.com/public/status/studio/badge.svg" alt="État &lt;site&gt;" height="20" loading="lazy"></a>',
            $b->html('État <site>')
        );

        $p->settings['badge'] = false;
        $this->assertSame('', $b->html('Status'), 'switched off: nothing shown');
    }

    public function testBadgeRefused(): void
    {
        $p                    = (new FakePlatform())->connected();
        $p->settings['badge'] = true;
        $t                    = (new FakeTransport())->on('POST /v1/status-pages', 403, []);
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_SCOPE', (new Badge($p, $this->client($t)))->sync()[0]['key']);
    }

    public function testBadgeWithIncompleteAnswer(): void
    {
        $p                    = (new FakePlatform())->connected();
        $p->settings['badge'] = true;
        $t                    = (new FakeTransport())->on('POST /v1/status-pages', 201, []);
        $b                    = new Badge($p, $this->client($t));
        $b->sync();
        $this->assertSame('', $b->html('x'));
        $p->state['badge_url'] = 'https://api.pathlyhq.com/b.svg';
        unset($p->state['status_url']);
        $this->assertStringContainsString('href=""', $b->html('x'));
    }
}
