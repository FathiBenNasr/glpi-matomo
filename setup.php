<?php

declare(strict_types=1);

/**
 * Matomo Tag Manager plugin for GLPI
 * Injects a Matomo Tag Manager container into GLPI pages (logged-in pages, and
 * optionally the login screen), optionally with the identity of the user.
 */

define('PLUGIN_MATOMO_VERSION', '1.1.3');
define('PLUGIN_MATOMO_MIN_GLPI', '12.0.0');
define('PLUGIN_MATOMO_MAX_GLPI', '12.99.99');

function plugin_version_matomo(): array
{
    return [
        'name'         => 'Matomo Tag Manager',
        'version'      => PLUGIN_MATOMO_VERSION,
        'author'       => 'Convergent Cloud Computing',
        'license'      => 'GPL v2+',
        'homepage'     => 'https://www.convergent.tn',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_MATOMO_MIN_GLPI,
                'max' => PLUGIN_MATOMO_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_matomo_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_MATOMO_MIN_GLPI, 'lt') ||
        version_compare(GLPI_VERSION, PLUGIN_MATOMO_MAX_GLPI, 'gt')) {
        echo 'This plugin requires GLPI >= ' . PLUGIN_MATOMO_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_matomo_check_config(): bool
{
    return true;
}

function plugin_init_matomo(): void
{
    global $PLUGIN_HOOKS;

    $plugin = new Plugin();
    if (!$plugin->isActivated('matomo')) {
        return;
    }

    // Config page link in plugin list
    $PLUGIN_HOOKS['config_page']['matomo'] = 'front/config.php';

    // Only inject on real HTTP requests
    if (!isset($_SERVER['HTTP_HOST'])) {
        return;
    }

    $settings = \GlpiPlugin\Matomo\Config::getSettings();
    if ($settings['container_url'] === '') {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    // WHY (M-13): the tracker records the full page URL; a URL carrying a secret
    // (password-reset token, API or CSRF token, SSO code) never reaches Matomo.
    // The referrer too: the page reached from a reset link would report it as urlref.
    if (\GlpiPlugin\Matomo\Config::urlCarriesSecret($uri)
        || \GlpiPlugin\Matomo\Config::urlCarriesSecret((string) ($_SERVER['HTTP_REFERER'] ?? ''))) {
        return;
    }

    // Logged-in pages: the session is already started when plugins initialise.
    $users_id = (int) (\Session::getLoginUserID() ?: 0);
    // WHY (M-12): the container is mutable third-party code running in the GLPI
    // origin; it never runs in administration pages nor for a session that can
    // change configuration, rights or accounts.
    $privileged = \GlpiPlugin\Matomo\Config::isPrivilegedContext(
        $uri,
        static fn (string $module, int $right): bool => \GlpiPlugin\Matomo\Config::sessionCanReachRight($module, $right)
    );
    if ($users_id > 0 && !$privileged) {
        $user_id = '';
        if ($settings['user_id_mode'] !== \GlpiPlugin\Matomo\Config::USER_ID_NONE) {
            $user_id = \GlpiPlugin\Matomo\Config::userIdFor(
                $settings['user_id_mode'],
                $users_id,
                (string) ($_SESSION['glpiname'] ?? ''),
                // WHY (L-41): the plugin's own salt, never GLPI's master key.
                $settings['user_id_mode'] === \GlpiPlugin\Matomo\Config::USER_ID_PSEUDONYM
                    ? \GlpiPlugin\Matomo\Config::pseudonymSecret()
                    : ''
            );
        }
        $PLUGIN_HOOKS['add_header_tag']['matomo'] = \GlpiPlugin\Matomo\Config::headerTags($settings, false, $user_id);
        $PLUGIN_HOOKS['add_javascript']['matomo'] = ['js/mtm-loader.js'];
    }

    // Anonymous pages (login screen, password reset…) use their own hooks; opt-in only.
    if ($settings['track_anonymous'] && !\GlpiPlugin\Matomo\Config::isPrivilegedPath($uri)) {
        $PLUGIN_HOOKS['add_header_tag_anonymous_page']['matomo'] = \GlpiPlugin\Matomo\Config::headerTags($settings, true, '');
        $PLUGIN_HOOKS['add_javascript_anonymous_page']['matomo'] = ['js/mtm-loader.js'];
    }
}
