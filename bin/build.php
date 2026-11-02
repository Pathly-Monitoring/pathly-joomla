<?php

/**
 * Builds the installable package: build/dist/pkg_pathly-<version>.zip.
 *
 *     php bin/build.php
 *
 * The package holds the three extensions, each in its own zip, as Joomla
 * expects: the system plugin, the content plugin and the administrator module.
 */

$root    = dirname(__DIR__);
$src     = $root . '/src';
$out     = $root . '/build/dist';
$version = '';
if (preg_match("/VERSION = '([^']+)'/", (string) file_get_contents($src . '/plugins/system/pathly/src/Version.php'), $m)) {
    $version = $m[1];
}
if ($version === '') {
    fwrite(STDERR, "Version not found.\n");
    exit(1);
}

// Every manifest carries the same version as the code.
foreach (glob($src . '/{pkg_pathly.xml,plugins/*/pathly/pathly.xml,modules/mod_pathly/mod_pathly.xml}', GLOB_BRACE) as $manifest) {
    if (strpos((string) file_get_contents($manifest), '<version>' . $version . '</version>') === false) {
        fwrite(STDERR, "Version $version missing in $manifest\n");
        exit(1);
    }
}

/**
 * Adds a folder to a zip, files sorted for a stable archive.
 */
function addFolder(ZipArchive $zip, string $folder, string $prefix = ''): void
{
    $files    = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $files[] = $file->getPathname();
    }
    sort($files);
    foreach ($files as $path) {
        $name = $prefix . str_replace('\\', '/', substr($path, strlen($folder) + 1));
        addFile($zip, $path, $name);
    }
}

/**
 * Adds one file with a fixed date, so two builds of the same code match.
 */
function addFile(ZipArchive $zip, string $path, string $name): void
{
    $zip->addFile($path, $name);
    $zip->setMtimeName($name, 1767225600);
}

function makeZip(string $target, callable $fill): void
{
    $zip = new ZipArchive();
    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Cannot write $target\n");
        exit(1);
    }
    $fill($zip);
    $zip->close();
}

if (!is_dir($out . '/packages') && !mkdir($out . '/packages', 0777, true)) {
    exit(1);
}

$children = [
    'plg_system_pathly'  => $src . '/plugins/system/pathly',
    'plg_content_pathly' => $src . '/plugins/content/pathly',
    'mod_pathly'         => $src . '/modules/mod_pathly',
];
foreach ($children as $name => $folder) {
    makeZip($out . '/packages/' . $name . '.zip', static function (ZipArchive $zip) use ($folder, $root) {
        addFolder($zip, $folder);
        addFile($zip, $root . '/LICENSE', 'LICENSE.txt');
    });
}

$package = $out . '/pkg_pathly-' . $version . '.zip';
makeZip($package, static function (ZipArchive $zip) use ($src, $out, $root, $children) {
    addFile($zip, $src . '/pkg_pathly.xml', 'pkg_pathly.xml');
    addFile($zip, $src . '/script.php', 'script.php');
    addFile($zip, $root . '/LICENSE', 'LICENSE.txt');
    addFolder($zip, $src . '/language', 'language/');
    foreach (array_keys($children) as $name) {
        addFile($zip, $out . '/packages/' . $name . '.zip', 'packages/' . $name . '.zip');
    }
});

echo $package, PHP_EOL;
