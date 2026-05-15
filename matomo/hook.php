<?php

function plugin_matomo_install(): bool
{
    // No database tables needed — config stored in GLPI config table
    return true;
}

function plugin_matomo_uninstall(): bool
{
    // Remove stored config
    $config = new Config();
    $config->deleteConfigurationValues('plugin:matomo', ['container_url']);
    return true;
}
