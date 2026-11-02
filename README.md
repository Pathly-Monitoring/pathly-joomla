# Pathly for Joomla

_Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold._

This package connects a Joomla 4.4 or 5 site to [Pathly](https://pathlyhq.com). Version française : [README.fr.md](README.fr.md).

- **Ping** on the home page of the site, created as soon as the API key is saved. If a Ping already watches the same address, the package attaches to it: no duplicate.
- **Flow prepared by Lumi**, the Pathly Flow assistant. Lumi recognises Joomla and has templates for the contact form and the login. Every Flow Lumi proposes is replayed once before it is shown, and you choose the one to keep in one click.
- **Replay after updates**: when Joomla or an extension is updated (or a newer version is installed by hand), Pathly runs the Flow again, once, at the end of the update.
- **Dashboard box** in the administrator: state of the Ping and of the Flow, date and screenshot of the last failure, plan and quotas, links to Pathly.
- **Availability badge** for articles (`{pathly-badge}`), **off by default**.

## Requirements

- Joomla 4.4 (PHP 7.4 or later) or Joomla 5 (PHP 8.1 or later).
- A Pathly account. The Solo plan is free, without time limit: [create an account](https://pathlyhq.com/en/login?mode=signup&ref=joomla&utm_source=joomla&utm_medium=module).
- A site reachable from the internet. A site in maintenance mode, behind a password or on a local address cannot be watched.

## Install

1. Download `pkg_pathly-x.y.z.zip` from the [releases](https://github.com/Pathly-Monitoring/pathly-joomla/releases).
2. In the administrator: **System → Install → Extensions → Upload Package File**.

The package installs three extensions: the **System - Pathly** plugin, the **Content - Pathly badge** plugin and the **Pathly** box of the home dashboard. Nothing is sent to Pathly until an administrator saves an API key.

## Configure

1. In Pathly, **Settings**: create an API key. Prefer a key limited to one space, with these scopes: `org:read`, `scenarios:read`, `scenarios:write`, `runs:read`, `runs:trigger`, `assistant:run`, `assistant:save`, `status:read`, `status:write`.
2. In Joomla, **System → Plugins → System - Pathly**: paste the key and save. The key is checked and the Ping is created.
3. On the home dashboard, the Pathly box offers **Prepare the Flow with Lumi**. Refresh after a minute, then save the Flow you want to watch.

The key is never shown again after it is saved. Leave the field empty to keep it, or use **Disconnect** in the box to remove it from the site.

### Stores (VirtueMart, HikaShop)

Lumi has no cart template for VirtueMart or HikaShop. On such a store, Lumi hands over to its AI for the cart: an AI in the EU (Vertex AI, europe-west1) reads the structure and the text of the page, never the screenshots, and it is not used for training. You still validate the Flow before it is saved.

### Test carts

A Flow walks your site like a visitor. A cart Flow adds a product to a cart and stops at the payment page. A contact Flow fills the form and stops before sending it. Lumi pays nothing, sends no form and only types test values. A cart left by a Flow can still trigger an abandoned-cart reminder in your store: exclude these test sessions from your reminders if needed.

## Replay after an update

The system plugin listens to the Joomla events `onExtensionAfterUpdate` (update manager), `onExtensionAfterInstall` (a newer version uploaded by hand) and `onJoomlaAfterUpdate` (Joomla itself). Updating several extensions at once asks for one run only, at the end of the request, once every update is installed. The run is `POST /v1/scenarios/{id}/run` on the Flow, or on the Ping when there is no Flow yet. **Run now** in the box does the same by hand.

## Availability badge

Turn **Availability badge** on in the settings of the System - Pathly plugin. This publishes the Pathly status page of your organisation (or of the space of the key). Then write `{pathly-badge}` in an article. While the badge is off, the shortcode is removed from the page. The browsers of your visitors load the badge image from `api.pathlyhq.com`.

## Data and security

- The extension only calls `https://api.pathlyhq.com`. It never calls an address typed by a user.
- It sends the address of the site, the version of Joomla and the version of the extension (in the `User-Agent`). No customer data, no order.
- Nothing is sent before an administrator saves an API key.
- The key is stored in the parameters of the plugin, in the Joomla database. It is never written back into a page and never written to a log.
- The settings and the actions of the box are for Super Users only, and every action is protected by the Joomla form token (CSRF).
- Uninstalling removes the settings and the state with the extensions. The extension creates no table and no scheduled task. Your Ping and Flow stay in Pathly: delete them there if you no longer need them.
- Updates: like every Joomla extension with an update server, Joomla checks `raw.githubusercontent.com` for a newer version when you look for updates.

## Development

```bash
composer install
composer lint         # PHP_CodeSniffer, PSR-12 and PHP 7.4 compatibility
composer coverage     # PHPUnit, fails below 100 % of lines and branches (Xdebug)
php bin/build.php     # build/dist/pkg_pathly-x.y.z.zip
tests/integration/run.sh                                   # Joomla 5 in Docker, simulated API
JOOMLA_IMAGE=joomla:4.4-php8.1-apache tests/integration/run.sh
JEDCHECKER=/path/to/jedchecker tests/integration/run.sh    # also runs the JED Checker
```

Without a local PHP: `docker build -t pathly-joomla-dev -f bin/dev.Dockerfile bin`, then `docker run --rm -v "$PWD:/app" -w /app pathly-joomla-dev composer coverage`.

The integration test installs the package on the official Joomla image, saves a key, asks Lumi, saves the Flow, updates an extension through the Joomla update manager, checks the badge, runs the JED Checker, then uninstalls. `KEEP=1` leaves the site running on http://localhost:8891, and `tests/integration/screenshots.mjs` takes the screenshots of the `publication/` folder.

Versions follow [Conventional Commits](https://www.conventionalcommits.org/) and are released by release-please.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
