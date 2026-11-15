# Matomo Tag Manager — user manual

Deployed version: **1.0.6**

## What the plugin is for

Matomo Tag Manager connects GLPI to a Matomo audience measurement service. When it is configured, users' browsers load, on every page, a small script (the "container") provided by the organization, which records statistics on GLPI usage. The plugin adds no screen or menu for technicians and managers: this page explains what it measures and what that means for privacy.

> **Good to know:** When the plugin is configured, every GLPI page loads a script from the Matomo service: read the privacy section.

## Where to find it

The plugin has **no** menu and adds **no tab** to GLPI items. Its settings page is restricted to people who can change GLPI's configuration; it opens from the plugin list (Setup → Plugins), using the plugin's configuration icon. The labels on that page are in English.

## Required rights

No right specific to the plugin. Changing the plugin's settings requires the right to **change the configuration** of GLPI ("Setup" right, update level). Other users have nothing to do: the plugin acts automatically in their browser.

## What the plugin measures

- **Nothing until a container address is entered.** Without it, no measurement takes place.
- **Pages concerned**: the pages viewed by logged-in users (standard interface and simplified interface). The login page and other public pages are only measured if the option **Also track the login screen and other anonymous pages** is checked. It is unchecked by default.
- **User identity**: the **Identity of the logged-in user** setting has three values.

| Value | Effect |
|---|---|
| **Do not send** (default) | No identity is sent: visits are not linked to a person |
| **Pseudonym (stable, not reversible)** | A stable, non-reversible code is sent instead of the name: it makes it possible to follow the visits of the same person without Matomo knowing their login |
| **GLPI login (personal data)** | The GLPI login name is sent in clear text |

No identity is ever sent from public pages.

## Privacy: what this means

- Audience measurement is performed by a script loaded in your browser, provided by the organization's Matomo service. Matomo receives the addresses of the pages viewed.
- With the pseudonym or the login name, visits are linked to a person. The pseudonym remains personal data; the login name even more so.
- The plugin limits collection itself. The script **does not load**:
  - on administration pages (setup, users, profiles, entities, authentication, notifications, rules, plugins, etc.);
  - for a person who has a profile that allows changing the configuration, the profiles or the users;
  - on a page whose address contains a secret (token, password, authentication code), nor on a page reached from such a link (for example a password reset link).
- The users concerned must be informed of this measurement (data protection notice). The identity choices are up to the administrator and management: to find out which one is active, contact support.

## Tasks, step by step

### Find out whether your browsing is measured

1. You are logged in with a profile that has no administration rights.
2. Ask support whether a container address is configured and which identity setting is chosen.

Result: you know whether your GLPI pages are measured and whether Matomo can identify you. There is no individual setting in GLPI to object to it; to exercise your rights, contact support.

### Change the settings (configuration administrators)

1. Open **Setup → Plugins** and click the configuration icon of "Matomo Tag Manager".
2. In **Container URL**, enter the https address of the container, in the form `https://…/container_XXXXXXXX.js`. An address that does not have this form is rejected. Empty the field to stop all measurement.
3. Check or uncheck **Also track the login screen and other anonymous pages**.
4. Choose the **Identity of the logged-in user**.
5. Click **Save**.

Result: "Configuration saved" is displayed; the change applies the next time pages are loaded.

## What users see or receive

Nothing visible: no email, notification or ticket. Measurement happens in the background in the browser.

## Automatic tasks

None.

## Frequently asked questions and common problems

| Symptom | What to do |
|---|---|
| I cannot find any Matomo screen in GLPI | This is normal: only the plugin settings offer one, to people who manage the configuration. |
| The message "Container URL must be an https:// Matomo Tag Manager container" appears | The address does not have the required form (https, ending in `/container_….js`, with no credentials, parameter or anchor). Correct it or contact support. |
| Statistics do not appear in Matomo | Contact support: the container configuration on the Matomo side is the administrator's responsibility. |
| I am an administrator and nothing is measured for me | This is intended: the script does not load for administration profiles or on administration pages. |

## See also

- Data Consent — user manual

## Support

contact@convergent.tn
