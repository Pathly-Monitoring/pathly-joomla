<?php

/**
 * @package     Pathly
 * @subpackage  mod_pathly
 * @copyright   (C) 2026 Pathly
 * @license     GNU General Public License version 2 or later; see LICENSE
 *
 * @var string      $state  ok, plugin_off or forbidden.
 * @var array|null  $pathly View of the dashboard.
 * @var string      $token  Form token.
 * @var string      $action Address of the actions.
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ($state === 'forbidden') {
    return;
}

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$t = static function (string $key, ...$args) use ($e): string {
    return $e($args ? Text::sprintf('MOD_PATHLY_' . $key, ...$args) : Text::_('MOD_PATHLY_' . $key));
};
$button = static function (string $name, string $label, string $style = 'btn-outline-secondary', ?int $index = null) use ($e, $t, $action, $token): string {
    return '<form method="post" action="' . $e($action) . '" class="d-inline-block me-1 mb-1">'
        . '<input type="hidden" name="action" value="' . $e($name) . '">'
        . ($index === null ? '' : '<input type="hidden" name="index" value="' . (int) $index . '">')
        . '<input type="hidden" name="' . $e($token) . '" value="1">'
        . '<button type="submit" class="btn btn-sm ' . $e($style) . '">' . $t($label) . '</button></form>';
};
$status = static function (?array $scenario) use ($t): string {
    $map   = ['ok' => ['bg-success', 'STATUS_OK'], 'fail' => ['bg-danger', 'STATUS_FAIL']];
    $style = $map[$scenario['status'] ?? ''] ?? ['bg-secondary', 'STATUS_PENDING'];

    return '<span class="badge ' . $style[0] . '">' . $t($style[1]) . '</span>';
};
$notice = static function (array $notice) use ($e): string {
    $args = $notice['args'] ?? [];

    return $e($args ? Text::sprintf($notice['key'], ...$args) : Text::_($notice['key']));
};
$link = static function (string $url, string $label) use ($e, $t): string {
    return '<a href="' . $e($url) . '" target="_blank" rel="noopener">' . $t($label) . '</a>';
};
?>
<div class="mod-pathly p-3">
<?php if ($state === 'plugin_off') : ?>
    <p class="mb-0"><?php echo $t('PLUGIN_OFF'); ?></p>
<?php elseif (!$pathly['configured']) : ?>
    <p><?php echo $t('INTRO'); ?></p>
    <a class="btn btn-primary btn-sm me-1" href="<?php echo $e($pathly['signupUrl']); ?>" target="_blank" rel="noopener"><?php echo $t('SIGNUP'); ?></a>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo $e($pathly['settingsUrl']); ?>"><?php echo $t('PASTE_KEY'); ?></a>
<?php elseif (!$pathly['connected']) : ?>
    <?php if ($pathly['error']) : ?>
        <div class="alert alert-danger"><?php echo $notice($pathly['error']); ?></div>
    <?php endif; ?>
    <?php echo $button('connect', 'RETRY', 'btn-primary'); ?>
    <a class="btn btn-outline-secondary btn-sm mb-1" href="<?php echo $e($pathly['settingsUrl']); ?>"><?php echo $t('SETTINGS'); ?></a>
<?php else : ?>
    <p class="mb-2">
        <strong><?php echo $e($pathly['organization']); ?></strong>
        <?php if ($pathly['space']) :
            ?> · <?php echo $e($pathly['space']); ?><?php
        endif; ?>
        <?php if (!empty($pathly['plan'])) : ?>
            · <?php echo $t('PLAN', $pathly['plan']); ?> (<?php echo $link($pathly['billing'], 'PLANS'); ?>)
        <?php endif; ?>
    </p>
    <?php if ($pathly['error']) : ?>
        <div class="alert alert-warning"><?php echo $notice($pathly['error']); ?></div>
    <?php endif; ?>

    <table class="table table-sm mb-2">
        <tbody>
        <tr>
            <th scope="row">Ping</th>
            <td><?php echo $status($pathly['ping'] ?? null); ?></td>
            <td class="text-end"><?php echo empty($pathly['ping']) ? '' : $link($pathly['ping']['url'], 'OPEN'); ?></td>
        </tr>
        <tr>
            <th scope="row">Flow</th>
            <td><?php echo empty($pathly['flow']) ? $t('NO_FLOW') : $status($pathly['flow']); ?></td>
            <td class="text-end"><?php echo empty($pathly['flow']) ? '' : $link($pathly['flow']['url'], 'OPEN'); ?></td>
        </tr>
        </tbody>
    </table>

    <?php if (empty($pathly['flow'])) : ?>
        <div class="border rounded p-2 mb-2">
            <?php $trial = $pathly['trial'] ?? null; ?>
            <?php if ($trial === null) : ?>
                <p class="mb-1"><?php echo $t('LUMI_INTRO'); ?></p>
                <?php if ($pathly['store'] !== '') : ?>
                    <p class="small text-muted mb-1"><?php echo $t('LUMI_STORE', $pathly['store']); ?></p>
                <?php endif; ?>
                <?php echo $button('lumi_start', 'LUMI_START', 'btn-primary'); ?>
            <?php elseif ($trial['status'] === 'pending') : ?>
                <p class="mb-1"><?php echo $t('LUMI_PENDING'); ?></p>
                <?php echo $button('refresh', 'REFRESH'); ?>
                <?php echo $button('lumi_cancel', 'CANCEL'); ?>
            <?php elseif ($trial['status'] === 'ready' && $trial['proposals']) : ?>
                <p class="mb-1"><?php echo $t('LUMI_READY'); ?></p>
                <ul class="list-unstyled mb-1">
                <?php foreach ($trial['proposals'] as $proposal) : ?>
                    <li class="mb-1">
                        <?php echo $t('KIND_' . strtoupper($proposal['kind'])); ?>
                        · <?php echo $t('STEPS', $proposal['steps']); ?>
                        <?php if ($proposal['source'] === 'ai') :
                            ?> · <?php echo $t('BY_AI'); ?><?php
                        endif; ?>
                        <?php echo $button('lumi_save', 'SAVE_FLOW', 'btn-success', $proposal['index']); ?>
                    </li>
                <?php endforeach; ?>
                </ul>
                <?php echo $button('lumi_cancel', 'CANCEL'); ?>
            <?php else : ?>
                <p class="mb-1"><?php echo $t('LUMI_NOT_FOUND'); ?></p>
                <?php echo $button('lumi_cancel', 'LUMI_AGAIN'); ?>
                <?php echo $link($pathly['appUrl'], 'CREATE_BY_HAND'); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pathly['failure'])) : ?>
        <?php $failure = $pathly['failure']; ?>
        <div class="mb-2">
            <strong><?php echo $t('LAST_FAILURE'); ?></strong>
            <?php echo $e(HTMLHelper::date($failure['at'], Text::_('DATE_FORMAT_LC2'))); ?>
            · <?php echo $e($failure['scenario']); ?>
            <?php if ($failure['message'] !== '') :
                ?><br><span class="small"><?php echo $e($failure['message']); ?></span><?php
            endif; ?>
            <?php if ($failure['screenshotUrl'] !== '') : ?>
                <a href="<?php echo $e($failure['screenshotUrl']); ?>" target="_blank" rel="noopener" class="d-block mt-1">
                    <img src="<?php echo $e($failure['screenshotUrl']); ?>" alt="<?php echo $t('SCREENSHOT'); ?>" class="img-fluid border" loading="lazy">
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pathly['quotas'])) : ?>
        <table class="table table-sm small mb-2">
            <tbody>
            <?php foreach ($pathly['quotas'] as $name => $quota) : ?>
                <tr>
                    <th scope="row" class="fw-normal"><?php echo $t('QUOTA_' . strtoupper($name)); ?></th>
                    <td class="text-end"><?php echo (int) $quota['used']; ?> / <?php echo $quota['limit'] === null ? '∞' : (int) $quota['limit']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($pathly['lastReplayAt'] > 0) : ?>
        <p class="small text-muted mb-2"><?php echo $t('LAST_REPLAY', HTMLHelper::date('@' . $pathly['lastReplayAt'], Text::_('DATE_FORMAT_LC2'))); ?></p>
    <?php endif; ?>

    <?php echo $button('run', 'RUN_NOW'); ?>
    <?php echo $button('refresh', 'REFRESH'); ?>
    <a class="btn btn-sm btn-outline-primary mb-1" href="<?php echo $e($pathly['appUrl']); ?>" target="_blank" rel="noopener"><?php echo $t('OPEN_PATHLY'); ?></a>
    <a class="btn btn-sm btn-link mb-1" href="<?php echo $e($pathly['settingsUrl']); ?>"><?php echo $t('SETTINGS'); ?></a>
    <?php echo $button('disconnect', 'DISCONNECT', 'btn-link text-danger'); ?>
<?php endif; ?>
</div>
