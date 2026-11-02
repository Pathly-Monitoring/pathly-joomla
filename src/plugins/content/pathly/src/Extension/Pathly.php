<?php

/**
 * @package     Pathly
 * @subpackage  plg_content_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\Content\Pathly\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Pathly\Plugin\System\Pathly\Service\Services;

\defined('_JEXEC') or die;

/**
 * Replaces `{pathly-badge}` in articles with the availability badge. The badge
 * is off by default: the shortcode is then removed. No call to the API, the
 * badge address is saved when the settings are saved.
 */
final class Pathly extends CMSPlugin implements SubscriberInterface
{
    /** @var bool */
    protected $autoloadLanguage = true;

    /** @var Services|null */
    private $services;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    /** Services to use instead of those of the site (tests). */
    public function setServices(Services $services): void
    {
        $this->services = $services;
    }

    public function onContentPrepare(EventInterface $event): void
    {
        $item = $event->getArgument('subject') ?? $event->getArgument(1);
        if (!\is_object($item) || !isset($item->text) || strpos((string) $item->text, '{pathly-badge}') === false) {
            return;
        }
        // The system plugin carries the settings: when it is off, the shortcode is only removed.
        $html = '';
        if ($this->services !== null || PluginHelper::isEnabled('system', 'pathly')) {
            $html = ($this->services ?? Services::joomla())->badge->html(Text::_('PLG_CONTENT_PATHLY_BADGE_ALT'));
        }
        $item->text = str_replace('{pathly-badge}', $html, (string) $item->text);
    }
}
