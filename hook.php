<?php

declare(strict_types=1);

function plugin_matomo_install(): bool
{
    // No database tables needed — config stored in GLPI config table.
    // WHY (L-41): the pseudonym is keyed with the plugin's own salt, sealed by GLPIKey.
    \GlpiPlugin\Matomo\Config::ensurePseudonymSalt();
    return true;
}

function plugin_matomo_uninstall(): bool
{
    // Remove stored config
    $config = new Config();
    $config->deleteConfigurationValues('plugin:matomo', ['container_url', 'track_anonymous', 'user_id_mode', \GlpiPlugin\Matomo\Config::SALT_KEY]);
    return true;
}
