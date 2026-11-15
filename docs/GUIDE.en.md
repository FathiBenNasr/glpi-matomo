# User guide — Matomo Tag Manager

[Français](GUIDE.md) · **English**

This guide explains how to carry out the usual tasks. The manual for GLPI users (what the plugin
measures, privacy) is in [MANUEL.en.md](MANUEL.en.md); installation, options and troubleshooting are
in [ADMINISTRATION.en.md](ADMINISTRATION.en.md). The plugin adds no menu or tab: its settings page
opens from **Setup → Plugins** (the configuration icon of **Matomo Tag Manager**) and requires the
right to change GLPI's configuration. The plugin's own labels are in English whatever the interface language; the
save button comes from GLPI core and is translated.

## Entering the container URL

Connect GLPI to your Matomo Tag Manager container. While the field is empty, **nothing is measured**.

1. In Matomo Tag Manager, publish your container and copy the address of its script
   (`https://stats.example.com/js/container_AbCd1234.js`).
2. In GLPI, open **Setup → Plugins → Matomo Tag Manager** (configuration icon).
3. Paste the address into **Container URL**.
4. Click **Save**.

Expected result: the "Configuration saved" message appears and the container loads on the next page
of logged-in users without administration rights. An address that is not HTTPS, carries credentials,
a query or a fragment, or whose path does not end in `/container_<id>.js`, is refused.

![The settings page with a fictitious container URL, login-screen tracking unticked and identity "Do not send"](captures/02-reglages-defaut.png)

## Also measuring the login screen

Count visits to the login screen and other anonymous pages (password reset, for instance). **Off by
default.**

1. Open the settings page.
2. Tick **Also track the login screen and other anonymous pages**.
3. Click **Save**.

Expected result: the container loads on those pages. No identity is sent there, and a page whose
address (or referrer) carries a token is never measured.

## Enabling the pseudonymised identity

Know that the same person comes back, without Matomo learning their GLPI login. The pseudonym **is
still personal data**: before enabling it, check your legal basis and inform users (see
[SECURITE.md](SECURITE.md#données-personnelles), in French).

1. Open the settings page.
2. In **Identity of the logged-in user**, choose **Pseudonym (stable, not reversible)**.
3. Click **Save**.

![The settings page in pseudonym mode, login-screen tracking ticked](captures/01-settings.png)

Expected result: every page of a logged-in user pushes `glpiUserId` to the MTM data layer before the
container starts. The **GLPI login (personal data)** choice sends the login in clear: avoid it
unless a need is established.

## Using the identity in Matomo

Link `glpiUserId` to Matomo's **User ID** field.

1. In Matomo Tag Manager, create a *Data Layer* variable named `glpiUserId`.
2. Open your Matomo configuration variable and set that variable as its **User ID**.
3. Publish a new version of the container.

Expected result: a person's visits are grouped under one User ID. On the Matomo side, enable IP
anonymisation, honour *Do Not Track* or offer an opt-out, and exclude the `password_forget_token`,
`token` and `_glpi_csrf_token` parameters in the site settings.

## Switching measurement off

Stop all measurement at once, without uninstalling the plugin.

1. Open the settings page.
2. Clear **Container URL**.
3. Click **Save**.

Expected result: no script is loaded, whatever the other settings. To remove the stored settings
too, uninstall the plugin (see
[ADMINISTRATION.en.md](ADMINISTRATION.en.md#upgrade-and-uninstall)).

## Frequently asked questions

**Why is nothing measured when I test with my administrator account?** The container never loads for
a session that can change configuration, profiles or accounts, nor on administration pages: this is
intended. Test with an account without administration rights.

**Can technicians be measured?** Yes, as long as their profile (and none of their other profiles)
grants one of the administration rights listed in
[SECURITE.md](SECURITE.md#menaces-et-mesures) (French).

**Does the pseudonym change?** It is stable as long as the plugin's salt does not change; it changes
if the plugin is uninstalled and reinstalled.
