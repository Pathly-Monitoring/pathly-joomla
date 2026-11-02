<?php

/**
 * @package     Pathly
 * @subpackage  plg_system_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Pathly\Plugin\System\Pathly\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Pathly\Plugin\System\Pathly\Service\Dashboard;
use Pathly\Plugin\System\Pathly\Service\Services;

\defined('_JEXEC') or die;

/**
 * API key field. The saved key is never written back into the page: the
 * field stays empty and says whether a key is saved. Left empty, the saved
 * key is kept.
 */
final class PathlykeyField extends FormField
{
    /** @var string */
    protected $type = 'Pathlykey';

    /** @var Services|null */
    private $services;

    /** Services to use instead of those of the site (tests). */
    public function setServices(Services $services): void
    {
        $this->services = $services;
    }

    protected function getInput()
    {
        $esc   = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };
        $saved = trim((string) $this->value) !== '';
        $hint  = $saved ? 'PLG_SYSTEM_PATHLY_KEY_SAVED' : 'PLG_SYSTEM_PATHLY_KEY_EMPTY';
        $dash  = ($this->services ?? Services::joomla())->dashboard;

        $html = '<input type="password" name="' . $esc((string) $this->name) . '" id="' . $esc((string) $this->id) . '"'
            . ' value="" class="form-control" autocomplete="new-password" spellcheck="false"'
            . ' placeholder="' . $esc(Text::_($saved ? 'PLG_SYSTEM_PATHLY_KEY_KEEP' : 'PLG_SYSTEM_PATHLY_KEY_PLACEHOLDER')) . '">'
            . '<div class="form-text">' . $esc(Text::_($hint)) . '</div>';
        if (!$saved) {
            $html .= '<div class="form-text"><a href="' . $esc($dash->signupUrl()) . '" target="_blank" rel="noopener">'
                . $esc(Text::_('PLG_SYSTEM_PATHLY_SIGNUP')) . '</a> · <a href="' . $esc(Dashboard::APP_URL . '/settings')
                . '" target="_blank" rel="noopener">' . $esc(Text::_('PLG_SYSTEM_PATHLY_KEY_WHERE')) . '</a></div>';
        }

        return $html;
    }
}
