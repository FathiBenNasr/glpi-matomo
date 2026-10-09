<?php

declare(strict_types=1);

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

    /** Config key of the plugin's own pseudonym salt, stored encrypted with GLPIKey. */
    public const SALT_KEY = 'pseudonym_salt';

    /**
     * Query parameter names that carry a secret (password reset, API/CSRF tokens,
     * SSO callbacks). Same pattern, verbatim, in public/js/mtm-loader.js.
     */
    public const SECRET_PARAM_PATTERN = '^([^=]*(token|passw|secret)[^=]*|code|state)$';

    /**
     * Pages from which a session can change configuration, rights, accounts,
     * authentication or API access. Same pattern, verbatim, in mtm-loader.js.
     */
    public const PRIVILEGED_PATH_PATTERN = '/(front|ajax)/(config|setup|profile|user|preference|group|entity|auth|apiclient|oauthclient|plugin|marketplace|crontask|mailcollector|notification|rule|webhook)[A-Za-z0-9_.-]*[.]php|/plugins/[^/]+/front/config';

    /** Rights whose holder can escalate anyone: no third-party script runs in their pages. */
    public const PRIVILEGED_RIGHTS = [['config', UPDATE], ['profile', UPDATE], ['user', UPDATE]];

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
            // WHY (M-13): privacy by default — an upgrade must not widen what leaves
            // the browser, so anonymous pages are tracked only after an explicit opt-in.
            'track_anonymous' => ($raw['track_anonymous'] ?? '0') === '1',
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
        echo '<tr class="headerRow"><th colspan="2">' . __('Matomo Tag Manager Settings', 'matomo') . '</th></tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Container URL', 'matomo') . '</td>';
        echo '<td>';
        echo '<input type="text" name="container_url" value="' . htmlspecialchars($s['container_url'], ENT_QUOTES) . '" size="80" placeholder="https://stats.example.com/js/container_XXXXXXXX.js">';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Also track the login screen and other anonymous pages', 'matomo') . '</td>';
        echo '<td><input type="hidden" name="track_anonymous" value="0">';
        echo '<input type="checkbox" name="track_anonymous" value="1"' . ($s['track_anonymous'] ? ' checked' : '') . '></td>';
        echo '</tr>';

        $labels = [
            self::USER_ID_NONE      => __('Do not send', 'matomo'),
            self::USER_ID_PSEUDONYM => __('Pseudonym (stable, not reversible)', 'matomo'),
            self::USER_ID_LOGIN     => __('GLPI login (personal data)', 'matomo'),
        ];
        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Identity of the logged-in user', 'matomo') . '</td>';
        echo '<td><select name="user_id_mode">';
        foreach ($labels as $value => $label) {
            echo '<option value="' . $value . '"' . ($s['user_id_mode'] === $value ? ' selected' : '') . '>'
                . htmlspecialchars($label) . '</option>';
        }
        echo '</select><br><small>'
            . htmlspecialchars(__('Pushed to the Matomo Tag Manager data layer as "glpiUserId"; map it to the User ID field of your Matomo configuration tag. The login is personal data: inform your users and check your legal basis before enabling it.', 'matomo'))
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

        $url = trim((string) ($post['container_url'] ?? ''));
        // WHY (M-12): the container runs with the privileges of the GLPI origin; only
        // accept the exact shape of an MTM container served over HTTPS (no credentials,
        // query, fragment or arbitrary script path).
        if ($url !== '' && !self::isValidContainerUrl($url)) {
            Session::addMessageAfterRedirect(
                __('Container URL must be an https:// Matomo Tag Manager container (…/container_XXXXXXXX.js)', 'matomo'),
                false,
                ERROR
            );
            return;
        }
        $mode = (string) ($post['user_id_mode'] ?? self::USER_ID_NONE);
        if (!in_array($mode, self::userIdModes(), true)) {
            Session::addMessageAfterRedirect(__('Unknown user identity mode', 'matomo'), false, ERROR);
            return;
        }

        GlpiConfig::setConfigurationValues('plugin:matomo', [
            'container_url'   => $url,
            'track_anonymous' => ($post['track_anonymous'] ?? '0') === '1' ? '1' : '0',
            'user_id_mode'    => $mode,
        ]);
        if ($mode === self::USER_ID_PSEUDONYM) {
            self::ensurePseudonymSalt();
        }
        Session::addMessageAfterRedirect(__('Configuration saved', 'matomo'), false, INFO);
    }

    public static function getContainerUrl(): string
    {
        return self::getSettings()['container_url'];
    }

    /**
     * An https:// URL of an MTM container script: a host, no userinfo, no query or
     * fragment, and a path ending in /container_<id>.js.
     */
    public static function isValidContainerUrl(string $url): bool
    {
        if (!str_starts_with($url, 'https://') || preg_match('/[\s"\'<>\\\\]/', $url) === 1) {
            return false;
        }
        $p = parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') === ''
            || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            return false;
        }
        $path = (string) ($p['path'] ?? '');
        return !str_contains($path, '/.')
            && preg_match('#^(/[A-Za-z0-9._~-]+)*/container_[A-Za-z0-9_]+[.]js$#', $path) === 1;
    }

    /**
     * True when the URL carries a secret in its query string (or fragment).
     *
     * WHY (M-13): a tracker records the full page URL; a password-reset token sent
     * to Matomo lets any Matomo reader take over the GLPI account.
     */
    public static function urlCarriesSecret(string $uri): bool
    {
        $parts = [];
        $q = strpos($uri, '?');
        if ($q !== false) {
            $parts[] = substr($uri, $q + 1);
        }
        $h = strpos($uri, '#');
        if ($h !== false) {
            $parts[] = substr($uri, $h + 1);
        }
        foreach ($parts as $part) {
            $part = explode('#', $part, 2)[0];
            foreach (preg_split('/[&;]/', $part) ?: [] as $pair) {
                if (!str_contains($pair, '=')) {
                    continue;
                }
                $name = rawurldecode(str_replace('+', ' ', explode('=', $pair, 2)[0]));
                if (preg_match('#' . self::SECRET_PARAM_PATTERN . '#i', $name) === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /** True when the path is an administration page (see PRIVILEGED_PATH_PATTERN). */
    public static function isPrivilegedPath(string $uri): bool
    {
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
        // WHY (M-12): the web server decodes %XX and merges "//" and "/./" before
        // picking the script; compare the same path it serves, not the raw spelling.
        $path = rawurldecode($path);
        do {
            $before = $path;
            $path   = (string) preg_replace(['#/{2,}#', '#/\./#'], '/', $path);
        } while ($path !== $before);
        return preg_match('#' . self::PRIVILEGED_PATH_PATTERN . '#i', $path) === 1;
    }

    /**
     * True when the user holds $right on $module in the active profile or in any
     * other profile of the session.
     *
     * WHY (M-12): a super-administrator browsing under a self-service profile can
     * switch back to the admin profile from the same page; the container would
     * then act as that administrator. Any reachable profile counts. Exceptions
     * propagate, so isPrivilegedContext() fails closed.
     */
    public static function sessionCanReachRight(string $module, int $right): bool
    {
        if (Session::haveRight($module, $right)) {
            return true;
        }
        foreach (array_keys((array) ($_SESSION['glpiprofiles'] ?? [])) as $profiles_id) {
            $rights = \ProfileRight::getProfileRights((int) $profiles_id, [$module]);
            if ((((int) ($rights[$module] ?? 0)) & $right) === $right) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when the third-party container must not run: an administration page, or
     * a session holding a right that can escalate anyone.
     *
     * WHY (M-12): whoever can publish in the MTM container (or compromises the
     * Matomo host) runs code in the GLPI origin; it must never get the session of
     * an administrator nor the pages that change configuration, rights or tokens.
     * Fails closed: a right check that throws counts as privileged.
     *
     * @param callable(string, int): bool $hasRight
     */
    public static function isPrivilegedContext(string $uri, callable $hasRight): bool
    {
        if (self::isPrivilegedPath($uri)) {
            return true;
        }
        try {
            foreach (self::PRIVILEGED_RIGHTS as [$module, $right]) {
                if ($hasRight($module, $right)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return true;
        }
        return false;
    }

    /**
     * Creates the plugin's own pseudonym salt once, stored encrypted with GLPIKey.
     *
     * WHY (L-41): the pseudonym must not be keyed with GLPI's master key — that key
     * protects every stored credential and must not be reused for another purpose.
     */
    public static function ensurePseudonymSalt(): void
    {
        $raw = GlpiConfig::getConfigurationValues('plugin:matomo', [self::SALT_KEY]);
        if ((string) ($raw[self::SALT_KEY] ?? '') !== '') {
            return;
        }
        $salt = bin2hex(random_bytes(32));
        GlpiConfig::setConfigurationValues('plugin:matomo', [
            self::SALT_KEY => (string) (new \GLPIKey())->encrypt($salt),
        ]);
    }

    /** The decrypted pseudonym salt, or '' (no pseudonym) when absent or unreadable. */
    public static function pseudonymSecret(): string
    {
        $raw    = GlpiConfig::getConfigurationValues('plugin:matomo', [self::SALT_KEY]);
        $sealed = (string) ($raw[self::SALT_KEY] ?? '');
        if ($sealed === '') {
            return '';
        }
        try {
            return (string) ((new \GLPIKey())->decrypt($sealed) ?? '');
        } catch (\Throwable $e) {
            return '';   // fail closed
        }
    }

    /**
     * Value sent as the Matomo user id, or '' when none must be sent.
     *
     * The pseudonym is an HMAC of the numeric user id keyed with the plugin's own
     * salt, which never leaves the server: stable across sessions, so Matomo can
     * follow a user, but not reversible to the account.
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
        if ($url === '' || !self::isValidContainerUrl($url)) {
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
