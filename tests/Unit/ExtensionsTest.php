<?php

namespace Pathly\Tests\Unit;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Pathly\Module\Pathly\Administrator\Dispatcher\Dispatcher;
use Pathly\Plugin\Content\Pathly\Extension\Pathly as ContentPlugin;
use Pathly\Plugin\System\Pathly\Extension\Pathly as SystemPlugin;
use Pathly\Plugin\System\Pathly\Field\PathlykeyField;
use Pathly\Plugin\System\Pathly\Service\Services;
use Pathly\Tests\FakeApp;
use Pathly\Tests\FakeDb;
use Pathly\Tests\FakePlatform;
use Pathly\Tests\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ExtensionsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../src';

    protected function setUp(): void
    {
        Factory::$application = new FakeApp();
        Factory::$container   = new Container();
        Factory::$container->set(DatabaseInterface::class, new FakeDb());
        PluginHelper::$enabled = [];
    }

    protected function tearDown(): void
    {
        Services::reset();
        Dispatcher::useServices(null);
    }

    public function testKeyFieldNeverShowsTheKey(): void
    {
        $platform = (new FakePlatform())->withKey();
        $field    = new PathlykeyField();
        $field->setServices(new Services($platform, new FakeTransport()));
        $field->name  = 'jform[params][api_key]';
        $field->id    = 'jform_params_api_key';
        $field->value = $platform->settings['api_key'];

        $html = $field->input();
        $this->assertStringNotContainsString('sp_abc', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('PLG_SYSTEM_PATHLY_KEY_SAVED', $html);
        $this->assertStringNotContainsString('ref=joomla', $html);

        $field->value = '';
        Services::reset(new Services(new FakePlatform(), new FakeTransport()));
        $empty = (new PathlykeyField());
        $empty->value = null;
        $html  = $empty->input();
        $this->assertStringContainsString('PLG_SYSTEM_PATHLY_KEY_EMPTY', $html);
        $this->assertStringContainsString('https://pathlyhq.com/fr/login?mode=signup&amp;ref=joomla&amp;utm_source=joomla&amp;utm_medium=module', $html);
    }

    public function testContentPluginReplacesTheShortcode(): void
    {
        $platform                    = (new FakePlatform())->connected(['badge_url' => 'https://api.pathlyhq.com/b.svg', 'status_url' => 'https://pathlyhq.com/status/s']);
        $platform->settings['badge'] = true;
        $subject                     = null;
        $plugin                      = new ContentPlugin($subject, []);
        $plugin->setServices(new Services($platform, new FakeTransport()));
        $this->assertSame(['onContentPrepare' => 'onContentPrepare'], ContentPlugin::getSubscribedEvents());

        $article = (object) ['text' => '<p>Status: {pathly-badge}</p>'];
        $plugin->onContentPrepare(new Event('onContentPrepare', ['context' => 'com_content.article', 'subject' => $article]));
        $this->assertStringContainsString('<img src="https://api.pathlyhq.com/b.svg" alt="PLG_CONTENT_PATHLY_BADGE_ALT"', $article->text);

        $platform->settings['badge'] = false;
        $article                     = (object) ['text' => 'A {pathly-badge} B'];
        $plugin->onContentPrepare(new Event('onContentPrepare', ['com_content.article', $article]));
        $this->assertSame('A  B', $article->text, 'badge off: the shortcode is removed');

        foreach ([(object) ['text' => 'no code'], (object) ['title' => 'x'], 'string'] as $item) {
            $before = \is_object($item) ? clone $item : $item;
            $plugin->onContentPrepare(new Event('onContentPrepare', ['x', $item]));
            $this->assertEquals($before, $item);
        }
    }

    public function testContentPluginWithoutTheSystemPlugin(): void
    {
        $subject = null;
        $plugin  = new ContentPlugin($subject, []);
        $article = (object) ['text' => 'A {pathly-badge} B'];
        $plugin->onContentPrepare(new Event('onContentPrepare', ['x', $article]));
        $this->assertSame('A  B', $article->text);

        PluginHelper::$enabled['system.pathly'] = true;
        $article                                = (object) ['text' => 'A {pathly-badge} B'];
        $plugin->onContentPrepare(new Event('onContentPrepare', ['x', $article]));
        $this->assertSame('A  B', $article->text, 'no key, no badge');
    }

    private function dispatcher(): Dispatcher
    {
        return new Dispatcher((object) ['id' => 1], Factory::$application, Factory::$application->input);
    }

    public function testDashboardBox(): void
    {
        $data = $this->dispatcher()->layoutData();
        $this->assertSame('plugin_off', $data['state']);
        $this->assertNull($data['pathly']);

        PluginHelper::$enabled['system.pathly'] = true;
        $platform                               = new FakePlatform();
        $platform->allowed                      = false;
        Dispatcher::useServices(new Services($platform, new FakeTransport()));
        $this->assertSame('forbidden', $this->dispatcher()->layoutData()['state']);

        $platform->allowed = true;
        $data              = $this->dispatcher()->layoutData();
        $this->assertSame('ok', $data['state']);
        $this->assertFalse($data['pathly']['configured']);
        $this->assertSame('tok123', $data['token']);
        $this->assertSame('index.php?option=com_ajax&group=system&plugin=pathly&format=raw', $data['action']);
        $this->assertSame('atum', $data['template']);

        Dispatcher::useServices(null);
        // The services of the site: a Super User, no key saved yet.
        $data = $this->dispatcher()->layoutData();
        $this->assertSame('ok', $data['state']);
        $this->assertFalse($data['pathly']['configured']);
    }

    public function testServiceProviders(): void
    {
        $dispatcher = new class () implements DispatcherInterface {
        };
        foreach (['system', 'content'] as $group) {
            $provider  = require self::ROOT . '/plugins/' . $group . '/pathly/services/provider.php';
            $container = new Container();
            $container->set(DispatcherInterface::class, $dispatcher);
            $provider->register($container);
            $plugin = $container->get(PluginInterface::class);
            $this->assertInstanceOf($group === 'system' ? SystemPlugin::class : ContentPlugin::class, $plugin);
            $this->assertSame(Factory::$application, $plugin->application);
            $this->assertSame($dispatcher, $plugin->subject);
        }

        $provider  = require self::ROOT . '/modules/mod_pathly/services/provider.php';
        $container = new Container();
        $provider->register($container);
        $this->assertCount(2, $container->providers);
        $this->assertSame('\\Pathly\\Module\\Pathly', $container->providers[0]->namespace);
    }
}
