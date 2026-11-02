<?php

namespace Pathly\Tests\Unit;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\Event;
use Pathly\Plugin\System\Pathly\Extension\Pathly;
use Pathly\Plugin\System\Pathly\Service\Services;
use Pathly\Tests\FakeDb;
use Pathly\Tests\FakePlatform;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class SystemPluginTest extends TestCase
{
    /** @var FakePlatform */
    private $platform;

    /** @var FakeTransport */
    private $transport;

    /** @var Pathly */
    private $plugin;

    protected function setUp(): void
    {
        $this->platform  = new FakePlatform();
        $this->transport = new FakeTransport();
        $subject         = null;
        $this->plugin    = new Pathly($subject, []);
        $this->plugin->setServices(new Services($this->platform, $this->transport));
    }

    protected function tearDown(): void
    {
        Services::reset();
    }

    private static function table(array $params, string $folder = 'system', string $element = 'pathly'): object
    {
        return (object) ['type' => 'plugin', 'folder' => $folder, 'element' => $element, 'params' => json_encode($params)];
    }

    public function testSubscribedEvents(): void
    {
        $events = Pathly::getSubscribedEvents();
        $this->assertSame('onExtensionAfterUpdate', $events['onExtensionAfterUpdate']);
        $this->assertArrayHasKey('onJoomlaAfterUpdate', $events);
        $this->assertArrayHasKey('onAjaxPathly', $events);
        foreach ($events as $method) {
            $this->assertTrue(method_exists($this->plugin, $method));
        }
    }

    public function testEmptyKeyFieldKeepsTheSavedKey(): void
    {
        $this->platform->withKey('sp_saved_key_abcdefghijklmnop');
        $table = self::table(['api_key' => '  ', 'badge' => 1]);
        // Joomla 5: named arguments.
        $this->plugin->onExtensionBeforeSave(new Event('onExtensionBeforeSave', ['context' => 'com_plugins.plugin', 'subject' => $table, 'isNew' => false]));
        $this->assertSame(['api_key' => 'sp_saved_key_abcdefghijklmnop', 'badge' => 1], json_decode($table->params, true));
    }

    public function testNewKeyIsTrimmedAndKept(): void
    {
        $table = self::table(['api_key' => ' sp_new ']);
        // Joomla 4: positional arguments.
        $this->plugin->onExtensionBeforeSave(new Event('onExtensionBeforeSave', ['com_plugins.plugin', $table, false]));
        $this->assertSame(['api_key' => 'sp_new'], json_decode($table->params, true));

        $broken         = self::table([]);
        $broken->params = 'not json';
        $this->plugin->onExtensionBeforeSave(new Event('x', ['com_plugins.plugin', $broken, false]));
        $this->assertSame(['api_key' => ''], json_decode($broken->params, true));
    }

    public function testOtherSavesAreIgnored(): void
    {
        $others = [
            ['com_modules.module', self::table(['api_key' => ''])],
            ['com_plugins.plugin', 'not an object'],
            ['com_plugins.plugin', (object) ['folder' => 'system', 'element' => 'pathly']],
            ['com_plugins.plugin', self::table(['api_key' => ''], 'content')],
            ['com_plugins.plugin', self::table(['api_key' => ''], 'system', 'cache')],
        ];
        foreach ($others as [$context, $table]) {
            $event  = new Event('onExtensionBeforeSave', [$context, $table, false]);
            $before = \is_object($table) ? clone $table : $table;
            $this->plugin->onExtensionBeforeSave($event);
            $this->plugin->onExtensionAfterSave($event);
            $this->assertEquals($before, $table);
        }
        $this->assertSame([], $this->transport->requests);
        $this->assertSame([], $this->platform->notices);
    }

    public function testSavingTheSettingsConnects(): void
    {
        // The key is saved after the services were built: they are built again.
        $this->platform->withKey();
        $this->transport
            ->on('GET /v1/account', 200, ['organization' => ['name' => 'Studio']])
            ->on('GET /v1/scenarios', 200, ['items' => [['id' => 'p1', 'type' => 'http', 'url' => 'https://www.example.test']]]);

        $this->plugin->onExtensionAfterSave(new Event('onExtensionAfterSave', ['com_plugins.plugin', self::table([]), false]));

        $this->assertSame(['CONNECTED'], $this->platform->keys());
        $this->assertSame('p1', $this->platform->state['ping_id']);
        $this->assertSame(1, $this->platform->reloads);
        $this->assertSame('Bearer sp_abcdefghijklmnopqrstuvwxyz012345', $this->transport->requests[0]['headers']['Authorization']);
        $this->assertSame($this->platform, Services::joomla()->platform, 'the new services serve the rest of the request');
    }

    public function testUpdatesAskForAReplay(): void
    {
        $this->platform->connected(['flow_id' => 'f1']);
        $this->transport->on('POST /v1/scenarios/f1/run', 200, []);

        $this->plugin->onExtensionAfterUpdate(new Event('onExtensionAfterUpdate', ['installer' => null, 'eid' => 12]));
        $this->plugin->onJoomlaAfterUpdate(new Event('onJoomlaAfterUpdate', ['oldVersion' => '5.1.0']));
        $this->plugin->onExtensionAfterInstall(new Event('onExtensionAfterInstall', ['installer' => null, 'eid' => 13]));
        $this->assertCount(1, $this->platform->deferred, 'one replay for the request');

        $this->platform->runDeferred();
        $this->assertSame(['POST /v1/scenarios/f1/run'], $this->transport->routes());
        $this->assertSame('extension', $this->platform->state['last_replay_reason']);
    }

    public function testCoreUpdateAndFailedInstall(): void
    {
        $this->platform->connected();
        $this->plugin->onExtensionAfterInstall(new Event('onExtensionAfterInstall', [null, false]));
        $this->assertSame([], $this->platform->deferred, 'a failed install changes nothing');
        $this->plugin->onJoomlaAfterUpdate(new Event('onJoomlaAfterUpdate', []));
        $this->assertCount(1, $this->platform->deferred);
    }

    public function testAjaxNeedsASuperUserInTheAdministrator(): void
    {
        $this->platform->allowed = false;
        $this->plugin->onAjaxPathly(new Event('onAjaxPathly'));
        $this->platform->allowed = true;
        $this->platform->admin   = false;
        $this->plugin->onAjaxPathly(new Event('onAjaxPathly'));
        $this->assertSame(['ERR_FORBIDDEN', 'ERR_FORBIDDEN'], $this->platform->keys());
        $this->assertSame(['index.php', 'index.php'], $this->platform->redirects);
        $this->assertSame([], $this->transport->requests);
    }

    public function testAjaxNeedsTheFormToken(): void
    {
        $this->platform->post  = false;
        $this->platform->input = ['action' => 'disconnect'];
        $this->platform->withKey();
        $this->plugin->onAjaxPathly(new Event('onAjaxPathly'));
        $this->assertSame(['ERR_TOKEN'], $this->platform->keys());
        $this->assertNotSame('', $this->platform->settings['api_key']);
    }

    public function testAjaxRunsTheAction(): void
    {
        $this->platform->withKey();
        $this->platform->input = ['action' => 'disconnect'];
        $this->plugin->onAjaxPathly(new Event('onAjaxPathly'));
        $this->assertSame(['DISCONNECTED'], $this->platform->keys());
        $this->assertSame('', $this->platform->settings['api_key']);
        $this->assertSame(['index.php'], $this->platform->redirects);
    }

    public function testUsesTheServicesOfTheSiteByDefault(): void
    {
        Factory::$container = new Container();
        Factory::$container->set(DatabaseInterface::class, new FakeDb());
        $subject = null;
        $plugin  = new Pathly($subject, []);
        $plugin->onExtensionAfterUpdate(new Event('onExtensionAfterUpdate', []));
        $this->assertNull(Pathly::argument(new Event('x', []), 'eid', 1));
    }
}
