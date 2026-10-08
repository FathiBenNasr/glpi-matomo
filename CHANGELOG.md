# Changelog

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
