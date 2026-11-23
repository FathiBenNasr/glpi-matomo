# Administration — Matomo Tag Manager for GLPI

[Français](ADMINISTRATION.md) · **English**

For the GLPI system administrator. The deployed folder is named `matomo` (the plugin key), whatever the repository is called.

## Installation

Prerequisites: GLPI 11.x (`setup.php` refuses anything outside 11.0.0 – 11.99.99), the URL of a Matomo Tag Manager container.

1. Put the plugin in `<GLPI root>/plugins/matomo/` (archive from the [releases page](https://github.com/FathiBenNasr/glpi-matomo/releases),
   or Git Plugin Installer). The folder must be named `matomo`.
2. Make the files readable by the web server; on a system with SELinux, restore the contexts
   (`restorecon -R <GLPI root>/plugins/matomo`), otherwise the server silently reads nothing.
3. Install, activate, clear the cache, **as the web server user** (never `root`, which creates caches the server
   cannot write):
   ```bash
   cd <GLPI root>
   sudo -u apache php bin/console plugin:install matomo
   sudo -u apache php bin/console plugin:activate matomo
   sudo -u apache php bin/console cache:clear
   ```
4. Configure the plugin (see the [guide](GUIDE.en.md)).

The install creates the pseudonym salt (`Config::ensurePseudonymSalt()`, called by `hook.php`). No table is created.

## Configuration

| Key (context `plugin:matomo`) | Default | Effect |
|---|---|---|
| `container_url` | empty | HTTPS URL of the container; empty = no tracking |
| `track_anonymous` | `0` | `1`: also load the container on anonymous pages |
| `user_id_mode` | `none` | `none`, `pseudonym` or `login`; any other stored value falls back to `none` |
| `pseudonym_salt` | created at install | random salt (64 hex characters), encrypted with the GLPI key; not set by hand |

Changes go through the settings page, restricted to the `config` UPDATE right. No option is read from a file.

## Operations

- **Logs**: the plugin writes no log. PHP errors go to GLPI's and the web server's logs (location depends on the
  installation: to be checked).
- **Monitoring**: tracking is checked from a browser (see "Check that tracking works" in the
  [guide](GUIDE.en.md#check-that-tracking-works)).
- **Backup**: settings live in GLPI's configuration (context `plugin:matomo` of the configuration table), hence in the
  database backup. Without GLPI's encryption key the salt is unreadable and no pseudonym is produced (pseudonyms change).
- **Recommended CSP** on GLPI's virtual host, for example
  `Content-Security-Policy: script-src 'self' https://stats.example.com; object-src 'none'; base-uri 'self'`.
- **Matomo**: restrict the *Publish* right on the container, enable IP anonymisation, exclude the parameters
  `password_forget_token`, `token` and `_glpi_csrf_token`.

## Upgrade and uninstall

**Upgrade**: replace the files, then **reinstall**:

```bash
sudo -u apache php bin/console plugin:install --force matomo
sudo -u apache php bin/console cache:clear
```

Without a reinstall GLPI sees a code version different from the stored one, disables the plugin, and all its pages return
404. Versions 1.0.4 to 1.0.6 explicitly require it (see the [CHANGELOG](../CHANGELOG.md)).

**Uninstall**: `sudo -u apache php bin/console plugin:uninstall matomo`. It removes the keys `container_url`,
`track_anonymous`, `user_id_mode` and `pseudonym_salt` (`hook.php`); then delete the plugin folder.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Nothing loads for an administrator | By design: no container for a privileged right or an administration page | Test with an account without administration rights |
| Nothing loads for anyone | Empty `container_url`, inactive plugin, or a CSP blocking the Matomo host | Check the settings, `plugin:activate matomo`, the browser console |
| Nothing during an "Impersonate" session | By design: the container never runs in an impersonated session | Log in normally |
| Nothing on one particular page | Its address or referrer carries a secret (`*token*`, `*passw*`, `*secret*`, `code`, `state`) | None: it is the defence against a token leak |
| URL refused when saving | Expected shape: `https://host[:port]/…/container_<id>.js` | Fix the address |
| No pseudonym | Salt missing or unreadable: the plugin sends nothing (fails closed) | `plugin:install --force matomo`; if still unreadable, GLPI's key changed |
| 404 on the plugin's pages | GLPI disabled the plugin after a version change | Reinstall (see above) then `plugin:activate matomo` |
| 500 everywhere after a deployment | Files copied with wrong permissions or SELinux contexts | Web server permissions, `restorecon -R`; read the logs |
