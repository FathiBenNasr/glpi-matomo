# Matomo Tag Manager — inject a Matomo container into GLPI pages

![Settings page](docs/captures/01-settings.png)

Inject a [Matomo Tag Manager](https://matomo.org/guide/tag-manager/) container into GLPI with zero code changes — just paste your container URL in the plugin settings. The container is loaded on the pages shown to logged-in users (standard and simplified interfaces) — never for administrators nor on administration pages — and, if you switch it on, on the login screen and the other anonymous pages. It can optionally tell Matomo **who** the logged-in user is.

## Features

- Loads your Matomo Tag Manager container on the pages of logged-in users, and optionally on the login screen (off by default)
- **Optional user identity** pushed to the MTM data layer as `glpiUserId`: a non-reversible pseudonym, or the GLPI login (see *Privacy*)
- Configured entirely from the GLPI admin panel — no file or template editing
- Native GLPI plugin hooks + one small static JS loader; asynchronous, zero perceptible overhead
- No custom database table — settings live in GLPI's own configuration store

## Requirements

| Requirement | Version |
|-------------|---------|
| GLPI        | 11.x → plugin 1.0.x · 12.x → plugin 1.1.x · 10.x → plugin 1.0.0 (login screen and identity not available) |
| PHP         | ≥ 8.2 |
| Matomo      | A reachable Matomo Tag Manager container URL |

## Installation

1. Download the latest release archive from the [Releases](https://github.com/FathiBenNasr/glpi-matomo/releases) page (or install it from git with the **Git Plugin Installer** plugin).
2. Extract into your GLPI `plugins/` directory so the path is `plugins/matomo/`.
3. Install & enable as the web user, or via the UI (**Setup → Plugins → Matomo Tag Manager → Install → Enable**):
   ```bash
   sudo -u apache php bin/console plugin:install matomo
   sudo -u apache php bin/console plugin:activate matomo
   sudo -u apache php bin/console cache:clear
   ```

## Usage

1. Go to **Setup → Matomo Tag Manager**.
2. Paste your Matomo Tag Manager **Container URL** (e.g. `https://stats.example.com/js/container_XXXXXXXX.js`).
3. Choose whether to track the login screen, and which **identity** to send (default: none).
4. Click **Save** — the container is injected on the next page load and tracking starts immediately.

To use the identity in Matomo: in Tag Manager, create a *Data-Layer* variable named `glpiUserId`, then set it as the **User ID** of your *Matomo Configuration* variable.

## Configuration

| Setting | Description |
|---------|-------------|
| Container URL | Full HTTPS URL to your MTM container JS (`https://…/js/container_XXXXXXXX.js`). Only the shape of an MTM container is accepted: `https://`, a host, no credentials, query or fragment, a path ending in `/container_<id>.js`. Empty = tracking off. |
| Track the login screen | Also load the container on anonymous pages (login, password reset). **Off by default** (since 1.0.5 / 1.1.3). No identity is ever sent there, and a URL carrying a token (password reset link…) is never tracked. |
| Identity of the logged-in user | **Do not send** (default) · **Pseudonym** — HMAC-SHA256 of the GLPI user id keyed with a random salt of the plugin, stored encrypted with the GLPI key: stable, not reversible, meaningless outside your instance · **GLPI login** — in clear. |

The value is stored in GLPI's core configuration store under the `plugin:matomo` context (no dedicated plugin table is created).

**Upgrading to 1.0.5 / 1.1.3 requires a reinstall** (`plugin:install --force matomo`, as the web user): the install creates the pseudonym salt. Until then the pseudonym mode sends no identity (it fails closed). Pseudonyms produced by 1.0.3 / 1.1.1 change once.

## Privacy

Sending an identity to Matomo links every visit to a person: under the GDPR (and the Tunisian law 2004-63) the **pseudonym is still personal data**, the login even more so. Before enabling either: have a legal basis, inform your users (and update your privacy notice), keep Matomo self-hosted if you can, and prefer the pseudonym. The identity is never sent on anonymous pages, and the setting fails closed: without the plugin's salt no pseudonym is produced, and there is never a fallback to the login. On the Matomo side, enable IP anonymisation, honour *Do Not Track* or offer an opt-out, and exclude the query parameters `password_forget_token`, `token` and `_glpi_csrf_token` in the site settings.

## Permissions

Configuration requires the GLPI core **`config: UPDATE`** right (typically the full-administrator profile) — the same right that protects GLPI's general setup. The plugin registers **no custom right** and writes only to GLPI's configuration store.

## Architecture

- The plugin passes its settings as `<meta>` tags through GLPI's `add_header_tag` / `add_header_tag_anonymous_page` hooks (escaped by GLPI's template), and loads one static script, `public/js/mtm-loader.js`, through `add_javascript` / `add_javascript_anonymous_page`. The loader reads the tags, pushes `glpiUserId` when present, then starts MTM.
- Settings are read from GLPI's core config (`plugin:matomo` context: `container_url`, `track_anonymous`, `user_id_mode`, `pseudonym_salt` encrypted) — no plugin database table, no per-asset data, and no file written at runtime.
- The configuration form posts through GLPI 11's CSRF-protected front controller.

## Security

**Trust boundary.** A Matomo Tag Manager container is third-party code that its publishers can change at any time; it runs with the privileges of the GLPI origin. Whoever can publish in your MTM container — or compromises your Matomo host — can act as any user whose page loads it. The plugin therefore:

- never loads the container for a session holding `config`, `profile` or `user` **UPDATE** in any of its profiles (administrators, even when browsing under another profile), nor on administration pages (setup, profiles, users, preferences with API tokens, authentication, plugins, rules, notifications…); a right check that fails counts as privileged;
- never loads it on a URL carrying a secret (`password_forget_token`, any `*token*`, `*passw*`, `*secret*`, `code`, `state` parameter), nor on a page whose referrer carries one, so a password-reset link never reaches Matomo's visit log;
- re-checks both conditions in the loader, as a second layer.

What remains is yours to decide: restrict *Publish* rights on the MTM container to the owner, and set a Content Security Policy on the GLPI virtual host, for example `Content-Security-Policy: script-src 'self' https://stats.example.com; object-src 'none'; base-uri 'self'`. A static `matomo.js` tracker pinned with an integrity hash (SRI) would remove the risk altogether, at the cost of Tag Manager's flexibility.

- The container URL must have the shape of an MTM container (`https://host/…/container_<id>.js`, no credentials, query or fragment) before being saved, and again before being emitted.
- Settings reach the page as data only: escaped `<meta>` attributes, never JavaScript; the loader refuses a container of any other shape.
- The identity mode is whitelisted; an unknown stored value falls back to *Do not send*.
- The configuration page is gated by `config: UPDATE` and protected by GLPI 11's CSRF listener.
- The plugin reads/writes only GLPI's own configuration store — it touches no core or third-party tables.

## Screenshots

Generated by the browser bench, see [docs/captures/](docs/captures/README.md).

## Tests

```bash
php tests/run.php            # or: phpunit --no-configuration tests/  (44 tests)
node --test tests/*.test.js  # the real loader, in a vm sandbox      (9 tests)
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Support

Report bugs and request features on the [issue tracker](https://github.com/FathiBenNasr/glpi-matomo/issues).

## License

Distributed under the [GNU GPL v2.0-or-later](LICENSE) license.

---

<div align="center">

## Developed by

[![Convergent Cloud Computing](https://www.convergent.tn/assets/images/convergent-logo.png)](https://www.convergent.tn)

**[Convergent Cloud Computing](https://www.convergent.tn)**  
Cloud infrastructure, open-source integration, and cybersecurity solutions for Tunisian and international businesses.

📧 contact@convergent.tn | 🌐 [www.convergent.tn](https://www.convergent.tn)

</div>
