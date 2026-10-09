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

require_once __DIR__ . '/stubs.php';

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
        Session::$rights       = [];
        GLPIKey::$getCalls     = 0;
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
        MatomoConfig::saveConfig(['container_url' => 'https://s.example/js/container_C1.js', 'user_id_mode' => 'email']);
        self::assertSame([], Config::$store);
        self::assertSame(ERROR, Session::$messages[0][0] ?? null);
    }

    /** An unchecked box posts only the hidden "0"; anything but "1" is off. */
    public function testTrackAnonymousIsStrictlyBoolean(): void
    {
        foreach (['0' => false, '1' => true, 'yes' => false, '' => false] as $posted => $expected) {
            $this->reset();
            MatomoConfig::saveConfig(['container_url' => 'https://s.example/js/container_C1.js', 'track_anonymous' => (string) $posted]);
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
            MatomoConfig::saveConfig(['container_url' => 'https://stats.example.com/js/container_C2.js']);
        } catch (RuntimeException $e) {
            $denied = true;
        }

        self::assertTrue($denied, 'the right must be checked');
        self::assertSame([], Config::$store, 'nothing may be written without the right');
    }

    // ---------------------------------------------------------- defaults --

    /**
     * M-13: an upgraded install (only container_url stored) gets the safe defaults —
     * anonymous pages (login, password reset) are NOT tracked until an admin opts in.
     */
    public function testAnonymousTrackingIsOffWhenKeyMissing(): void
    {
        $this->reset();
        Config::$store['plugin:matomo'] = ['container_url' => 'https://s.example/js/container_C1.js'];
        self::assertSame(
            ['container_url' => 'https://s.example/js/container_C1.js', 'track_anonymous' => false, 'user_id_mode' => 'none'],
            MatomoConfig::getSettings()
        );
        self::assertSame([], MatomoConfig::headerTags(MatomoConfig::getSettings(), true, ''));
    }

    // ------------------------------------------------------ URL guards --

    /**
     * M-12: only the exact shape of an MTM container served over HTTPS is accepted:
     * no credentials, query, fragment, quote or arbitrary script path.
     */
    public function testContainerUrlHostAllowlist(): void
    {
        foreach ([
            'https://stats.convergent.tn/js/container_AbC12345.js',
            'https://stats.example.com/matomo/js/container_X_dev_abc.js',
            'https://stats.example.com:8443/js/container_A1.js',
        ] as $ok) {
            self::assertTrue(MatomoConfig::isValidContainerUrl($ok), $ok);
        }
        foreach ([
            'https://evil.example/payload.js',
            'https://stats.example.com/js/container_A1.js?x=1',
            'https://stats.example.com/js/container_A1.js#x',
            'https://user:pw@stats.example.com/js/container_A1.js',
            'https://stats.example.com@evil.example/js/container_A1.js',
            'https:///js/container_A1.js',
            'https://stats.example.com/js/container_A1.js.php',
            'https://stats.example.com/../container_A1.js',
            'https://stats.example.com/js/"onload=x/container_A1.js',
            'http://stats.example.com/js/container_A1.js',
        ] as $bad) {
            self::assertFalse(MatomoConfig::isValidContainerUrl($bad), $bad);
            $this->reset();
            MatomoConfig::saveConfig(['container_url' => $bad]);
            self::assertSame([], Config::$store, "must not store {$bad}");
        }
    }

    /** M-13: a password-reset (or any token-bearing) URL is recognised as secret. */
    public function testUrlCarryingASecretIsDetected(): void
    {
        foreach ([
            '/front/lostpassword.php?password_forget_token=abc123',
            '/glpi/front/lostpassword.php?x=1&PASSWORD_FORGET_TOKEN=abc',
            '/front/lostpassword.php?password%5Fforget%5Ftoken=abc',
            '/front/central.php?_glpi_csrf_token=abc',
            '/apirest.php/initSession?user_token=abc',
            '/front/login.php?code=abc&state=xyz',
            '/front/x.php?new_password=abc',
        ] as $uri) {
            self::assertTrue(MatomoConfig::urlCarriesSecret($uri), $uri);
        }
        foreach (['/', '/front/central.php', '/front/ticket.form.php?id=12', '/front/lostpassword.php', '/front/helpdesk.public.php?create_ticket=1'] as $uri) {
            self::assertFalse(MatomoConfig::urlCarriesSecret($uri), $uri);
        }
    }

    /** M-12: administration pages are privileged; ordinary pages are not. */
    public function testPrivilegedPathsAreRecognised(): void
    {
        foreach ([
            '/front/config.form.php', '/glpi/front/profile.form.php?id=4', '/front/user.form.php',
            '/front/authldap.form.php', '/front/preference.php', '/front/apiclient.form.php',
            '/front/plugin.php', '/front/crontask.php', '/ajax/rule.php', '/plugins/matomo/front/config.php',
            '/front/CONFIG.form.php', '/front/%63onfig.form.php', '/glpi//front//user.form.php',
            '/front/./profile.form.php', '/plugins/matomo/front/%63onfig.php',
        ] as $uri) {
            self::assertTrue(MatomoConfig::isPrivilegedPath($uri), $uri);
        }
        foreach (['/', '/front/central.php', '/front/ticket.form.php?id=1', '/front/helpdesk.public.php',
                  '/front/computer.php?is_deleted=0&x=/front/config.php'] as $uri) {
            self::assertFalse(MatomoConfig::isPrivilegedPath($uri), $uri);
        }
    }

    /** M-12: a session holding config, profile or user UPDATE is privileged; a failing check too. */
    public function testPrivilegedSessionsFailClosed(): void
    {
        $none = static fn (string $m, int $r): bool => false;
        self::assertFalse(MatomoConfig::isPrivilegedContext('/front/central.php', $none));
        foreach (['config', 'profile', 'user'] as $module) {
            $only = static fn (string $m, int $r): bool => $m === $module && $r === UPDATE;
            self::assertTrue(MatomoConfig::isPrivilegedContext('/front/central.php', $only), $module);
        }
        $throws = static function (string $m, int $r): bool { throw new RuntimeException('no session'); };
        self::assertTrue(MatomoConfig::isPrivilegedContext('/front/central.php', $throws), 'fail closed');
    }

    /**
     * The loader re-checks the same patterns client side; they are copied verbatim
     * and must not drift apart.
     */
    public function testLoaderUsesTheSamePatterns(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../public/js/mtm-loader.js');
        self::assertStringContainsString("new RegExp('" . MatomoConfig::SECRET_PARAM_PATTERN . "', 'i')", $js);
        self::assertStringContainsString("new RegExp('" . MatomoConfig::PRIVILEGED_PATH_PATTERN . "', 'i')", $js);
    }

    // ---------------------------------------------------- pseudonym salt --

    /** L-41: the salt is the plugin's own, random, stored sealed and created once. */
    public function testPseudonymSaltIsOwnSealedAndStable(): void
    {
        $this->reset();
        MatomoConfig::ensurePseudonymSalt();
        $sealed = Config::$store['plugin:matomo'][MatomoConfig::SALT_KEY] ?? '';
        self::assertTrue(str_starts_with($sealed, 'sealed:'), 'stored sealed by GLPIKey');
        $salt = MatomoConfig::pseudonymSecret();
        self::assertSame(1, preg_match('/^[0-9a-f]{64}$/', $salt), 'random 256-bit salt');
        MatomoConfig::ensurePseudonymSalt();
        self::assertSame($salt, MatomoConfig::pseudonymSecret(), 'never regenerated');
        self::assertSame(0, GLPIKey::$getCalls, 'GLPI master key never read');
    }

    /** L-41: an unreadable salt yields no pseudonym (fail closed). */
    public function testUnreadableSaltGivesNoSecret(): void
    {
        $this->reset();
        Config::$store['plugin:matomo'] = [MatomoConfig::SALT_KEY => 'garbage'];
        self::assertSame('', MatomoConfig::pseudonymSecret());
    }

    /** Choosing the pseudonym mode creates the salt when it is missing. */
    public function testSavingPseudonymModeCreatesTheSalt(): void
    {
        $this->reset();
        MatomoConfig::saveConfig(['container_url' => 'https://s.example/js/container_C1.js', 'user_id_mode' => 'pseudonym']);
        self::assertNotSame('', MatomoConfig::pseudonymSecret());
    }

    /** A tampered stored mode falls back to "none", never to sending something. */
    public function testCorruptStoredModeFallsBackToNone(): void
    {
        $this->reset();
        Config::$store['plugin:matomo'] = ['container_url' => 'https://s.example/js/container_C1.js', 'user_id_mode' => 'everything'];
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

    /** When opted in, the login screen never carries an identity. */
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
