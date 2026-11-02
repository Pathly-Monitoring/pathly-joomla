<?php

namespace Pathly\Tests\Unit;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Pathly\Tests\FakeApp;
use Pathly\Tests\FakeDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/script.php';

final class InstallerScriptTest extends TestCase
{
    /** @var FakeDb */
    private $db;

    /** @var FakeApp */
    private $app;

    protected function setUp(): void
    {
        $this->db             = new FakeDb();
        $this->app            = new FakeApp();
        Factory::$application = $this->app;
        Factory::$container   = new Container();
        Factory::$container->set(DatabaseInterface::class, $this->db);
    }

    public function testPreflightChecksTheVersions(): void
    {
        $script = new \Pkg_PathlyInstallerScript();
        $this->assertTrue($script->preflight('uninstall', null));
        $this->assertTrue($script->preflight('install', null));
        $this->assertSame([], $this->app->messages);

        $this->assertSame('4.4.0', \Pkg_PathlyInstallerScript::MINIMUM_JOOMLA);
        $this->assertSame('7.4.0', \Pkg_PathlyInstallerScript::MINIMUM_PHP);

        // Raised minimums stand for a site that is too old.
        $joomla = new class () extends \Pkg_PathlyInstallerScript {
            public const MINIMUM_JOOMLA = '9.0.0';
        };
        $this->assertFalse($joomla->preflight('update', null));
        $php = new class () extends \Pkg_PathlyInstallerScript {
            public const MINIMUM_PHP = '99.0.0';
        };
        $this->assertFalse($php->preflight('install', null));
        $this->assertSame(
            [['error', 'PKG_PATHLY_JOOMLA_TOO_OLD|9.0.0'], ['error', 'PKG_PATHLY_PHP_TOO_OLD|99.0.0']],
            $this->app->messages
        );
    }

    public function testFirstInstallTurnsOnThePluginsAndShowsTheBox(): void
    {
        $this->db->results['`module` = \'mod_pathly\''] = '91';
        $this->assertTrue((new \Pkg_PathlyInstallerScript())->postflight('install', null));

        $sql = implode("\n", $this->db->executed);
        $this->assertStringContainsString("`folder` IN ('system', 'content')", $this->db->executed[0]);
        $this->assertStringContainsString("`position` = 'cpanel', `published` = 1, `access` = 3", $sql);
        $this->assertStringContainsString('DELETE FROM `#__modules_menu` WHERE `moduleid` = 91', $sql);
        $this->assertStringContainsString('VALUES (91, 0)', $sql);
    }

    public function testFirstInstallWithoutBox(): void
    {
        $this->assertTrue((new \Pkg_PathlyInstallerScript())->postflight('discover_install', null));
        $this->assertCount(1, $this->db->executed);
    }

    public function testUpdateKeepsTheChoicesOfTheAdministrator(): void
    {
        $this->assertTrue((new \Pkg_PathlyInstallerScript())->postflight('update', null));
        $this->assertTrue((new \Pkg_PathlyInstallerScript())->postflight('uninstall', null));
        $this->assertSame([], $this->db->queries);
    }
}
