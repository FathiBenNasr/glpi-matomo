<?php

namespace GlpiPlugin\Matomo;

use CommonGLPI;
use Config as GlpiConfig;
use Html;
use Plugin;
use Session;

class Config extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return 'Matomo Tag Manager';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return self::getTypeName();
    }

    public static function showConfigForm(): bool
    {
        $config = GlpiConfig::getConfigurationValues('plugin:matomo', ['container_url']);
        $url    = $config['container_url'] ?? '';

        echo '<form method="post" action="' . Plugin::getWebDir('matomo') . '/front/config.php">';
        echo '<table class="tab_cadre_fixe">';
        echo '<tr class="headerRow"><th colspan="2">' . __('Matomo Tag Manager Settings') . '</th></tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Container URL') . '</td>';
        echo '<td>';
        echo '<input type="text" name="container_url" value="' . htmlspecialchars($url, ENT_QUOTES) . '" size="80" placeholder="https://stats.example.com/js/container_XXXXXXXX.js">';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_2">';
        echo '<td colspan="2" class="center">';
        echo Html::submit(__('Save'), ['name' => 'update']);
        echo '</td>';
        echo '</tr>';

        echo '</table>';
        Html::closeForm();

        return true;
    }

    public static function saveConfig(array $post): void
    {
        Session::checkRight('config', UPDATE);

        $url = trim($post['container_url'] ?? '');
        if ($url !== '' && !str_starts_with($url, 'https://')) {
            Session::addMessageAfterRedirect(__('Container URL must start with https://'), false, ERROR);
            return;
        }

        GlpiConfig::setConfigurationValues('plugin:matomo', ['container_url' => $url]);
        self::writeConfigJs($url);
        Session::addMessageAfterRedirect(__('Configuration saved'), false, INFO);
    }

    public static function getContainerUrl(): string
    {
        $config = GlpiConfig::getConfigurationValues('plugin:matomo', ['container_url']);
        return $config['container_url'] ?? '';
    }

    public static function writeConfigJs(string $url): void
    {
        $file = Plugin::getPhpDir('matomo') . '/public/js/mtm-config.js';
        file_put_contents(
            $file,
            'window.MATOMO_CONTAINER_URL=' . json_encode($url) . ";\n"
        );
    }
}
