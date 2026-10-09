# Changelog

## [1.0.5] — 2026-10-09

Supersedes 1.0.4, which was never published. **Reinstall after upgrading**
(`sudo -u apache php bin/console plugin:install --force matomo`).

### Security
- **The referrer is checked too**: a page reached from a password-reset link (the reset form's result, the
  login screen) would report the token to Matomo as its referrer (`urlref`); such a page loads nothing.
- **Any profile of the session counts**: a super-administrator browsing under the self-service profile can switch
  back to the admin profile from the same page, so a user holding `config`, `profile` or `user` UPDATE in *any*
  of their profiles never loads the container.
- **Administration paths are compared after decoding**: `%63onfig.form.php`, `//` and `/./` no longer slip past
  the server and loader checks.
- 44 PHP tests, 9 loader tests.

## [1.0.4] — 2026-10-09

Security release. **Reinstall after upgrading** (`sudo -u apache php bin/console plugin:install --force matomo`):
the install creates the pseudonym salt, and GLPI disables a plugin whose version changed without a reinstall.

### Security
- **The login screen is no longer tracked by default.** An upgrade from 1.0.2 silently started tracking the
  anonymous pages; it is now an explicit opt-in.
- **A URL carrying a secret is never tracked** — password-reset links (`password_forget_token`), CSRF/API tokens,
  SSO `code`/`state`: the reset token would otherwise land in Matomo's visit log and let any Matomo reader take
  over the GLPI account.
- **No third-party container for administrators nor on administration pages.** A session holding `config`,
  `profile` or `user` UPDATE, and the pages that change configuration, rights, accounts, authentication or API
  tokens, never load the container: whoever can publish in the MTM container must not act as a GLPI administrator.
  The loader re-checks the page and the URL client side.
- **Stricter container URL**: only `https://host/…/container_<id>.js` (no credentials, query or fragment).
- **Pseudonym keyed with the plugin's own random salt**, stored encrypted with the GLPI key, instead of GLPI's
  master key itself. Pseudonyms from 1.0.3 change once.

### Changed
- `declare(strict_types=1)` in every PHP file; translation domain `matomo` for the plugin's strings; the obsolete
  `csrf_compliant` hook (no effect since GLPI 11) is gone.
- `plugin_init_matomo()` is covered by tests (42 PHP tests, 8 loader tests).
- Screenshot bench documentation no longer names an internal address or account, and passes credentials from the
  caller's environment.

## [1.0.3] — 2026-10-08

### Added
- **The login screen is tracked too** (and the other anonymous pages), as the documentation
  always claimed: GLPI only runs `add_javascript` on logged-in pages, so the plugin now also
  uses `add_javascript_anonymous_page`. A setting switches it off.
- **Optional identity of the logged-in user**, pushed to the Matomo Tag Manager data layer as
  `glpiUserId` (map it to the *User ID* field of your Matomo configuration tag). Off by default.
  Two modes: a *pseudonym* — HMAC-SHA256 of the GLPI user id keyed with the instance's own
  GLPI key, stable but not reversible — or the *GLPI login* in clear (personal data: inform
  your users and check your legal basis first). Never sent on anonymous pages.

### Changed
- Settings reach the browser as `<meta>` tags through GLPI's header-tag hooks, escaped by
  GLPI's template. The plugin no longer writes `public/js/mtm-config.js` into its own code
  directory at save time; a leftover copy from an older version is simply no longer loaded.

## [1.0.2] — 2026-10-08

### Fixed
- Declared requirements now match the code: GLPI **11.x** (min 11.0.0, max 11.99.99). Since 1.0.1 the
  configuration page no longer includes `inc/includes.php`, which GLPI 10 still needs; GLPI 10 users stay on
  1.0.0, GLPI 12 users get 1.1.0.

## [1.0.1] — 2026-10-08

### Fixed
- GLPI 12 readiness, still compatible with GLPI 11: no `inc/includes.php` in web entry points (useless since GLPI 11, deprecated in GLPI 12).

## [1.0.0] — 2026-05-15

### Added
- Initial release
- Matomo Tag Manager container injection on every GLPI page via `ADD_HEADER_TAG` hook
- Admin-configurable container URL (Setup → Plugins → Matomo Tag Manager → Configuration)
- Compatible with GLPI 10.0.0 – 11.x
