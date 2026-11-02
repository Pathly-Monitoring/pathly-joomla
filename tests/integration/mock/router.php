<?php

/**
 * Simulated Pathly API for the integration tests, served by `php -S`.
 * Every request is written to a log, read back by run.sh through `/_requests`.
 * Also serves the update server and the zips of a small extension, to
 * trigger a real update in Joomla.
 */

const KEY = 'sp_integration_test_key_0123456789';
const LOG = '/tmp/pathly-mock-requests.jsonl';
const BASE = 'http://mock:8080';

$method = $_SERVER['REQUEST_METHOD'];
$path   = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body   = (string) file_get_contents('php://input');

function answer(int $status, $data, string $type = 'application/json'): void
{
    http_response_code($status);
    header('Content-Type: ' . $type);
    echo is_string($data) ? $data : json_encode($data);
}

/** Zip of the small "file" extension used to trigger an update. */
function dummyZip(string $version): string
{
    $manifest = '<?xml version="1.0" encoding="UTF-8"?>
<extension type="file" method="upgrade">
    <name>file_pathlydummy</name>
    <version>' . $version . '</version>
    <fileset><files folder="files" target="media/pathlydummy"><filename>index.html</filename></files></fileset>
    <updateservers><server type="extension" name="Pathly dummy">' . BASE . '/updates.xml</server></updateservers>
</extension>';
    $file = tempnam(sys_get_temp_dir(), 'zip');
    $zip  = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $zip->addFromString('file_pathlydummy.xml', $manifest);
    $zip->addFromString('files/index.html', '<!DOCTYPE html><title>' . $version . '</title>');
    $zip->close();

    return (string) file_get_contents($file);
}

// Test helpers.
if ($path === '/_requests') {
    answer(200, is_file(LOG) ? (string) file_get_contents(LOG) : '', 'text/plain');
    return;
}
if ($path === '/_reset') {
    @unlink(LOG);
    answer(200, 'ok', 'text/plain');
    return;
}
if ($path === '/updates.xml') {
    answer(200, '<?xml version="1.0" encoding="UTF-8"?><updates><update>
        <name>Pathly dummy</name><element>file_pathlydummy</element><type>file</type><version>1.0.1</version>
        <client>site</client>
        <downloads><downloadurl type="full" format="zip">' . BASE . '/dummy-1.0.1.zip</downloadurl></downloads>
        <targetplatform name="joomla" version="(4|5)\..*"/>
    </update></updates>', 'application/xml');
    return;
}
if (preg_match('#^/dummy-(1\.0\.[01])\.zip$#', $path, $m)) {
    answer(200, dummyZip($m[1]), 'application/zip');
    return;
}
if (preg_match('#^/public/run-shot/#', $path)) {
    // Screenshot of the failed step: a real capture when one was made (screenshots), else a blank image.
    $shot = __DIR__ . '/run-shot.png';
    answer(200, is_file($shot) ? (string) file_get_contents($shot) : base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='
    ), 'image/png');
    return;
}
if (preg_match('#^/public/#', $path)) {
    // Same drawing as the Pathly badge (packages/shared/src/statusBadge.ts), state "ok".
    answer(200, '<svg xmlns="http://www.w3.org/2000/svg" width="90" height="20" role="img" aria-label="Pathly: ok">'
        . '<title>Pathly: ok</title><rect width="50" height="20" fill="#555"/><rect x="50" width="40" height="20" fill="#2e7d32"/>'
        . '<g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">'
        . '<text x="25" y="14">pathly</text><text x="70" y="14">ok</text></g></svg>', 'image/svg+xml');
    return;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth    = $headers['Authorization'] ?? '';
file_put_contents(LOG, json_encode([
    'method' => $method,
    'path'   => $path,
    'query'  => (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY),
    'auth'   => $auth === 'Bearer ' . KEY,
    'agent'  => $headers['User-Agent'] ?? '',
    'body'   => json_decode($body, true),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

if ($auth !== 'Bearer ' . KEY) {
    answer(401, ['error' => 'Clé inconnue']);
    return;
}

$route = $method . ' ' . $path;
switch (true) {
    case $route === 'GET /v1/account':
        answer(200, [
            'organization' => ['id' => 'org', 'name' => 'Studio Test'],
            'space'        => null,
            'plan'         => ['id' => 'solo', 'name' => 'Solo', 'priceMonthlyEur' => 0],
            'billingUrl'   => 'https://pathlyhq.com/billing',
            'quotas'       => [
                'ping' => ['used' => 1, 'limit' => 5, 'remaining' => 4],
                'flow' => ['used' => 0, 'limit' => 1, 'remaining' => 1],
            ],
        ]);
        break;
    case $route === 'GET /v1/scenarios':
        answer(200, ['items' => [], 'nextCursor' => null]);
        break;
    case $route === 'POST /v1/scenarios':
        answer(201, ['id' => 'ping-it', 'type' => 'http']);
        break;
    case (bool) preg_match('#^POST /v1/scenarios/[^/]+/run$#', $route):
        answer(200, ['ok' => true, 'queued' => true, 'jobId' => 'job']);
        break;
    case (bool) preg_match('#^GET /v1/scenarios/(.+)$#', $route, $m):
        $flow = $m[1] === 'flow-it';
        answer(200, ['id' => $m[1], 'name' => $flow ? 'Flow · localhost' : 'Ping · localhost', 'lastStatus' => $flow ? 'fail' : 'ok']);
        break;
    case $route === 'GET /v1/runs':
        // The Flow failed once, the Ping never.
        $flow = strpos((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), 'scenarioId=flow-it') !== false;
        answer(200, ['items' => [['id' => $flow ? 'run-it' : 'run-ok', 'status' => $flow ? 'fail' : 'ok', 'created_at' => '2026-11-02T10:00:00Z']]]);
        break;
    case $route === 'GET /v1/runs/run-it':
        answer(200, ['id' => 'run-it', 'status' => 'fail', 'created_at' => '2026-11-02T10:00:00Z', 'message' => 'The "Send" button was not found',
            'screenshotUrl' => 'http://localhost:8892/public/run-shot/token', 'screenshotExpiresAt' => '2026-11-02T10:15:00Z']);
        break;
    case $route === 'POST /v1/flow-assistant/detect':
        answer(200, ['available' => true, 'reason' => null, 'siteKind' => 'joomla', 'ai' => false, 'limit' => 2, 'remaining' => 2]);
        break;
    case $route === 'POST /v1/flow-assistant/trials':
        answer(202, ['id' => 'trial-it']);
        break;
    case $route === 'GET /v1/flow-assistant/trials/trial-it':
        answer(200, ['item' => ['id' => 'trial-it', 'status' => 'ready', 'siteKind' => 'joomla', 'proposals' => [
            ['index' => 0, 'kind' => 'login', 'source' => 'template', 'steps' => [1, 2, 3], 'replay' => ['ok' => true]],
            ['index' => 1, 'kind' => 'contact', 'source' => 'template', 'steps' => [1, 2, 3, 4], 'replay' => ['ok' => true]],
        ]]]);
        break;
    case $route === 'POST /v1/flow-assistant/trials/trial-it/save':
        answer(201, ['item' => ['id' => 'flow-it', 'name' => 'Flow · localhost']]);
        break;
    case $route === 'POST /v1/status-pages':
        answer(201, ['id' => 'org', 'kind' => 'organization', 'public' => true, 'url' => 'https://pathlyhq.com/status/studio-test',
            'badgeUrl' => 'http://localhost:8892/public/status/studio-test/badge.svg']);
        break;
    default:
        answer(404, ['error' => 'Introuvable']);
}
