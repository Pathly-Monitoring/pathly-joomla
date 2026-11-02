// Screenshots of the publication folder, taken on the test site left running
// by `KEEP=1 tests/integration/run.sh`. Needs Playwright (npm i playwright),
// or PLAYWRIGHT_FROM set to a folder where it is installed.
//
//   node tests/integration/screenshots.mjs
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";

const here = dirname(fileURLToPath(import.meta.url));
const require = createRequire(process.env.PLAYWRIGHT_FROM ? join(process.env.PLAYWRIGHT_FROM, "x.js") : import.meta.url);
const { chromium } = require("playwright");
const out = join(here, "..", "..", "publication", "screenshots");
const site = "http://localhost:8891";
const key = "sp_integration_test_key_0123456789";
mkdirSync(out, { recursive: true });

function sql(query) {
  return execFileSync(
    "docker",
    ["compose", "-f", join(here, "docker-compose.yml"), "exec", "-T", "db", "mariadb", "-N", "-ujoomla", "-pjoomla-password-it", "joomla", "-e", query],
    { encoding: "utf8", env: { ...process.env, MSYS_NO_PATHCONV: "1" } },
  ).trim();
}

function setPlugin(params, state) {
  const q = (v) => `'${JSON.stringify(v).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'`;
  sql(`UPDATE joom_extensions SET params = ${q(params)}, custom_data = ${state ? q(state) : "''"} WHERE element = 'pathly' AND folder = 'system'`);
}

const browser = await chromium.launch();

async function admin() {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  await page.goto(`${site}/administrator/index.php`);
  await page.fill("#mod-login-username", "pathlyadmin");
  await page.fill("#mod-login-password", "integration-password-it");
  await page.click("#btn-login-submit");
  await page.waitForLoadState("networkidle");
  return { context, page };
}

async function box(name) {
  const { context, page } = await admin();
  const card = page.locator(".mod-pathly").locator("xpath=ancestor::div[contains(@class, 'card')][1]");
  await card.scrollIntoViewIfNeeded();
  await page.waitForLoadState("networkidle");
  await card.screenshot({ path: join(out, name) });
  await context.close();
}

// The capture of the failed step shown in the box: a real capture of the test site.
{
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  await page.goto(`${site}/index.php?option=com_users&view=login`);
  await page.screenshot({ path: join(here, "mock", "run-shot.png") });
  await context.close();
}

// No welcome tour nor statistics prompt over the dashboard.
sql("UPDATE joom_extensions SET enabled = 0 WHERE folder = 'system' AND element IN ('guidedtours', 'stats')");
sql(`UPDATE joom_modules SET params = '{"bootstrap_size":"12"}' WHERE module = 'mod_pathly'`);

const connected = { connected: true, organization: "Studio Test", space: null, site_url: site, ping_id: "ping-it" };

// 1. Before any key: sign-up link or key.
setPlugin({ badge: "0" }, null);
await box("1-dashboard-connect.png");

// 2. Settings: the key is saved and never shown again.
setPlugin({ api_key: key, badge: "1" }, { ...connected, badge_url: "http://localhost:8892/public/status/studio-test/badge.svg", status_url: "https://pathlyhq.com/status/studio-test" });
{
  const { context, page } = await admin();
  const id = sql("SELECT extension_id FROM joom_extensions WHERE element = 'pathly' AND folder = 'system'");
  await page.goto(`${site}/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=${id}`);
  await page.waitForLoadState("networkidle");
  await page.screenshot({ path: join(out, "2-settings.png") });
  sql(`UPDATE joom_extensions SET checked_out = NULL, checked_out_time = NULL WHERE extension_id = ${id}`);
  await context.close();
}

// 3. Lumi replayed two Flow: the administrator saves one.
setPlugin({ api_key: key, badge: "1" }, { ...connected, trial_id: "trial-it" });
await box("3-dashboard-lumi.png");

// 4. Ping up, Flow failing: last failure with its capture.
setPlugin({ api_key: key, badge: "1" }, { ...connected, flow_id: "flow-it", last_replay_at: 1793613600, last_replay_reason: "extension", badge_url: "http://localhost:8892/public/status/studio-test/badge.svg", status_url: "https://pathlyhq.com/status/studio-test" });
await box("4-dashboard-failure.png");

// 5. Badge in an article.
{
  const context = await browser.newContext({ viewport: { width: 1280, height: 700 } });
  const page = await context.newPage();
  const id = sql("SELECT id FROM joom_content WHERE alias = 'status'");
  sql(`UPDATE joom_content SET title = 'Service status', introtext = '<p>Our booking path is watched by Pathly. Current state: {pathly-badge}</p>' WHERE id = ${id}`);
  await page.goto(`${site}/index.php?option=com_content&view=article&id=${id}`);
  await page.waitForLoadState("networkidle");
  await page.screenshot({ path: join(out, "5-badge.png") });
  await context.close();
}

await browser.close();
console.log(`Screenshots in ${out}`);
