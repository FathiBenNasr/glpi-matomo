<?php

namespace GlpiPlugin\Matomo;

use CommonGLPI;
use Config as GlpiConfig;
use Html;
use Session;

class Config extends CommonGLPI
{
    /** No identity is sent to Matomo (default). */
    public const USER_ID_NONE      = 'none';
    /** A stable, non-reversible pseudonym of the GLPI user id. */
    public const USER_ID_PSEUDONYM = 'pseudonym';
    /** The GLPI login name, in clear: personal data, opt-in only. */
    public const USER_ID_LOGIN     = 'login';

    /** <meta> names read by public/js/mtm-loader.js. */
    public const META_CONTAINER = 'glpi-matomo-container';
    public const META_USER_ID   = 'glpi-matomo-uid';

    private const KEYS = ['container_url', 'track_anonymous', 'user_id_mode'];

    public static function getTypeName($nb = 0): string
    {
        return 'Matomo Tag Manager';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return self::getTypeName();
    }

    /** @return array{container_url: string, track_anonymous: bool, user_id_mode: string} */
    public static function getSettings(): array
    {
        $raw  = GlpiConfig::getConfigurationValues('plugin:matomo', self::KEYS);
        $mode = (string) ($raw['user_id_mode'] ?? self::USER_ID_NONE);

        return [
            'container_url'   => (string) ($raw['container_url'] ?? ''),
            // Default on: the login screen was always meant to be tracked.
            'track_anonymous' => ($raw['track_anonymous'] ?? '1') === '1',
            'user_id_mode'    => in_array($mode, self::userIdModes(), true) ? $mode : self::USER_ID_NONE,
        ];
    }

    /** @return list<string> */
    public static function userIdModes(): array
    {
        return [self::USER_ID_NONE, self::USER_ID_PSEUDONYM, self::USER_ID_LOGIN];
    }

    public static function showConfigForm(): bool
    {
        $s = self::getSettings();

        // GLPI serves every plugin under /plugins/ (Plugin::getWebDir() is gone in GLPI 12).
        $webdir = ($GLOBALS['CFG_GLPI']['root_doc'] ?? '') . '/plugins/matomo';
        echo '<form method="post" action="' . $webdir . '/front/config.php">';
        echo '<table class="tab_cadre_fixe">';
        echo '<tr class="headerRow"><th colspan="2">' . __('Matomo Tag Manager Settings') . '</th></tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Container URL') . '</td>';
        echo '<td>';
        echo '<input type="text" name="container_url" value="' . htmlspecialchars($s['container_url'], ENT_QUOTES) . '" size="80" placeholder="https://stats.example.com/js/container_XXXXXXXX.js">';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Also track the login screen and other anonymous pages') . '</td>';
        echo '<td><input type="hidden" name="track_anonymous" value="0">';
        echo '<input type="checkbox" name="track_anonymous" value="1"' . ($s['track_anonymous'] ? ' checked' : '') . '></td>';
        echo '</tr>';

        $labels = [
            self::USER_ID_NONE      => __('Do not send'),
            self::USER_ID_PSEUDONYM => __('Pseudonym (stable, not reversible)'),
            self::USER_ID_LOGIN     => __('GLPI login (personal data)'),
        ];
        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Identity of the logged-in user') . '</td>';
        echo '<td><select name="user_id_mode">';
        foreach ($labels as $value => $label) {
            echo '<option value="' . $value . '"' . ($s['user_id_mode'] === $value ? ' selected' : '') . '>'
                . htmlspecialchars($label) . '</option>';
        }
        echo '</select><br><small>'
            . htmlspecialchars(__('Pushed to the Matomo Tag Manager data layer as "glpiUserId"; map it to the User ID field of your Matomo configuration tag. The login is personal data: inform your users and check your legal basis before enabling it.'))
            . '</small></td>';
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
        $mode = (string) ($post['user_id_mode'] ?? self::USER_ID_NONE);
        if (!in_array($mode, self::userIdModes(), true)) {
            Session::addMessageAfterRedirect(__('Unknown user identity mode'), false, ERROR);
            return;
        }

        GlpiConfig::setConfigurationValues('plugin:matomo', [
            'container_url'   => $url,
            'track_anonymous' => ($post['track_anonymous'] ?? '0') === '1' ? '1' : '0',
            'user_id_mode'    => $mode,
        ]);
        Session::addMessageAfterRedirect(__('Configuration saved'), false, INFO);
    }

    public static function getContainerUrl(): string
    {
        return self::getSettings()['container_url'];
    }

    /**
     * Value sent as the Matomo user id, or '' when none must be sent.
     *
     * The pseudonym is an HMAC of the numeric user id keyed with a secret that
     * never leaves the server (GLPI's own encryption key): stable across
     * sessions, so Matomo can follow a user, but not reversible to the account.
     */
    public static function userIdFor(string $mode, int $users_id, string $login, string $secret): string
    {
        if ($users_id <= 0) {
            return '';
        }
        return match ($mode) {
            self::USER_ID_PSEUDONYM => $secret === ''
                ? ''   // fail closed: no key, no pseudonym (and never fall back to the login)
                : substr(hash_hmac('sha256', 'glpi-matomo-uid:' . $users_id, $secret), 0, 32),
            self::USER_ID_LOGIN     => $login,
            default                 => '',
        };
    }

    /**
     * Header tags for GLPI's add_header_tag[_anonymous_page] hooks. Values are
     * data only: GLPI's head template escapes them, nothing is executed.
     *
     * @param array{container_url: string, track_anonymous: bool, user_id_mode: string} $settings
     * @return list<array{tag: string, properties: array<string, string>}>
     */
    public static function headerTags(array $settings, bool $anonymous, string $user_id): array
    {
        $url = $settings['container_url'];
        if ($url === '' || !str_starts_with($url, 'https://')) {
            return [];
        }
        if ($anonymous && !$settings['track_anonymous']) {
            return [];
        }
        $tags = [['tag' => 'meta', 'properties' => ['name' => self::META_CONTAINER, 'content' => $url]]];
        if (!$anonymous && $user_id !== '') {
            $tags[] = ['tag' => 'meta', 'properties' => ['name' => self::META_USER_ID, 'content' => $user_id]];
        }
        return $tags;
    }
}
