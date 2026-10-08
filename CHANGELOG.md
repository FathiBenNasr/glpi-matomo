# Changelog

## [1.1.0] — 2026-10-08

### Changed
- **Requires GLPI 12.0** (min 12.0.0, max 12.99.99); the 11.x line stays on the previous minor.
- No more `csrf_compliant` hook and no `Plugin::getWebDir()` fallback (both gone in GLPI 12).

## [1.0.1] — 2026-10-08

### Fixed
- GLPI 12 readiness, still compatible with GLPI 11: no `inc/includes.php` in web entry points (useless since GLPI 11, deprecated in GLPI 12).

## [1.0.0] — 2026-05-15

### Added
- Initial release
- Matomo Tag Manager container injection on every GLPI page via `ADD_HEADER_TAG` hook
- Admin-configurable container URL (Setup → Plugins → Matomo Tag Manager → Configuration)
- Compatible with GLPI 10.0.0 – 11.x
