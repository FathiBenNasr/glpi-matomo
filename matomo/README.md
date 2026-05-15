# GLPI — Matomo Tag Manager Plugin

Inject a [Matomo Tag Manager](https://matomo.org/guide/tag-manager/) container into every GLPI page with zero code changes — just paste your container URL in the plugin settings.

---

## Features

- Loads your Matomo Tag Manager container on every GLPI page (authenticated and login screens)
- Container URL configured from the GLPI admin panel — no file editing required
- Lightweight: one `<script>` tag injected via `ADD_HEADER_TAG` + one static JS file
- Compatible with GLPI 10.x and 11.x

## Requirements

| Requirement | Version |
|-------------|---------|
| GLPI        | ≥ 10.0.0 |
| PHP         | ≥ 8.1   |

## Installation

1. Download the latest release archive from the [Releases](https://github.com/FathiBenNasr/glpi-matomo/releases) page
2. Extract into your GLPI `plugins/` directory so the path is `plugins/matomo/`
3. In GLPI: **Setup → Plugins → Matomo Tag Manager → Install → Enable**
4. Click **Configuration** and paste your Matomo Tag Manager container URL
5. Save — tracking starts immediately on the next page load

## Configuration

| Setting | Description |
|---------|-------------|
| Container URL | Full URL to your MTM container JS (e.g. `https://stats.example.com/js/container_XXXXXXXX.js`) |

## License

This plugin is distributed under the [GNU GPL v2+](LICENSE) license.

---

<div align="center">

### Developed by

[![Convergent Cloud Computing](https://www.convergent.tn/assets/images/convergent-logo.png)](https://www.convergent.tn)

**[Convergent Cloud Computing](https://www.convergent.tn)**  
Cloud infrastructure, open-source integration, and cybersecurity solutions for Tunisian and international businesses.

📧 contact@convergent.tn | 🌐 [www.convergent.tn](https://www.convergent.tn)

</div>
