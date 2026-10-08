# Changelog

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
