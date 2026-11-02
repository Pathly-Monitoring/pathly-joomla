<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Service;

use Pathly\Plugin\System\Pathly\Api\Client;
use Pathly\Plugin\System\Pathly\Api\JoomlaTransport;
use Pathly\Plugin\System\Pathly\Api\Transport;
use Pathly\Plugin\System\Pathly\Version;

\defined('_JEXEC') or die;

/**
 * Builds the services of the extension, and runs the actions of the dashboard.
 * Shared by the system plugin, the dashboard module and the content plugin.
 */
final class Services
{
    /** @var self|null */
    private static $instance;

    /** @var Platform */
    public $platform;

    /** @var Connection */
    public $connection;

    /** @var Lumi */
    public $lumi;

    /** @var Replay */
    public $replay;

    /** @var Badge */
    public $badge;

    /** @var Dashboard */
    public $dashboard;

    /** @var Transport */
    private $transport;

    public function __construct(Platform $platform, Transport $transport)
    {
        $this->transport = $transport;
        $agent = 'pathly-joomla/' . Version::VERSION . ' Joomla/' . $platform->cmsVersion();
        $base  = Client::resolveBase($platform->env('PATHLY_API_BASE'));
        $client = new Client($transport, $platform->settings()['api_key'], $agent, $base);

        $this->platform   = $platform;
        $this->connection = new Connection($platform, $client);
        $this->lumi       = new Lumi($platform, $client);
        $this->replay     = new Replay($platform, $client);
        $this->badge      = new Badge($platform, $client);
        $this->dashboard  = new Dashboard($platform, $client, $this->lumi);
    }

    /** Services of the running site, built once per request. */
    public static function joomla(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(new JoomlaPlatform(), new JoomlaTransport());
        }

        return self::$instance;
    }

    /** Replaces the services of the site (tests), or forgets them. */
    public static function reset(?self $instance = null): void
    {
        self::$instance = $instance;
    }

    /**
     * Services built again from the saved settings (the key may have changed),
     * which become the services of the site for the rest of the request.
     */
    public function reload(): self
    {
        $this->platform->reload();
        self::$instance = new self($this->platform, $this->transport);

        return self::$instance;
    }

    /**
     * Checks the key, creates or attaches the Ping and applies the badge switch.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function connect(): array
    {
        $notices = $this->connection->connect();
        $notices = array_merge($notices, $this->badge->sync());
        $this->dashboard->clear();

        return $notices;
    }

    /**
     * Runs an action of the dashboard.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function act(string $action, int $index): array
    {
        switch ($action) {
            case 'connect':
                return $this->connect();
            case 'lumi_start':
                $notices = $this->lumi->start();
                break;
            case 'lumi_save':
                $notices = $this->lumi->save($index);
                break;
            case 'lumi_cancel':
                $notices = $this->lumi->cancel();
                break;
            case 'run':
                $notices = $this->replay->flush('manual');
                break;
            case 'refresh':
                $notices = [];
                break;
            case 'disconnect':
                $notices = $this->connection->disconnect();
                break;
            default:
                return [Notices::make('error', 'ERR_ACTION')];
        }
        $this->dashboard->clear();

        return $notices;
    }
}
