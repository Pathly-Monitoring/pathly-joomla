<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Service;

use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Api\Client;

\defined('_JEXEC') or die;

/**
 * Availability badge, off by default. Turning it on publishes the status page
 * of the organisation (or of the space of the key) on Pathly. Turning it off
 * only stops showing the badge: the status page stays as it is.
 */
final class Badge
{
    /** Shortcode replaced in articles by the content plugin. */
    public const SHORTCODE = '{pathly-badge}';

    /** @var Platform */
    private $platform;

    /** @var Client */
    private $client;

    public function __construct(Platform $platform, Client $client)
    {
        $this->platform = $platform;
        $this->client   = $client;
    }

    /**
     * Applies the switch of the settings.
     *
     * @return list<array{level: string, key: string, args: list<scalar>}>
     */
    public function sync(): array
    {
        $state = $this->platform->state();
        if (!$this->platform->settings()['badge'] || empty($state['connected'])) {
            unset($state['badge_url'], $state['status_url']);
            $this->platform->saveState($state);

            return [];
        }
        try {
            $page = $this->client->createStatusPage(['public' => true]);
        } catch (ApiException $e) {
            return [Notices::fromApi($e)];
        }
        $state['status_page_id'] = (string) ($page['id'] ?? '');
        $state['badge_url']      = (string) ($page['badgeUrl'] ?? '');
        $state['status_url']     = (string) ($page['url'] ?? '');
        $this->platform->saveState($state);

        return [Notices::make('success', 'BADGE_ON')];
    }

    /**
     * Markup of the badge, or '' when it is off. No call to the API.
     */
    public function html(string $alt): string
    {
        $state = $this->platform->state();
        $badge = (string) ($state['badge_url'] ?? '');
        if (!$this->platform->settings()['badge'] || $badge === '') {
            return '';
        }
        $esc = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };

        return '<a class="pathly-badge" href="' . $esc((string) ($state['status_url'] ?? '')) . '" target="_blank"'
            . ' rel="noopener"><img src="' . $esc($badge) . '" alt="' . $esc($alt) . '" height="20" loading="lazy"></a>';
    }
}
