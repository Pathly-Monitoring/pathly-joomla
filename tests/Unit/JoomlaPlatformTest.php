<?php

namespace Pathly\Tests\Unit;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Pathly\Plugin\System\Pathly\Service\JoomlaPlatform;
use Pathly\Tests\FakeApp;
use Pathly\Tests\FakeDb;
use PHPUnit\Framework\TestCase;

final class JoomlaPlatformTest extends TestCase
{
    /** @var FakeDb */
    private $db;

    /** @var FakeApp */
    private $app;

    protected function setUp(): void
    {
        $this->db  = new FakeDb();
        $this->app = new FakeApp();
        Factory::$container = new Container();
        Factory::$container->set(DatabaseInterface::class, $this->db);
        Factory::$application = $this->app;
        Session::$valid       = true;
    }

    public function testSettingsAndStateLiveInTheRowOfThePlugin(): void
    {
        $this->db->results["`element` = 'pathly'"] = [
            'extension_id' => '17',
            'params'       => '{"api_key":" sp_key ","badge":"1","other":"kept"}',
            'custom_data'  => '{"ping_id":"p1"}',
        ];
        $p = new JoomlaPlatform();

        $this->assertSame(['api_key' => 'sp_key', 'badge' => true, 'extension_id' => 17], $p->settings());
        $this->assertSame(['ping_id' => 'p1'], $p->state());
        $this->assertStringContainsString("`folder` = 'system'", $this->db->queries[0]);

        $p->saveState(['ping_id' => 'p2']);
        $p->saveSettings('', false);
        $this->assertCount(1, array_filter($this->db->queries, static function (string $q): bool {
            return strpos($q, 'SELECT') === 0;
        }), 'the row is read once');
        $this->assertSame(
            "UPDATE `#__extensions` SET `custom_data` = '{\\\"ping_id\\\":\\\"p2\\\"}' WHERE `extension_id` = 17",
            $this->db->executed[0]
        );
        $this->assertStringContainsString('\"other\":\"kept\"', $this->db->executed[1]);
        $this->assertSame(['ping_id' => 'p2'], $p->state());
        $this->assertSame(['api_key' => '', 'badge' => false, 'extension_id' => 17], $p->settings());
        $p->saveSettings('sp_new', true);
        $this->assertSame(['api_key' => 'sp_new', 'badge' => true, 'extension_id' => 17], $p->settings());

        $p->reload();
        $this->assertSame('sp_key', $p->settings()['api_key'], 'read again from the database');
    }

    public function testMissingRowAndBrokenJson(): void
    {
        $p = new JoomlaPlatform();
        $this->assertSame(['api_key' => '', 'badge' => false, 'extension_id' => 0], $p->settings());
        $this->assertSame([], $p->state());

        $this->db->results["`element` = 'pathly'"] = ['extension_id' => 1, 'params' => 'not json', 'custom_data' => ''];
        $this->assertSame([], (new JoomlaPlatform())->state());
    }

    public function testSiteLanguageAndVersion(): void
    {
        Uri::$root = 'https://www.example.test/';
        $p         = new JoomlaPlatform();
        $this->assertSame('https://www.example.test', $p->siteUrl());
        $this->assertSame('5.2.1', $p->cmsVersion());
        $this->assertSame('fr', $p->language());
        $this->app->language->tag = 'de-DE';
        $this->assertSame('de', $p->language());
        $this->app->language->tag = 'it-IT';
        $this->assertSame('en', $p->language());
    }

    public function testSessionCache(): void
    {
        $p = new JoomlaPlatform();
        $this->assertNull($p->cacheGet('dashboard'));
        $p->cacheSet('dashboard', ['a' => 1]);
        $this->assertSame(['a' => 1], $this->app->session->data['plg_system_pathly.dashboard']);
        $this->assertSame(['a' => 1], $p->cacheGet('dashboard'));

        $this->app->session->broken = true;
        $p->cacheSet('dashboard', ['b' => 2]);
        $this->assertNull($p->cacheGet('dashboard'));
    }

    public function testComponentEnabled(): void
    {
        $this->db->results["'com_virtuemart'"] = '1';
        $p = new JoomlaPlatform();
        $this->assertTrue($p->componentEnabled('com_virtuemart'));
        $this->assertFalse($p->componentEnabled('com_hikashop'));
        $this->assertStringContainsString("`type` = 'component'", end($this->db->queries));
    }

    public function testEnvironmentTimeAndDefer(): void
    {
        $p = new JoomlaPlatform();
        putenv('PATHLY_TEST_VAR=x');
        $this->assertSame('x', $p->env('PATHLY_TEST_VAR'));
        putenv('PATHLY_TEST_VAR=');
        $this->assertNull($p->env('PATHLY_TEST_VAR'));
        putenv('PATHLY_TEST_VAR');
        $this->assertNull($p->env('PATHLY_TEST_VAR'));
        $this->assertEqualsWithDelta(time(), $p->now(), 2);

        // Deferred tasks run at shutdown, through runQuietly, checked here.
        $p->defer(static function () {
        });
        $ran = [];
        JoomlaPlatform::runQuietly(static function () use (&$ran) {
            $ran[] = 'ok';
        });
        JoomlaPlatform::runQuietly(static function () {
            throw new \RuntimeException('boom');
        });
        $this->assertSame(['ok'], $ran);
    }

    public function testRequestChecks(): void
    {
        $p = new JoomlaPlatform();
        $this->assertTrue($p->isAdministrator());
        $this->assertTrue($p->canConfigure());
        $this->assertTrue($p->validPost());
        $this->assertSame('post', Session::$method);

        $this->app->input->values = ['action' => 'lumi_save', 'index' => '3'];
        $this->assertSame('lumi_save', $p->inputCmd('action'));
        $this->assertSame(3, $p->inputInt('index'));
        $this->assertSame('', $p->inputCmd('missing'));

        Session::$valid = false;
        $this->assertFalse($p->validPost());
        Session::$valid           = true;
        $this->app->input->method = 'GET';
        $this->assertFalse($p->validPost());

        $this->app->client          = 'site';
        $this->app->identity->admin = false;
        $this->assertFalse($p->isAdministrator());
        $this->assertFalse($p->canConfigure());
        $this->app->identity = null;
        $this->assertFalse($p->canConfigure());
    }

    public function testNotifyAndRedirect(): void
    {
        $p = new JoomlaPlatform();
        $p->notify([
            ['level' => 'success', 'key' => 'K1', 'args' => ['Studio']],
            ['level' => 'warning', 'key' => 'K2', 'args' => []],
            ['level' => 'odd', 'key' => 'K3'],
        ]);
        $this->assertSame([['message', 'K1|Studio'], ['warning', 'K2'], ['info', 'K3']], $this->app->messages);
        $p->redirect('index.php');
        $this->assertSame(['index.php'], $this->app->redirects);
    }
}
