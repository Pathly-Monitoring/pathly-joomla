<?php

namespace Pathly\Tests\Unit;

use Pathly\Plugin\System\Pathly\Api\ApiException;
use Pathly\Plugin\System\Pathly\Service\Notices;
use PHPUnit\Framework\TestCase;

final class NoticesTest extends TestCase
{
    /**
     * @dataProvider refusals
     */
    public function testRefusalsBecomeOurOwnMessages(int $status, string $code, int $retry, string $level, string $key, array $args): void
    {
        $notice = Notices::fromApi(new ApiException($status, $code, 'message en français', $retry));
        $this->assertSame(['level' => $level, 'key' => 'PLG_SYSTEM_PATHLY_' . $key, 'args' => $args], $notice);
    }

    public function refusals(): array
    {
        return [
            'lumi off'      => [403, 'assistant_off', 0, 'error', 'ERR_ASSISTANT_OFF', []],
            'lumi quota'    => [403, 'assistant_quota', 0, 'error', 'ERR_ASSISTANT_QUOTA', []],
            'plan limit'    => [403, 'PLAN_LIMIT', 0, 'error', 'ERR_PLAN_LIMIT', []],
            'blocked'       => [400, 'url_blocked', 0, 'error', 'ERR_URL_BLOCKED', []],
            'already saved' => [409, 'already_saved', 0, 'error', 'ERR_ALREADY_SAVED', []],
            'busy'          => [429, 'assistant_busy', 60, 'warning', 'ERR_BUSY', [60]],
            'rate limit'    => [429, '', 0, 'warning', 'ERR_BUSY', [1]],
            'network'       => [0, 'network', 0, 'error', 'ERR_NETWORK', []],
            'key'           => [401, '', 0, 'error', 'ERR_KEY', []],
            'scope'         => [403, '', 0, 'error', 'ERR_SCOPE', []],
            'not found'     => [404, '', 0, 'error', 'ERR_NOT_FOUND', []],
            'server'        => [503, '', 0, 'error', 'ERR_SERVER', []],
            'request'       => [400, 'confirm_owner', 0, 'error', 'ERR_REQUEST', []],
        ];
    }
}
