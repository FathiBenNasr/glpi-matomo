<?php
/**
 * Matomo Tag Manager — plugin_init_matomo(), the real setup.php.
 *
 * What reaches the browser is decided here: which pages get the third-party
 * container, for whom, and with which identity. GLPI core is stubbed
 * (tests/stubs.php); setup.php itself is the shipped file.
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../setup.php';

use GlpiPlugin\Matomo\Config as MatomoConfig;

final class SetupTest extends TestCase
{
    private const URL = 'https://stats.example.com/js/container_AbC12345.js';

    /**
     * Runs plugin_init_matomo() for one request and returns the hooks it set.
     *
     * @param array<string, string> $stored  plugin:matomo configuration values
     * @param list<string>          $rights  "module:right" granted to the session
     */
    private function init(string $uri, int $users_id = 0, array $stored = [], array $rights = []): array
    {
        global $PLUGIN_HOOKS;
        $PLUGIN_HOOKS = [];

        Config::$store            = ['plugin:matomo' => $stored + ['container_url' => self::URL]];
        Session::$loginUserId     = $users_id;
        Session::$rights          = array_fill_keys($rights, true);
        Session::$haveRightThrows = false;
        Plugin::$active           = true;
        GLPIKey::$getCalls        = 0;

        $saved = $_SERVER;
        $savedSession = $_SESSION ?? [];
        $_SERVER['HTTP_HOST']   = 'glpi.example';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SESSION['glpiname']   = 'jdupont';
        try {
            plugin_init_matomo();
        } finally {
            $_SERVER  = $saved;
            $_SESSION = $savedSession;
        }
        return $PLUGIN_HOOKS;
    }

    private static function loads(array $hooks, string $hook): bool
    {
        return ($hooks[$hook]['matomo'] ?? null) === ['js/mtm-loader.js'];
    }

    public function testOrdinaryUserPageGetsTheContainer(): void
    {
        $h = $this->init('/front/central.php', 42);
        self::assertTrue(self::loads($h, 'add_javascript'));
        self::assertSame('front/config.php', $h['config_page']['matomo'] ?? null);
    }

    public function testInactivePluginInjectsNothing(): void
    {
        global $PLUGIN_HOOKS;
        $this->init('/front/central.php', 42);
        Plugin::$active = false;
        $PLUGIN_HOOKS = [];
        $_SERVER['HTTP_HOST'] = 'glpi.example';
        plugin_init_matomo();
        unset($_SERVER['HTTP_HOST']);
        self::assertSame([], $PLUGIN_HOOKS);
        Plugin::$active = true;
    }

    /** Dead on GLPI 11+: the plugin no longer declares csrf_compliant. */
    public function testNoObsoleteCsrfCompliantHook(): void
    {
        self::assertFalse(isset($this->init('/front/central.php', 42)['csrf_compliant']));
    }

    // ------------------------------------------------------------ M-13 --

    /** Upgrade without the key: the login screen is not tracked. */
    public function testAnonymousPagesAreNotTrackedByDefault(): void
    {
        $h = $this->init('/', 0);
        self::assertFalse(self::loads($h, 'add_javascript_anonymous_page'));
        self::assertFalse(isset($h['add_header_tag_anonymous_page']));
    }

    public function testAnonymousPagesTrackedOnlyWhenOptedIn(): void
    {
        $h = $this->init('/', 0, ['track_anonymous' => '1']);
        self::assertTrue(self::loads($h, 'add_javascript_anonymous_page'));
    }

    /**
     * The password-reset link carries the token in the URL: even when anonymous
     * tracking is on, nothing is injected, so the token never reaches Matomo.
     */
    public function testNoTrackingOnPasswordResetUrl(): void
    {
        foreach ([
            '/front/lostpassword.php?password_forget_token=0123456789abcdef',
            '/glpi/front/lostpassword.php?password_forget_token=0123456789abcdef&x=1',
        ] as $uri) {
            $h = $this->init($uri, 0, ['track_anonymous' => '1']);
            self::assertFalse(isset($h['add_javascript_anonymous_page']), $uri);
            self::assertFalse(isset($h['add_header_tag_anonymous_page']), $uri);
            $h = $this->init($uri, 42, ['track_anonymous' => '1']);
            self::assertFalse(isset($h['add_javascript']), "{$uri} (logged in)");
        }
    }

    // ------------------------------------------------------------ M-12 --

    /** An administrator never runs the third-party container, on any page. */
    public function testPrivilegedSessionGetsNoContainer(): void
    {
        foreach (['config:' . UPDATE, 'profile:' . UPDATE, 'user:' . UPDATE] as $right) {
            $h = $this->init('/front/central.php', 1, [], [$right]);
            self::assertFalse(isset($h['add_javascript']), $right);
            self::assertFalse(isset($h['add_header_tag']), $right);
        }
    }

    /** Administration pages never get it either, whatever the session. */
    public function testAdministrationPagesGetNoContainer(): void
    {
        foreach (['/front/user.form.php?id=2', '/front/preference.php', '/plugins/matomo/front/config.php'] as $uri) {
            $h = $this->init($uri, 42, ['track_anonymous' => '1']);
            self::assertFalse(isset($h['add_javascript']), $uri);
            self::assertFalse(isset($h['add_javascript_anonymous_page']), $uri);
        }
    }

    public function testFailingRightCheckInjectsNothing(): void
    {
        global $PLUGIN_HOOKS;
        $this->init('/front/central.php', 42);
        Session::$haveRightThrows = true;
        $PLUGIN_HOOKS = [];
        $_SERVER['HTTP_HOST']   = 'glpi.example';
        $_SERVER['REQUEST_URI'] = '/front/central.php';
        Session::$loginUserId   = 42;
        plugin_init_matomo();
        Session::$haveRightThrows = false;
        self::assertFalse(isset($PLUGIN_HOOKS['add_javascript']));
    }

    // ------------------------------------------------------------ L-41 --

    /** The pseudonym is keyed with the plugin's own salt, never GLPI's master key. */
    public function testPseudonymDoesNotUseGlpiEncryptionKey(): void
    {
        $salt = str_repeat('ab', 32);
        $h = $this->init('/front/central.php', 42, [
            'user_id_mode'         => 'pseudonym',
            MatomoConfig::SALT_KEY => (new GLPIKey())->encrypt($salt),
        ]);
        $uid = '';
        foreach ($h['add_header_tag']['matomo'] ?? [] as $tag) {
            if ($tag['properties']['name'] === MatomoConfig::META_USER_ID) {
                $uid = $tag['properties']['content'];
            }
        }
        self::assertSame(substr(hash_hmac('sha256', 'glpi-matomo-uid:42', $salt), 0, 32), $uid);
        self::assertNotSame(substr(hash_hmac('sha256', 'glpi-matomo-uid:42', 'GLPI-MASTER-KEY'), 0, 32), $uid);
        self::assertSame(0, GLPIKey::$getCalls, 'GLPIKey::get() (the master key) must not be called');
    }

    /** No salt stored (not reinstalled): no pseudonym, and never the login instead. */
    public function testPseudonymWithoutSaltSendsNoIdentity(): void
    {
        $h = $this->init('/front/central.php', 42, ['user_id_mode' => 'pseudonym']);
        self::assertTrue(self::loads($h, 'add_javascript'));
        foreach ($h['add_header_tag']['matomo'] ?? [] as $tag) {
            self::assertNotSame(MatomoConfig::META_USER_ID, $tag['properties']['name']);
        }
    }
}
