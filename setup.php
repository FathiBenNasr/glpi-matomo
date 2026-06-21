<?php

/**
 * Matomo Tag Manager plugin for GLPI
 * Injects a Matomo Tag Manager container on every GLPI page.
 */

define('PLUGIN_MATOMO_VERSION', '1.0.0');
define('PLUGIN_MATOMO_MIN_GLPI', '10.0.0');
define('PLUGIN_MATOMO_MAX_GLPI', '12.0.0');

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

    $PLUGIN_HOOKS['csrf_compliant']['matomo'] = true;

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

    // Load config JS (sets window.MATOMO_CONTAINER_URL) then the loader
    $config_js = \Plugin::getPhpDir('matomo') . '/public/js/mtm-config.js';
    if (!is_file($config_js)) {
        return;
    }

    $PLUGIN_HOOKS['add_javascript']['matomo'] = ['js/mtm-config.js', 'js/mtm-loader.js'];
}
