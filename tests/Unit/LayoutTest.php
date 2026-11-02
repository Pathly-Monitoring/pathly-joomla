<?php

namespace Pathly\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Layout of the dashboard box, rendered for each state.
 */
final class LayoutTest extends TestCase
{
    private const LAYOUT = __DIR__ . '/../../src/modules/mod_pathly/tmpl/default.php';

    private static function render(string $state, ?array $pathly): string
    {
        $token  = 'tok123';
        $action = 'index.php?option=com_ajax&group=system&plugin=pathly&format=raw';
        ob_start();
        include self::LAYOUT;

        return (string) ob_get_clean();
    }

    private static function base(array $extra = []): array
    {
        return array_merge([
            'configured'  => true,
            'connected'   => true,
            'error'       => null,
            'signupUrl'   => 'https://pathlyhq.com/fr/login?mode=signup&ref=joomla',
            'settingsUrl' => 'index.php?option=com_plugins&task=plugin.edit&extension_id=42',
            'appUrl'      => 'https://pathlyhq.com/dashboard',
            'store'       => '',
        ], $extra);
    }

    private static function connected(array $extra = []): array
    {
        return self::base(array_merge([
            'organization' => 'Studio <Horizon>',
            'space'        => null,
            'badge'        => false,
            'lastReplayAt' => 0,
            'plan'         => '',
            'billing'      => 'https://pathlyhq.com/billing',
            'quotas'       => [],
            'ping'         => ['id' => 'p', 'name' => 'Ping', 'status' => 'ok', 'url' => 'https://pathlyhq.com/monitors/p'],
            'flow'         => null,
            'failure'      => null,
            'trial'        => null,
        ], $extra));
    }

    public function testForbiddenRendersNothing(): void
    {
        $this->assertSame('', self::render('forbidden', null));
    }

    public function testPluginOff(): void
    {
        $this->assertStringContainsString('MOD_PATHLY_PLUGIN_OFF', self::render('plugin_off', null));
    }

    public function testNotConfigured(): void
    {
        $html = self::render('ok', self::base(['configured' => false, 'connected' => false]));
        $this->assertStringContainsString('MOD_PATHLY_SIGNUP', $html);
        $this->assertStringContainsString('href="https://pathlyhq.com/fr/login?mode=signup&amp;ref=joomla"', $html);
        $this->assertStringNotContainsString('<form', $html, 'no action before a key is saved');
    }

    public function testConfiguredButRefused(): void
    {
        $html = self::render('ok', self::base(['connected' => false, 'error' => ['level' => 'error', 'key' => 'PLG_SYSTEM_PATHLY_ERR_BUSY', 'args' => [30]]]));
        $this->assertStringContainsString('PLG_SYSTEM_PATHLY_ERR_BUSY|30', $html);
        $this->assertStringContainsString('name="action" value="connect"', $html);
        $this->assertStringContainsString('name="tok123" value="1"', $html);

        $html = self::render('ok', self::base(['connected' => false]));
        $this->assertStringNotContainsString('alert', $html);
    }

    public function testConnectedWithoutFlowOffersLumi(): void
    {
        $html = self::render('ok', self::connected(['store' => 'VirtueMart', 'error' => ['level' => 'error', 'key' => 'PLG_SYSTEM_PATHLY_ERR_SCOPE']]));
        $this->assertStringContainsString('Studio &lt;Horizon&gt;', $html);
        $this->assertStringContainsString('PLG_SYSTEM_PATHLY_ERR_SCOPE', $html);
        $this->assertStringContainsString('MOD_PATHLY_STATUS_OK', $html);
        $this->assertStringContainsString('MOD_PATHLY_NO_FLOW', $html);
        $this->assertStringContainsString('MOD_PATHLY_LUMI_STORE|VirtueMart', $html);
        $this->assertStringContainsString('value="lumi_start"', $html);
        $this->assertStringContainsString('value="disconnect"', $html);
        $this->assertStringNotContainsString('MOD_PATHLY_PLAN', $html);

        $html = self::render('ok', self::connected(['ping' => null]));
        $this->assertStringContainsString('MOD_PATHLY_STATUS_PENDING', $html);
        $this->assertStringNotContainsString('MOD_PATHLY_LUMI_STORE', $html);
    }

    public function testLumiStates(): void
    {
        $pending = self::render('ok', self::connected(['trial' => ['status' => 'pending', 'proposals' => []]]));
        $this->assertStringContainsString('MOD_PATHLY_LUMI_PENDING', $pending);
        $this->assertStringContainsString('value="lumi_cancel"', $pending);

        $ready = self::render('ok', self::connected(['trial' => ['status' => 'ready', 'proposals' => [
            ['index' => 2, 'kind' => 'contact', 'source' => 'template', 'steps' => 4],
            ['index' => 0, 'kind' => 'cart', 'source' => 'ai', 'steps' => 6],
        ]]]));
        $this->assertStringContainsString('MOD_PATHLY_KIND_CONTACT', $ready);
        $this->assertStringContainsString('MOD_PATHLY_STEPS|4', $ready);
        $this->assertStringContainsString('<input type="hidden" name="index" value="2">', $ready);
        $this->assertSame(1, substr_count($ready, 'MOD_PATHLY_BY_AI'));

        foreach (['not_found', 'failed'] as $status) {
            $html = self::render('ok', self::connected(['trial' => ['status' => $status, 'proposals' => []]]));
            $this->assertStringContainsString('MOD_PATHLY_LUMI_NOT_FOUND', $html);
        }
        $empty = self::render('ok', self::connected(['trial' => ['status' => 'ready', 'proposals' => []]]));
        $this->assertStringContainsString('MOD_PATHLY_LUMI_NOT_FOUND', $empty);
    }

    public function testFlowFailureQuotasAndReplay(): void
    {
        $html = self::render('ok', self::connected([
            'space'        => 'Client A',
            'plan'         => 'Pro',
            'quotas'       => ['ping' => ['used' => 3, 'limit' => 50], 'flow' => ['used' => 1, 'limit' => null]],
            'flow'         => ['id' => 'f', 'name' => 'Flow', 'status' => 'fail', 'url' => 'https://pathlyhq.com/monitors/f'],
            'failure'      => ['at' => '2026-11-02T10:00:00Z', 'message' => 'Bouton introuvable', 'screenshotUrl' => 'https://api.pathlyhq.com/public/run-shot/t', 'scenario' => 'Flow'],
            'lastReplayAt' => 1800000000,
        ]));
        $this->assertStringContainsString('Client A', $html);
        $this->assertStringContainsString('MOD_PATHLY_PLAN|Pro', $html);
        $this->assertStringContainsString('MOD_PATHLY_STATUS_FAIL', $html);
        $this->assertStringContainsString('https://pathlyhq.com/monitors/f', $html);
        $this->assertStringNotContainsString('lumi_start', $html);
        $this->assertStringContainsString('DATE(2026-11-02T10:00:00Z)', $html);
        $this->assertStringContainsString('<img src="https://api.pathlyhq.com/public/run-shot/t"', $html);
        $this->assertStringContainsString('Bouton introuvable', $html);
        $this->assertStringContainsString('3 / 50', $html);
        $this->assertStringContainsString('1 / ∞', $html);
        $this->assertStringContainsString('MOD_PATHLY_LAST_REPLAY|DATE(@1800000000)', $html);

        $html = self::render('ok', self::connected([
            'failure' => ['at' => 'x', 'message' => '', 'screenshotUrl' => '', 'scenario' => 'Ping'],
        ]));
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<br>', $html);
    }
}
