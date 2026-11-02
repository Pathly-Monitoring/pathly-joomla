<?php

namespace Pathly\Tests\Unit;

use Pathly\Plugin\System\Pathly\Api\Client;
use Pathly\Plugin\System\Pathly\Service\Lumi;
use Pathly\Tests\FakePlatform;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class LumiTest extends TestCase
{
    private function make(FakePlatform $p, FakeTransport $t): Lumi
    {
        return new Lumi($p, new Client($t, 'sp_abcdefghijklmnopqrstuvwxyz012345', 'ua'));
    }

    public function testNeedsAConnection(): void
    {
        $t = new FakeTransport();
        $this->assertSame('PLG_SYSTEM_PATHLY_NOT_CONNECTED', $this->make(new FakePlatform(), $t)->start()[0]['key']);
        $this->assertSame([], $t->requests);
    }

    public function testStartsATrialOnJoomla(): void
    {
        $p = (new FakePlatform())->connected();
        $t = (new FakeTransport())
            ->on('POST /v1/flow-assistant/detect', 200, ['available' => true, 'siteKind' => 'joomla', 'ai' => false])
            ->on('POST /v1/flow-assistant/trials', 202, ['id' => 'trial-1']);

        $notices = $this->make($p, $t)->start();

        $this->assertSame(['PLG_SYSTEM_PATHLY_LUMI_STARTED'], array_column($notices, 'key'));
        $this->assertSame(['url' => 'https://www.example.test', 'confirmOwner' => true], $t->last()['body']);
        $this->assertSame('trial-1', $p->state['trial_id']);
        $this->assertSame('joomla', $p->state['trial_kind']);
        $this->assertFalse($p->state['trial_ai']);
    }

    public function testStoreWithoutCartTemplateGoesToTheAi(): void
    {
        $p             = (new FakePlatform())->connected();
        $p->components = ['com_hikashop'];
        $t             = (new FakeTransport())
            ->on('POST /v1/flow-assistant/detect', 200, ['available' => true, 'ai' => true])
            ->on('POST /v1/flow-assistant/trials', 202, []);

        $lumi    = $this->make($p, $t);
        $notices = $lumi->start();

        $this->assertSame('HikaShop', $lumi->store());
        $this->assertSame('PLG_SYSTEM_PATHLY_LUMI_STORE_AI', $notices[1]['key']);
        $this->assertSame(['HikaShop'], $notices[1]['args']);
        $this->assertSame('other', $p->state['trial_kind']);
        $this->assertSame('', $p->state['trial_id']);

        $p->components = ['com_virtuemart', 'com_hikashop'];
        $this->assertSame('VirtueMart', $lumi->store());
        $p->components = [];
        $this->assertSame('', $lumi->store());
    }

    /**
     * @dataProvider unavailable
     */
    public function testUnavailable(array $detect, string $key): void
    {
        $p = (new FakePlatform())->connected();
        $t = (new FakeTransport())->on('POST /v1/flow-assistant/detect', 200, $detect);
        $notices = $this->make($p, $t)->start();
        $this->assertSame('PLG_SYSTEM_PATHLY_' . $key, $notices[0]['key']);
        $this->assertSame('warning', $notices[0]['level']);
        $this->assertSame(['POST /v1/flow-assistant/detect'], $t->routes());
    }

    public function unavailable(): array
    {
        return [
            'off'         => [['available' => false, 'reason' => 'off'], 'ERR_ASSISTANT_OFF'],
            'quota'       => [['available' => false, 'reason' => 'quota'], 'ERR_ASSISTANT_QUOTA'],
            'blocked'     => [['available' => false, 'reason' => 'url_blocked'], 'ERR_URL_BLOCKED'],
            'unreachable' => [['available' => false, 'reason' => 'unreachable'], 'ERR_UNREACHABLE'],
            'no reason'   => [[], 'ERR_ASSISTANT_OFF'],
        ];
    }

    public function testRefusedTrial(): void
    {
        $p = (new FakePlatform())->connected();
        $t = (new FakeTransport())
            ->on('POST /v1/flow-assistant/detect', 200, ['available' => true])
            ->on('POST /v1/flow-assistant/trials', 403, ['code' => 'assistant_quota']);
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_ASSISTANT_QUOTA', $this->make($p, $t)->start()[0]['key']);
        $this->assertArrayNotHasKey('trial_id', $p->state);
    }

    public function testViewWithoutTrial(): void
    {
        $this->assertNull($this->make(new FakePlatform(), new FakeTransport())->view());
    }

    public function testPendingTrial(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1']);
        $t = (new FakeTransport())->on('GET /v1/flow-assistant/trials/t1', 200, ['item' => ['status' => 'exploring', 'siteKind' => 'joomla']]);
        $view = $this->make($p, $t)->view();
        $this->assertSame(['status' => 'pending', 'reason' => '', 'siteKind' => 'joomla', 'proposals' => []], $view);
    }

    public function testReadyTrialSortsProposalsForTheSite(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1']);
        $t = (new FakeTransport())->on('GET /v1/flow-assistant/trials/t1', 200, ['item' => [
            'status'    => 'ready',
            'proposals' => [
                ['index' => 0, 'kind' => 'login', 'source' => 'template', 'steps' => [1, 2, 3]],
                ['index' => 1, 'kind' => 'custom', 'source' => 'ai', 'steps' => [1, 2]],
                ['index' => 2, 'kind' => 'contact', 'source' => 'template', 'steps' => [1, 2, 3, 4], 'stoppedBefore' => 'Envoyer'],
                ['index' => 3, 'kind' => 'cart', 'source' => 'ai', 'steps' => [1, 2]],
                ['index' => 4, 'kind' => 'login', 'source' => 'template', 'steps' => [1, 2]],
                [],
            ],
        ]]);
        $lumi = $this->make($p, $t);

        $view = $lumi->view();
        $this->assertSame('ready', $view['status']);
        $this->assertSame('other', $view['siteKind']);
        $this->assertSame([2, 0, 4, 3, 0, 1], array_column($view['proposals'], 'index'));
        $this->assertSame(4, $view['proposals'][0]['steps']);
        $this->assertSame('Envoyer', $view['proposals'][0]['stoppedBefore']);
        $this->assertSame('custom', $view['proposals'][4]['kind']);

        $p->components = ['com_virtuemart'];
        $this->assertSame([3, 2, 0, 4, 0, 1], array_column($lumi->view()['proposals'], 'index'));
    }

    public function testTrialWithoutItemIsFailed(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1']);
        $t = (new FakeTransport())->on('GET /v1/flow-assistant/trials/t1', 200, []);
        $this->assertSame('failed', $this->make($p, $t)->view()['status']);
    }

    public function testSaveNeedsATrial(): void
    {
        $p = (new FakePlatform())->connected();
        $this->assertSame('PLG_SYSTEM_PATHLY_LUMI_NO_TRIAL', $this->make($p, new FakeTransport())->save(0)[0]['key']);
    }

    public function testSaveKeepsTheFlow(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1', 'trial_kind' => 'joomla', 'trial_ai' => false]);
        $t = (new FakeTransport())->on('POST /v1/flow-assistant/trials/t1/save', 201, ['item' => ['id' => 'flow-1']]);
        $notices = $this->make($p, $t)->save(2);
        $this->assertSame('PLG_SYSTEM_PATHLY_FLOW_SAVED', $notices[0]['key']);
        $this->assertSame(['index' => 2, 'name' => 'Flow · example.test'], $t->last()['body']);
        $this->assertSame('flow-1', $p->state['flow_id']);
        $this->assertArrayNotHasKey('trial_id', $p->state);
        $this->assertArrayNotHasKey('trial_kind', $p->state);
    }

    public function testAlreadySavedKeepsTheExistingFlow(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1']);
        $t = (new FakeTransport())->on('POST /v1/flow-assistant/trials/t1/save', 409, ['code' => 'already_saved', 'monitorId' => 'flow-9']);
        $this->assertSame('PLG_SYSTEM_PATHLY_FLOW_SAVED', $this->make($p, $t)->save(0)[0]['key']);
        $this->assertSame('flow-9', $p->state['flow_id']);
    }

    public function testPlanLimitOnSave(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1']);
        unset($p->state['site_url']);
        $t = (new FakeTransport())->on('POST /v1/flow-assistant/trials/t1/save', 403, ['code' => 'PLAN_LIMIT']);
        $this->assertSame('PLG_SYSTEM_PATHLY_ERR_PLAN_LIMIT', $this->make($p, $t)->save(0)[0]['key']);
        $this->assertSame('t1', $p->state['trial_id']);
        $this->assertSame(['index' => 0, 'name' => 'Flow · '], $t->last()['body']);
    }

    public function testCancel(): void
    {
        $p = (new FakePlatform())->connected(['trial_id' => 't1', 'trial_kind' => 'joomla', 'trial_ai' => true]);
        $this->assertSame([], $this->make($p, new FakeTransport())->cancel());
        $this->assertArrayNotHasKey('trial_id', $p->state);
        $this->assertSame('ping-1', $p->state['ping_id']);
    }
}
