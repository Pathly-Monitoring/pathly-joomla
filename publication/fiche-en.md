# JED listing — English

## Title

Pathly for Joomla

## Short description (one line)

Ping your site, prepare a Flow of your contact form or login with Lumi, and replay it after every Joomla update.

## Description

_Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold._

A site can answer 200 while its contact form or its login is broken. Pathly for Joomla watches both.

- **Ping**: as soon as you save your Pathly API key, the home page of the site is watched. If a Ping already watches this address, the extension attaches to it, without duplicate.
- **Flow prepared by Lumi**: Lumi, the Pathly Flow assistant, recognises Joomla and prepares a Flow of your contact form or of your login. Every Flow Lumi proposes is replayed once before it is shown. You choose the one to keep, in one click.
- **Replay after updates**: after an update of Joomla or of an extension, Pathly runs your Flow again, once, when the updates are done.
- **Dashboard box**: state of the Ping and of the Flow, date and screenshot of the last failure, plan and quotas, link to Pathly.
- **Availability badge**: `{pathly-badge}` in an article shows the state of your site. Off by default.

Stores: Lumi has no cart template for VirtueMart or HikaShop. On these stores, an AI in the EU reads the structure and the text of the page to prepare the cart Flow, never the screenshots, and without training.

Privacy: the extension only calls `api.pathlyhq.com`, and only after an administrator saved a key. It sends the address of the site and the versions of Joomla and of the extension. No customer data, no order. The key is never shown again after it is saved. Settings and actions are for Super Users only.

Requirements: Joomla 4.4 (PHP 7.4+) or Joomla 5 (PHP 8.1+), a Pathly account. The Solo plan is free, without time limit.

## FAQ

**Do I need a paid plan?**
No. The Solo plan is free, without time limit, and includes Ping and one Flow. Paid plans add more Ping and Flow, and shorter intervals.

**Does Lumi buy anything or send my forms?**
No. Lumi pays nothing, sends no form and only types test values. A cart Flow stops at the payment page. A contact Flow stops before sending.

**Can a Flow trigger my abandoned-cart reminders?**
Yes, a cart left by a Flow can. Exclude these test sessions from your reminders if needed.

**What is sent to Pathly?**
The address of the site, the version of Joomla and the version of the extension. Nothing before you save a key.

**What happens when I uninstall?**
The settings and the state are removed with the extension. Your Ping and Flow stay in Pathly until you delete them there.

**My site is in maintenance mode or behind a password.**
Pathly watches your site from the internet like a visitor. A site that visitors cannot reach cannot be watched.

## Changelog

### 0.1.0

- First version for Joomla 4.4 and 5: API key in the plugin manager, Ping of the site, Flow prepared by Lumi, replay after every update of Joomla or of an extension, dashboard box, availability badge (off by default).

## Links

- Website: https://pathlyhq.com
- Download: https://github.com/Pathly-Monitoring/pathly-joomla/releases (public once the repository is published)
- Documentation: https://github.com/Pathly-Monitoring/pathly-joomla#readme
- Support: contact@pathlyhq.com
- License: GPL-2.0-or-later
