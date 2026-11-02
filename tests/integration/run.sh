#!/usr/bin/env bash
# Integration test on the official Joomla Docker image, against a simulated
# Pathly API: install, configure, Lumi, update hook, badge, uninstall.
#
#   php bin/build.php
#   JOOMLA_IMAGE=joomla:5-php8.2-apache tests/integration/run.sh
#
# KEEP=1 leaves the containers running (screenshots) and skips the uninstall.
set -euo pipefail

cd "$(dirname "$0")"
COMPOSE="docker compose -f docker-compose.yml"
SITE="http://localhost:8891"
KEY="sp_integration_test_key_0123456789"
JAR="$(mktemp)"
HTML="$(mktemp)"
FAILED=0

cleanup() {
  rm -f "$JAR" "$HTML"
  if [ "${KEEP:-0}" != "1" ]; then
    $COMPOSE down -v --remove-orphans >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

ok() { echo "  ok   $1"; }
ko() { echo "  FAIL $1"; FAILED=1; }
check() { if "${@:2}"; then ok "$1"; else ko "$1"; fi; }

sql() { MSYS_NO_PATHCONV=1 $COMPOSE exec -T db mariadb -N -ujoomla -pjoomla-password-it joomla -e "$1" 2>/dev/null; }
cli() { MSYS_NO_PATHCONV=1 $COMPOSE exec -T -u www-data joomla php cli/joomla.php "$@" --no-interaction; }
requests() { curl -fsS http://localhost:8892/_requests; }
reset_mock() { curl -fsS http://localhost:8892/_reset >/dev/null; }
# Number of requests to the simulated API containing a fixed string.
count() { requests | grep -cF -- "$1" || true; }
has() { requests | grep -qF -- "$1"; }
is() { [ "$1" = "$2" ]; }
page_has() { grep -qF -- "$1" "$HTML"; }
page_lacks() { ! grep -qF -- "$1" "$HTML"; }
page() { curl -fsS -L -b "$JAR" -c "$JAR" "$@" -o "$HTML"; }
post() { curl -fsS -b "$JAR" -c "$JAR" -o /dev/null -L --data "$1" "$2"; }
token() { grep -oE 'name="[a-f0-9]{32}" value="1"' "$HTML" | head -1 | cut -d'"' -f2; }
action() {
  page "$SITE/administrator/index.php"
  post "action=$1&index=${2:-0}&$(token)=1" "$SITE/administrator/index.php?option=com_ajax&group=system&plugin=pathly&format=raw"
}

PKG=$(ls ../../build/dist/pkg_pathly-*.zip | head -1)
echo "Package: $PKG, image: ${JOOMLA_IMAGE:-joomla:5-php8.2-apache}"

$COMPOSE down -v --remove-orphans >/dev/null 2>&1 || true
$COMPOSE up -d --quiet-pull

echo "Waiting for Joomla to install itself…"
for _ in $(seq 1 120); do
  if $COMPOSE exec -T joomla sh -c 'test -f configuration.php && test ! -d installation' 2>/dev/null \
    && curl -fsS -o /dev/null "$SITE/administrator/index.php"; then break; fi
  sleep 3
done
P="joom_"
echo "Joomla $(sql "SELECT JSON_VALUE(manifest_cache, '$.version') FROM ${P}extensions WHERE element = 'joomla' AND type = 'file'")"

echo "1. Install"
# The installer removes the archive after unpacking it: install from a copy.
MSYS_NO_PATHCONV=1 $COMPOSE exec -T -u www-data joomla cp "/pkg/$(basename "$PKG")" /tmp/pkg_pathly.zip
cli extension:install --path=/tmp/pkg_pathly.zip >/dev/null
check "system and content plugins on" is "$(sql "SELECT COUNT(*) FROM ${P}extensions WHERE element = 'pathly' AND type = 'plugin' AND enabled = 1")" "2"
check "box on the home dashboard" is "$(sql "SELECT CONCAT(position, ':', published) FROM ${P}modules WHERE module = 'mod_pathly'")" "cpanel:1"
check "nothing sent to Pathly before configuration" is "$(requests)" ""

echo "2. Administrator"
page "$SITE/administrator/index.php"
LOGIN_TOKEN=$(token)
RETURN=$(grep -oE 'name="return" value="[^"]+"' "$HTML" | head -1 | cut -d'"' -f4)
post "username=pathlyadmin&passwd=integration-password-it&option=com_login&task=login&return=$RETURN&$LOGIN_TOKEN=1" "$SITE/administrator/index.php"
page "$SITE/administrator/index.php"
check "logged in, box shown with the sign-up link" page_has "login?mode=signup&amp;ref=joomla&amp;utm_source=joomla&amp;utm_medium=module"

echo "3. Configure (plugin manager)"
EID=$(sql "SELECT extension_id FROM ${P}extensions WHERE element = 'pathly' AND folder = 'system'")
FORM="jform[enabled]=1&jform[access]=1&jform[ordering]=0&jform[note]=&jform[folder]=system&jform[element]=pathly&task=plugin.save"
page "$SITE/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=$EID"
post "jform[params][api_key]=$KEY&jform[params][badge]=1&$FORM&$(token)=1" "$SITE/administrator/index.php?option=com_plugins&view=plugin&layout=edit&extension_id=$EID"
check "key checked" has '"method":"GET","path":"/v1/account"'
check "key sent in the header, every time" is "$(count '"auth":false')" "0"
check "Ping created on the site" has '"body":{"name":"Ping · localhost","type":"http","url":"http://localhost:8891","intervalSec":300,"tags":["joomla"]}'
check "versions of Joomla and of the module in the User-Agent" sh -c "curl -fsS http://localhost:8892/_requests | grep -qE '\"agent\":\"pathly-joomla/[0-9.]+ Joomla/[0-9.]+\"'"
check "status page published for the badge" is "$(count '"path":"/v1/status-pages"')" "1"
check "state saved" is "$(sql "SELECT JSON_VALUE(custom_data, '$.ping_id') FROM ${P}extensions WHERE extension_id = $EID")" "ping-it"
page "$SITE/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=$EID"
check "key never written back into the page" page_lacks "$KEY"
post "jform[params][api_key]=&jform[params][badge]=1&$FORM&$(token)=1" "$SITE/administrator/index.php?option=com_plugins&view=plugin&layout=edit&extension_id=$EID"
check "empty field keeps the key" is "$(sql "SELECT JSON_VALUE(params, '$.api_key') FROM ${P}extensions WHERE extension_id = $EID")" "$KEY"
check "no duplicate Ping on the second save" is "$(count '"method":"POST","path":"/v1/scenarios",')" "1"

echo "4. Lumi"
reset_mock
action lumi_start
check "Lumi asked with the URL of the site" has '"body":{"url":"http://localhost:8891","confirmOwner":true}'
page "$SITE/administrator/index.php"
FIRST=$(grep -oE 'name="action" value="lumi_save"><input type="hidden" name="index" value="[0-9]"' "$HTML" | head -1 | grep -oE '[0-9]"$' | tr -d '"')
check "proposals shown, contact form first" is "$FIRST" "1"
action lumi_save 1
check "Flow saved" is "$(count '/trials/trial-it/save')" "1"
check "Flow kept in the state" is "$(sql "SELECT JSON_VALUE(custom_data, '$.flow_id') FROM ${P}extensions WHERE extension_id = $EID")" "flow-it"
page "$SITE/administrator/index.php"
check "box: last failure" page_has "button was not found"
check "box: screenshot of the failure" page_has "/public/run-shot/token"

echo "5. Replay after updates"
reset_mock
cli extension:install --url="http://mock:8080/dummy-1.0.0.zip" >/dev/null
check "install of an extension: one replay of the Flow" is "$(count '"method":"POST","path":"/v1/scenarios/flow-it/run"')" "1"
reset_mock
page "$SITE/administrator/index.php?option=com_installer&view=update"
post "task=update.find&$(token)=1" "$SITE/administrator/index.php?option=com_installer&view=update"
UPDATE_ID=$(sql "SELECT update_id FROM ${P}updates WHERE element = 'file_pathlydummy' LIMIT 1")
check "update found by Joomla" test -n "$UPDATE_ID"
page "$SITE/administrator/index.php?option=com_installer&view=update"
post "task=update.update&cid[]=$UPDATE_ID&$(token)=1" "$SITE/administrator/index.php?option=com_installer&view=update"
check "extension updated" is "$(sql "SELECT JSON_VALUE(manifest_cache, '$.version') FROM ${P}extensions WHERE element = 'file_pathlydummy'")" "1.0.1"
check "onExtensionAfterUpdate: one replay of the Flow" is "$(count '"method":"POST","path":"/v1/scenarios/flow-it/run"')" "1"

echo "6. Badge"
CATID=$(sql "SELECT id FROM ${P}categories WHERE extension = 'com_content' AND published = 1 ORDER BY id LIMIT 1")
sql "INSERT INTO ${P}content (title, alias, introtext, \`fulltext\`, state, catid, created, created_by, modified, publish_up, access, language, attribs, metadesc, metakey, urls, images, metadata, note) VALUES ('Status', 'status', '<p>{pathly-badge}</p>', '', 1, $CATID, NOW(), 1, NOW(), NOW(), 1, '*', '{}', '', '', '{}', '{}', '{}', '')"
AID=$(sql "SELECT id FROM ${P}content WHERE alias = 'status'")
curl -fsS "$SITE/index.php?option=com_content&view=article&id=$AID&catid=$CATID" -o "$HTML"
check "shortcode replaced by the badge" page_has "status/studio-test/badge.svg"
check "no shortcode left" page_lacks "{pathly-badge}"

# JED Checker, the official tool of the Joomla Extensions Directory. JEDCHECKER
# is a checkout of github.com/joomla-extensions/jedchecker (the CI clones it).
if [ -n "${JEDCHECKER:-}" ]; then
  echo "6b. JED Checker $(grep -oE '<version>[^<]+' "$JEDCHECKER/jedchecker.xml" | cut -d'>' -f2)"
  JC=pathly-joomla-it-joomla-1
  docker cp "$JEDCHECKER" "$JC:/tmp/jedchecker-src" >/dev/null
  docker cp "$PKG" "$JC:/tmp/pkg_pathly-jed.zip" >/dev/null
  # Zip the checker to install it, and unpack our package (zips inside zips) where it looks.
  MSYS_NO_PATHCONV=1 $COMPOSE exec -T joomla php -r '
    $zip = new ZipArchive(); $zip->open("/tmp/jedchecker.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("/tmp/jedchecker-src", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { $n = substr($f->getPathname(), 20); if (strpos($n, ".git") !== 0) { $zip->addFile($f->getPathname(), $n); } }
    $zip->close();
    function unpack_all($file, $dir) { $z = new ZipArchive(); $z->open($file); $z->extractTo($dir); $z->close();
      foreach (glob($dir . "/packages/*.zip") ?: [] as $child) { unpack_all($child, substr($child, 0, -4)); unlink($child); } }
    unpack_all("/tmp/pkg_pathly-jed.zip", "/var/www/html/tmp/jed_checker/unzipped/pkg_pathly");'
  MSYS_NO_PATHCONV=1 $COMPOSE exec -T joomla chown -R www-data:www-data /tmp/jedchecker.zip /var/www/html/tmp/jed_checker
  cli extension:install --path=/tmp/jedchecker.zip >/dev/null
  for RULE in xmlinfo xmlmanifest xmlfiles xmllicense xmlupdateserver gpl jexec encoding errorreporting framework jamss language; do
    page "$SITE/administrator/index.php?option=com_jedchecker&task=police.check&format=raw&rule=$RULE"
    ERRORS=$(grep -c 'alert alert-danger' "$HTML" || true)
    WARNINGS=$(grep -c 'alert alert-warning' "$HTML" || true)
    if [ "$WARNINGS" != "0" ]; then
      sed -e 's/<br \/>/\n/g' -e 's/<[^>]*>//g' "$HTML" | grep -v '^\s*$' | sed "s/^/      $RULE: /" | head -20
    fi
    check "JED Checker $RULE: no error ($WARNINGS warnings)" is "$ERRORS" "0"
    if [ "$ERRORS" != "0" ]; then sed -e 's/<br \/>/\n/g' -e 's/<[^>]*>//g' "$HTML" | head -30; fi
  done
fi

if [ "${KEEP:-0}" = "1" ]; then
  echo "KEEP=1: containers left running on $SITE (admin: pathlyadmin)."
  exit $FAILED
fi

echo "7. Uninstall"
reset_mock
PKG_ID=$(sql "SELECT extension_id FROM ${P}extensions WHERE element = 'pkg_pathly'")
# Joomla 4.4 asks for a confirmation, even without a terminal.
echo yes | MSYS_NO_PATHCONV=1 $COMPOSE exec -T -u www-data joomla php cli/joomla.php extension:remove "$PKG_ID" >/dev/null
check "extensions removed, with their settings and state" is "$(sql "SELECT COUNT(*) FROM ${P}extensions WHERE element IN ('pathly', 'mod_pathly', 'pkg_pathly')")" "0"
check "box removed" is "$(sql "SELECT COUNT(*) FROM ${P}modules WHERE module = 'mod_pathly'")" "0"
check "update server removed" is "$(sql "SELECT COUNT(*) FROM ${P}update_sites WHERE location LIKE '%pathly-joomla%'")" "0"
check "no scheduled task left" is "$(sql "SELECT COUNT(*) FROM ${P}scheduler_tasks WHERE type LIKE '%pathly%'")" "0"
check "nothing sent to Pathly on uninstall" is "$(requests)" ""

if [ "$FAILED" = "1" ]; then
  echo "Integration test FAILED"
  exit 1
fi
echo "Integration test passed."
