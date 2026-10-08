<?php
/**
 * Matomo Tag Manager — configuration logic.
 *
 * The plugin injects a third-party script into GLPI pages, so its guards matter
 * more than its size suggests: the container URL must be HTTPS, settings reach
 * the browser as escaped <meta> data (never as JavaScript), and the user's
 * identity is only sent when an administrator opted in — as a non-reversible
 * pseudonym unless the login itself was explicitly chosen.
 *
 * No DB, no GLPI core: the handful of core classes the plugin touches are
 * stubbed below.
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------- GLPI stubs
if (!defined('UPDATE')) { define('UPDATE', 2); }
if (!defined('ERROR'))  { define('ERROR', 1); }
if (!defined('INFO'))   { define('INFO', 0); }
if (!defined('GLPI_VERSION')) { define('GLPI_VERSION', '11.0.7'); }

if (!function_exists('__')) {
    function __(string $s, ?string $d = null): string { return $s; }
}

if (!class_exists('CommonGLPI')) {
    abstract class CommonGLPI {}
}

if (!class_exists('Config')) {
    class Config
    {
        public static array $store = [];

        public static function getConfigurationValues(string $ctx, array $keys = []): array
        {
            return self::$store[$ctx] ?? [];
        }

        public static function setConfigurationValues(string $ctx, array $values): void
        {
            self::$store[$ctx] = array_merge(self::$store[$ctx] ?? [], $values);
        }
    }
}

if (!class_exists('Html')) {
    class Html
    {
        public static function submit(string $label, array $opts = []): string { return '<button>' . $label . '</button>'; }
        public static function closeForm(): void { echo '</form>'; }
    }
}

if (!class_exists('Plugin')) {
    class Plugin
    {
        public static string $phpDir = '';
        // Removed in GLPI 12: on GLPI 11+ the plugin must not call it at all.
        public static function getWebDir(string $k): string { throw new RuntimeException('Plugin::getWebDir() called'); }
        public static function getPhpDir(string $k): string { return self::$phpDir; }
    }
}

if (!class_exists('Session')) {
    class Session
    {
        public static array $messages = [];
        public static bool $rightGranted = true;

        public static function checkRight(string $module, int $right): void
        {
            if (!self::$rightGranted) {
                throw new RuntimeException('right denied: ' . $module);
            }
        }

        public static function addMessageAfterRedirect(string $msg, bool $check = false, int $level = 0): void
        {
            self::$messages[] = [$level, $msg];
        }
    }
}

require_once __DIR__ . '/../src/Config.php';

use GlpiPlugin\Matomo\Config as MatomoConfig;

final class ConfigTest extends TestCase
{
    private const SECRET = 'test-secret-0123456789abcdef';

    /** Fresh state for every test: the stubs are static. */
    private function reset(): void
    {
        Config::$store         = [];
        Session::$messages     = [];
        Session::$rightGranted = true;
    }

    private function settings(array $over = []): array
    {
        return array_merge([
            'container_url'   => 'https://stats.example.com/js/container_X.js',
            'track_anonymous' => true,
            'user_id_mode'    => MatomoConfig::USER_ID_NONE,
        ], $over);
    }

    /** @return array<string, string> meta name => content */
    private static function metas(array $tags): array
    {
        $out = [];
        foreach ($tags as $t) {
            self::assertSame('meta', $t['tag']);
            $out[$t['properties']['name']] = $t['properties']['content'];
        }
        return $out;
    }

    // ------------------------------------------------------------ saving --

    /**
     * A plain-HTTP container URL is refused: the script runs in every
     * authenticated GLPI page, an http:// source is a downgrade an attacker on
     * the path can rewrite.
     */
    public function testHttpUrlIsRejectedAndNotStored(): void
    {
        $this->reset();
        MatomoConfig::saveConfig(['container_url' => 'http://stats.example.com/js/container_abc.js']);

        self::assertSame([], Config::$store, 'a rejected URL must not be persisted');
        self::assertSame(ERROR, Session::$messages[0][0] ?? null, 'the user must be told it failed');
    }

    /** Anything that is not https:// is refused, not merely non-http. */
    public function testNonHttpsSchemesAreRejected(): void
    {
        foreach (['javascript:alert(1)', 'data:text/javascript,alert(1)', '//evil.example.com/x.js', 'ftp://h/x.js'] as $url) {
            $this->reset();
            MatomoConfig::saveConfig(['container_url' => $url]);
            self::assertSame([], Config::$store, "must reject {$url}");
        }
    }

    public function testHttpsUrlAndOptionsAreStored(): void
    {
        $this->reset();
        $url = 'https://stats.convergent.tn/js/container_XYZ.js';
        MatomoConfig::saveConfig(['container_url' => $url, 'track_anonymous' => '0', 'user_id_mode' => 'pseudonym']);

        self::assertSame(
            ['container_url' => $url, 'track_anonymous' => false, 'user_id_mode' => 'pseudonym'],
            MatomoConfig::getSettings()
        );
        self::assertSame(INFO, Session::$messages[0][0] ?? null);
    }

    /** An empty value is a legitimate way to switch the tracking off. */
    public function testEmptyUrlClearsTheConfiguration(): void
    {
        $this->reset();
        MatomoConfig::saveConfig(['container_url' => '  ']);
        self::assertSame('', MatomoConfig::getContainerUrl());
    }

    /** Whitelist: an unknown identity mode is refused, nothing is written. */
    public function testUnknownUserIdModeIsRejected(): void
    {
        $this->reset();
        MatomoConfig::saveConfig(['container_url' => 'https://s/c.js', 'user_id_mode' => 'email']);
        self::assertSame([], Config::$store);
        self::assertSame(ERROR, Session::$messages[0][0] ?? null);
    }

    /** An unchecked box posts only the hidden "0"; anything but "1" is off. */
    public function testTrackAnonymousIsStrictlyBoolean(): void
    {
        foreach (['0' => false, '1' => true, 'yes' => false, '' => false] as $posted => $expected) {
            $this->reset();
            MatomoConfig::saveConfig(['container_url' => 'https://s/c.js', 'track_anonymous' => (string) $posted]);
            self::assertSame($expected, MatomoConfig::getSettings()['track_anonymous'], "posted '{$posted}'");
        }
    }

    /**
     * Saving is an administrative action: without the `config` UPDATE right it
     * must not proceed. Fail closed — the check comes first, before any write.
     */
    public function testSavingRequiresTheConfigurationRight(): void
    {
        $this->reset();
        Session::$rightGranted = false;

        $denied = false;
        try {
            MatomoConfig::saveConfig(['container_url' => 'https://stats.example.com/js/c.js']);
        } catch (RuntimeException $e) {
            $denied = true;
        }

        self::assertTrue($denied, 'the right must be checked');
        self::assertSame([], Config::$store, 'nothing may be written without the right');
    }

    // ---------------------------------------------------------- defaults --

    /** An upgraded install (only container_url stored) gets the safe defaults. */
    public function testDefaultsAfterUpgrade(): void
    {
        $this->reset();
        Config::$store['plugin:matomo'] = ['container_url' => 'https://s/c.js'];
        self::assertSame(
            ['container_url' => 'https://s/c.js', 'track_anonymous' => true, 'user_id_mode' => 'none'],
            MatomoConfig::getSettings()
        );
    }

    /** A tampered stored mode falls back to "none", never to sending something. */
    public function testCorruptStoredModeFallsBackToNone(): void
    {
        $this->reset();
        Config::$store['plugin:matomo'] = ['container_url' => 'https://s/c.js', 'user_id_mode' => 'everything'];
        self::assertSame('none', MatomoConfig::getSettings()['user_id_mode']);
    }

    public function testUnconfiguredPluginReturnsEmptyUrl(): void
    {
        $this->reset();
        self::assertSame('', MatomoConfig::getContainerUrl());
    }

    // ---------------------------------------------------------- identity --

    public function testNoIdentityByDefault(): void
    {
        self::assertSame('', MatomoConfig::userIdFor('none', 42, 'jdupont', self::SECRET));
    }

    public function testPseudonymIsStableAndDistinctPerUser(): void
    {
        $a = MatomoConfig::userIdFor('pseudonym', 42, 'jdupont', self::SECRET);
        self::assertSame($a, MatomoConfig::userIdFor('pseudonym', 42, 'jdupont', self::SECRET), 'stable');
        self::assertNotSame($a, MatomoConfig::userIdFor('pseudonym', 43, 'jdupont', self::SECRET), 'per user');
        self::assertSame(1, preg_match('/^[0-9a-f]{32}$/', $a), 'opaque hex: ' . $a);
    }

    /** Neither the id, the login nor a plain hash of the id can be read back. */
    public function testPseudonymRevealsNeitherIdNorLogin(): void
    {
        $p = MatomoConfig::userIdFor('pseudonym', 42, 'jdupont', self::SECRET);
        self::assertStringNotContainsString('jdupont', $p);
        self::assertNotSame(substr(hash('sha256', '42'), 0, 32), $p, 'must be keyed, not a bare hash');
        self::assertNotSame($p, MatomoConfig::userIdFor('pseudonym', 42, 'jdupont', 'another-key'), 'keyed');
    }

    /** Fail closed: no key → no pseudonym, and above all no fallback to the login. */
    public function testPseudonymWithoutKeySendsNothing(): void
    {
        self::assertSame('', MatomoConfig::userIdFor('pseudonym', 42, 'jdupont', ''));
    }

    public function testLoginModeSendsTheLogin(): void
    {
        self::assertSame('jdupont', MatomoConfig::userIdFor('login', 42, 'jdupont', self::SECRET));
    }

    public function testAnonymousSessionHasNoIdentity(): void
    {
        foreach (['pseudonym', 'login'] as $mode) {
            self::assertSame('', MatomoConfig::userIdFor($mode, 0, '', self::SECRET), $mode);
        }
    }

    // ------------------------------------------------------- header tags --

    public function testLoggedInPagesCarryContainerAndIdentity(): void
    {
        $m = self::metas(MatomoConfig::headerTags($this->settings(), false, 'abc123'));
        self::assertSame(
            ['glpi-matomo-container' => 'https://stats.example.com/js/container_X.js', 'glpi-matomo-uid' => 'abc123'],
            $m
        );
    }

    public function testNoIdentityTagWhenThereIsNone(): void
    {
        $m = self::metas(MatomoConfig::headerTags($this->settings(), false, ''));
        self::assertSame(['glpi-matomo-container'], array_keys($m));
    }

    /** The login screen is tracked by default, and never carries an identity. */
    public function testAnonymousPagesCarryTheContainerOnly(): void
    {
        $m = self::metas(MatomoConfig::headerTags($this->settings(), true, 'abc123'));
        self::assertSame(['glpi-matomo-container'], array_keys($m));
    }

    public function testAnonymousTrackingCanBeSwitchedOff(): void
    {
        self::assertSame([], MatomoConfig::headerTags($this->settings(['track_anonymous' => false]), true, ''));
        self::assertNotSame([], MatomoConfig::headerTags($this->settings(['track_anonymous' => false]), false, ''));
    }

    /** Defence in depth: a non-https URL that reached the store is never emitted. */
    public function testNonHttpsStoredUrlIsNeverEmitted(): void
    {
        foreach (['', 'http://x/c.js', 'javascript:alert(1)'] as $url) {
            self::assertSame([], MatomoConfig::headerTags($this->settings(['container_url' => $url]), false, 'u'), $url);
        }
    }

    // -------------------------------------------------------------- form --

    /**
     * The form posts to root_doc/plugins/matomo without calling
     * Plugin::getWebDir(), which GLPI 12 removes (the stub throws).
     */
    public function testConfigFormBuildsItsUrlWithoutGetWebDir(): void
    {
        $this->reset();
        $GLOBALS['CFG_GLPI']['root_doc'] = '/glpi';
        ob_start();
        try {
            MatomoConfig::showConfigForm();
        } finally {
            $html = (string) ob_get_clean();
        }
        self::assertTrue(
            str_contains($html, 'action="/glpi/plugins/matomo/front/config.php"'),
            'unexpected form action: ' . $html
        );
    }

    /** A stored URL is echoed back escaped in the form (no attribute break-out). */
    public function testConfigFormEscapesTheStoredUrl(): void
    {
        $this->reset();
        Config::$store['plugin:matomo'] = ['container_url' => 'https://x/"><script>alert(1)</script>'];
        ob_start();
        try {
            MatomoConfig::showConfigForm();
        } finally {
            $html = (string) ob_get_clean();
        }
        self::assertStringNotContainsString('<script>alert(1)', $html);
    }
}
