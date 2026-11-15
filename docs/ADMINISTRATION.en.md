# Administration — Matomo Tag Manager

[Français](ADMINISTRATION.md) · **English**

This document is for the GLPI administrator: install the plugin, configure it, run it, upgrade it,
troubleshoot it. Step-by-step setup tasks are in [GUIDE.en.md](GUIDE.en.md); risks in
[SECURITE.md](SECURITE.md) (French).

## Installation

### Where the plugin lives

The plugin exists in three places, which normally differ:

| Location | Role |
|---|---|
| Source: the `glpi-matomo` repository (GitHub, <https://github.com/FathiBenNasr/glpi-matomo>) | code, tests, documentation |
| Deployment: `<GLPI>/plugins/matomo/` | what GLPI runs |
| Release archive | what you download to install |

**The deployed folder name is `matomo`**, different from the repository name (`glpi-matomo`): any
other name stops GLPI from recognising the plugin. The `tests/` and `docs/` folders need not be
deployed (`.gitattributes` leaves them out of the archive).

### Procedure

Prerequisites: GLPI 11.x, PHP 8.2 or later, a Matomo Tag Manager container URL (optional at install
time).

1. Put the plugin in `<GLPI>/plugins/matomo/` (release archive, or **Git Plugin Installer**).
2. Restore SELinux contexts if the server enforces them (`restorecon -R <GLPI>/plugins/matomo`).
3. Install and enable as the web server user — **never as root**, which would create caches Apache
   cannot read:

   ```bash
   cd <GLPI>
   sudo -u apache php bin/console plugin:install matomo
   sudo -u apache php bin/console plugin:activate matomo
   sudo -u apache php bin/console cache:clear
   ```

4. Check: the plugin shows as **Enabled** in **Setup → Plugins**, and its settings page opens.

Installation creates **no table**: it only generates the pseudonym salt (see below).

## Configuration

The settings page is `front/config.php`, opened from **Setup → Plugins → Matomo Tag Manager**. It
requires the `config` right at update level (usually the super-administrator profile); the plugin
registers **no right of its own**.

| Setting | Stored key | Default | Values and effect |
|---|---|---|---|
| **Container URL** | `container_url` | empty | `https://host[/path]/container_<id>.js`; the id is letters, digits and `_`. Empty = no measurement. Refused: HTTP, credentials in the URL, query, fragment, spaces or quotes, a path segment starting with a dot. |
| **Also track the login screen and other anonymous pages** | `track_anonymous` | `0` | `1` loads the container on the login screen and anonymous pages (no identity, no token in the address). |
| **Identity of the logged-in user** | `user_id_mode` | `none` | `none` · `pseudonym` (HMAC-SHA256 of the id, cut to 32 hex characters, key = the plugin salt) · `login` (GLPI login in clear). An unknown value falls back to `none`. |
| (internal) pseudonym salt | `pseudonym_salt` | created at install | 64 random hex characters, **encrypted with GLPI's key**; never displayed. |

Settings are written to the core configuration, `plugin:matomo` context (GLPI's `glpi_configs` table,
reached only through the core API). The page labels are in English.

**Recommended security header** on GLPI's virtual host, to bound the third-party code:

```text
Content-Security-Policy: script-src 'self' https://stats.example.com; object-src 'none'; base-uri 'self'
```

On the Matomo side: restrict the *Publish* right of the container to its owner, anonymise IP
addresses, honour *Do Not Track* or offer an opt-out, and exclude the `password_forget_token`,
`token` and `_glpi_csrf_token` parameters in the site settings.

## Operations

- **Logs**: the plugin writes no log and no file. PHP errors go to GLPI's logs
  (`files/_log/php-errors.log` under GLPI's data directory, or the location your installation
  configures — to be checked).
- **Monitoring**: nothing to monitor on the GLPI side. In the browser, the console should show the
  container script loaded on a page of a user without administration rights, and nothing on an
  administration page. Matomo's availability is independent: if Matomo is unreachable, the
  asynchronous script fails without blocking the page.
- **Backup**: nothing specific to the plugin. The settings and the salt follow GLPI's database backup;
  the salt is encrypted with GLPI's key, which must therefore be backed up too. Without that key the
  pseudonym is no longer produced (fails closed).
- **File permissions**: the plugin writes nowhere at runtime; read-only is enough for the web server
  user.

## Upgrade and uninstall

**Upgrade.** Replace the files of `plugins/matomo/` with those of the new version, then
**reinstall** — especially when the version changes: a mismatch between code and recorded state makes
GLPI disable the plugin, and its pages return 404.

```bash
cd <GLPI>
sudo -u apache php bin/console plugin:install --force matomo
sudo -u apache php bin/console cache:clear
```

Versions 1.0.5 and 1.1.3 (and later) require this reinstall: it creates the pseudonym salt.
Pseudonyms produced by 1.0.3 / 1.1.1 change once. Each version is detailed in
[CHANGELOG.md](../CHANGELOG.md) (French).

**Uninstall.** `sudo -u apache php bin/console plugin:uninstall matomo` deletes the settings
(`container_url`, `track_anonymous`, `user_id_mode`, pseudonym salt). Since the salt is deleted, a later
reinstall produces different pseudonyms. To merely suspend, clear **Container URL** instead of
uninstalling.

## Troubleshooting

| Symptom | Likely cause | Remedy |
|---|---|---|
| Nothing loads on a user page | Empty URL; inactive plugin; excluded page; the address or referrer carries a `token`, `passw`, `secret`, `code` or `state` parameter; impersonated session | Check the URL, the plugin state and the test account |
| Nothing loads with a "technician" account | It holds, in one of **its profiles** (even inactive), an administration right listed in [SECURITE.md](SECURITE.md#menaces-et-mesures) | Intended; test with a profile without those rights |
| A right check throws an exception | Fail closed: the plugin treats the session as privileged | Look for the error in the PHP log |
| "Container URL must be an https:// …" | Refused URL shape | Paste the exact container address |
| "Unknown user identity mode" | Forged request or altered form | Save again from the page |
| No pseudonym in `pseudonym` mode | Salt missing or unreadable (GLPI key changed) | `plugin:install --force matomo` |
| Plugin pages return 404 after a version bump | Code and database out of step; GLPI disabled the plugin | Reinstall (`--force`), re-enable |
| Nothing changes after a JavaScript change | Browser or GLPI cache | `cache:clear`; hard reload |
| `glpiUserId` does not reach Matomo | *Data Layer* variable not created or not set as User ID | See [GUIDE.en.md](GUIDE.en.md#using-the-identity-in-matomo) |
| CSP violation in the console | The container host is not in `script-src` | Add the exact Matomo host |

Support: <https://github.com/FathiBenNasr/glpi-matomo/issues>, or contact@convergent.tn for a
vulnerability ([SECURITY.md](../SECURITY.md)).
