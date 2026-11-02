<?php

namespace Pathly\Tests\Unit;

use Pathly\Plugin\System\Pathly\Api\Client;
use Pathly\Plugin\System\Pathly\Service\Dashboard;
use Pathly\Plugin\System\Pathly\Service\JoomlaPlatform;
use Pathly\Plugin\System\Pathly\Service\Lumi;
use Pathly\Plugin\System\Pathly\Service\Services;
use Pathly\Tests\FakePlatform;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class DashboardServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        Services::reset();
    }

    private function dashboard(FakePlatform $p, FakeTransport $t): Dashboard
    {
        $c = new Client($t, 'sp_abcdefghijklmnopqrstuvwxyz012345', 'ua');

        return new Dashboard($p, $c, new Lumi($p, $c));
    }

    private function account(): array
    {
        return [
            'organization' => ['name' => 'Studio'],
            'plan'         => ['id' => 'pro', 'name' => 'Pro', 'priceMonthlyEur' => 69],
            'billingUrl'   => 'https://pathlyhq.com/billing',
            'quotas'       => [
                'ping'            => ['used' => 3, 'limit' => 50, 'remaining' => 47],
                'flow'            => ['used' => 1, 'limit' => null, 'remaining' => null],
                'runs'            => ['used' => 12, 'limit' => 800],
                'assistantTrials' => ['limit' => 15],
                'chain'           => ['used' => 0, 'limit' => 20],
                'spaces'          => null,
            ],
        ];
    }

    public function testNotConfigured(): void
    {
        $p       = new FakePlatform();
        $p->lang = 'de';
        $view    = $this->dashboard($p, new FakeTransport())->view();
        $this->assertFalse($view['configured']);
        $this->assertFalse($view['connected']);
        $this->assertSame('https://pathlyhq.com/de/login?mode=signup&ref=joomla&utm_source=joomla&utm_medium=module', $view['signupUrl']);
        $this->assertSame('index.php?option=com_plugins&task=plugin.edit&extension_id=42', $view['settingsUrl']);
        $this->assertSame('', $view['store']);
    }

    public function testFullViewWithLastFailureAndScreenshot(): void
    {
        $p = (new FakePlatform())->connected(['flow_id' => 'flow-1', 'last_replay_at' => 5, 'space' => 'Client A']);
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, $this->account())
            ->on('GET /v1/scenarios/ping-1', 200, ['name' => 'Ping · example.test', 'lastStatus' => 'ok'])
            ->on('GET /v1/scenarios/flow-1', 200, ['name' => 'Flow · example.test', 'lastStatus' => 'fail'])
            ->on('GET /v1/runs', 200, ['items' => [['id' => 'r0', 'status' => 'ok'], ['status' => 'fail'], ['id' => 'r1', 'status' => 'fail', 'created_at' => 'list']]])
            ->on('GET /v1/runs/r1', 200, ['created_at' => '2026-11-02T10:00:00Z', 'message' => 'Bouton introuvable', 'screenshotUrl' => 'https://api.pathlyhq.com/public/run-shot/t']);
        $d = $this->dashboard($p, $t);

        $view = $d->view();

        $this->assertTrue($view['connected']);
        $this->assertSame('Studio', $view['organization']);
        $this->assertSame('Client A', $view['space']);
        $this->assertSame('Pro', $view['plan']);
        $this->assertSame(['used' => 3, 'limit' => 50], $view['quotas']['ping']);
        $this->assertSame(['used' => 1, 'limit' => null], $view['quotas']['flow']);
        $this->assertSame(['used' => 0, 'limit' => 15], $view['quotas']['assistantTrials']);
        $this->assertArrayNotHasKey('chain', $view['quotas']);
        $this->assertSame('ok', $view['ping']['status']);
        $this->assertSame('https://pathlyhq.com/monitors/flow-1', $view['flow']['url']);
        $this->assertSame(
            ['at' => '2026-11-02T10:00:00Z', 'message' => 'Bouton introuvable', 'screenshotUrl' => 'https://api.pathlyhq.com/public/run-shot/t', 'scenario' => 'Flow · example.test'],
            $view['failure']
        );
        $this->assertNull($view['trial']);
        $this->assertFalse($view['badge']);
        $this->assertSame(5, $view['lastReplayAt']);
        $this->assertStringContainsString('scenarioId=flow-1', $t->requests[3]['url']);

        // Cached for two minutes, then read again.
        $count = \count($t->requests);
        $this->assertSame($view, $d->view());
        $this->assertCount($count, $t->requests);
        $p->time += Dashboard::CACHE_TTL;
        $d->view();
        $this->assertGreaterThan($count, \count($t->requests));
        $d->clear();
        $this->assertNull($p->cache[Dashboard::CACHE_KEY]);
    }

    public function testFailureOfThePingWhenTheFlowIsGreen(): void
    {
        $p = (new FakePlatform())->connected(['flow_id' => 'gone']);
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/scenarios/ping-1', 200, [])
            ->on('GET /v1/scenarios/gone', 404, [])
            ->on('GET /v1/runs', 200, ['items' => [['id' => 'r2', 'status' => 'fail', 'created_at' => 'when']]])
            ->on('GET /v1/runs/r2', 200, []);

        $view = $this->dashboard($p, $t)->view();

        $this->assertNull($view['flow']);
        $this->assertSame('pending', $view['ping']['status']);
        $this->assertSame(['at' => 'when', 'message' => '', 'screenshotUrl' => '', 'scenario' => ''], $view['failure']);
        $this->assertSame('', $view['plan']);
        $this->assertSame('https://pathlyhq.com/billing', $view['billing']);
        $this->assertSame([], $view['quotas']);
    }

    public function testNoFailureAndPendingTrialIsNotCached(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1', 'ping_id' => '']);
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/flow-assistant/trials/t1', 200, ['item' => ['status' => 'queued']]);
        $view = $this->dashboard($p, $t)->view();
        $this->assertNull($view['ping']);
        $this->assertNull($view['failure']);
        $this->assertSame('pending', $view['trial']['status']);
        $this->assertArrayNotHasKey(Dashboard::CACHE_KEY, $p->cache);
    }

    public function testRunsWithoutFailure(): void
    {
        $p = (new FakePlatform())->connected();
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/scenarios/ping-1', 200, ['lastStatus' => 'ok'])
            ->on('GET /v1/runs', 200, ['items' => [['id' => 'r', 'status' => 'ok']]]);
        $this->assertNull($this->dashboard($p, $t)->view()['failure']);
    }

    public function testBadgeState(): void
    {
        $p                    = (new FakePlatform())->connected(['ping_id' => '']);
        $p->settings['badge'] = true;
        $t                    = (new FakeTransport())->on('GET /v1/account', 200, []);
        $d                    = $this->dashboard($p, $t);
        $this->assertFalse($d->view()['badge'], 'switched on, page not published yet');
        $d->clear();
        $p->state['badge_url'] = 'https://api.pathlyhq.com/b.svg';
        $this->assertTrue($d->view()['badge']);
    }

    public function testApiDownShowsTheError(): void
    {
        $p = (new FakePlatform())->connected();
        $t = (new FakeTransport())
            ->on('GET /v1/account', 200, [])
            ->on('GET /v1/scenarios/ping-1', 500, []);
        $view = $this->dashboard($p, $t)->view();
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_SERVER', $view['error']['key']);
        $this->assertArrayNotHasKey(Dashboard::CACHE_KEY, $p->cache);
    }

    public function testServicesAndActions(): void
    {
        $p                    = (new FakePlatform())->connected(['trial_id' => 't1']);
        $p->settings['badge'] = true;
        $p->env               = ['PATHLY_API_BASE' => 'http://pathly-mock:8080'];
        $t                    = (new FakeTransport())
            ->on('GET /v1/account', 200, ['organization' => ['name' => 'Studio']])
            ->on('GET /v1/scenarios/ping-1', 200, [])
            ->on('POST /v1/status-pages', 201, ['badgeUrl' => 'b', 'url' => 'u'])
            ->on('POST /v1/flow-assistant/detect', 200, ['available' => false, 'reason' => 'off'])
            ->on('POST /v1/flow-assistant/trials/t1/save', 201, ['item' => ['id' => 'f']])
            ->on('POST /v1/scenarios/f/run', 200, []);
        $s = new Services($p, $t);

        $p->cache[Dashboard::CACHE_KEY] = ['at' => 0, 'view' => []];
        $this->assertSame(['CONNECTED', 'BADGE_ON'], self::keys($s->act('connect', 0)));
        $this->assertSame('http://pathly-mock:8080/v1/account', $t->requests[0]['url']);
        $this->assertSame('pathly-joomla/0.1.0 Joomla/5.2.1', $t->requests[0]['headers']['User-Agent']);
        $this->assertNull($p->cache[Dashboard::CACHE_KEY]);

        $this->assertSame(['ERR_ASSISTANT_OFF'], self::keys($s->act('lumi_start', 0)));
        $this->assertSame(['FLOW_SAVED'], self::keys($s->act('lumi_save', 1)));
        $this->assertSame(1, $t->last()['body']['index']);
        $this->assertSame([], $s->act('lumi_cancel', 0));
        $this->assertSame(['REPLAY_QUEUED'], self::keys($s->act('run', 0)));
        $this->assertSame('manual', $p->state['last_replay_reason']);
        $p->cache[Dashboard::CACHE_KEY] = ['at' => 0, 'view' => []];
        $this->assertSame([], $s->act('refresh', 0));
        $this->assertNull($p->cache[Dashboard::CACHE_KEY]);
        $this->assertSame(['ERR_ACTION'], self::keys($s->act('drop_tables', 0)));
        $this->assertSame(['DISCONNECTED'], self::keys($s->act('disconnect', 0)));
        $this->assertSame('', $p->settings['api_key']);
    }

    public function testJoomlaServicesAreBuiltOnce(): void
    {
        \Joomla\CMS\Factory::$container = new \Joomla\DI\Container();
        \Joomla\CMS\Factory::$container->set(\Joomla\Database\DatabaseInterface::class, new \Pathly\Tests\FakeDb());
        $one = Services::joomla();
        $this->assertSame($one, Services::joomla());
        $this->assertInstanceOf(JoomlaPlatform::class, $one->platform);
        Services::reset();
        $this->assertNotSame($one, Services::joomla());
        $fake = new Services(new FakePlatform(), new FakeTransport());
        Services::reset($fake);
        $this->assertSame($fake, Services::joomla());
    }

    private static function keys(array $notices): array
    {
        return array_map(static function (array $n): string {
            return substr($n['key'], \strlen('PLG_SYSTEM_PATHLY_'));
        }, $notices);
    }
}
