<?php
/**
 * Matomo Tag Manager — configuration logic.
 *
 * The plugin injects a third-party script tag into every GLPI page, so its two
 * guards matter more than its size suggests: the container URL must be HTTPS,
 * and it must reach the browser as data, never as executable JavaScript.
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
        public static function getWebDir(string $k): string { return '/plugins/' . $k; }
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
    /** Fresh state for every test: the stubs are static. */
    private function reset(): string
    {
        Config::$store          = [];
        Session::$messages      = [];
        Session::$rightGranted  = true;
        $dir = sys_get_temp_dir() . '/matomo-test-' . getmypid();
        @mkdir($dir . '/public/js', 0700, true);
        @unlink($dir . '/public/js/mtm-config.js');
        Plugin::$phpDir = $dir;
        return $dir;
    }

    private function writtenJs(string $dir): string
    {
        $f = $dir . '/public/js/mtm-config.js';
        return is_file($f) ? (string) file_get_contents($f) : '';
    }

    /**
     * The value as a JavaScript engine would read it: the string literal is
     * extracted and decoded, so the assertions talk about what the browser
     * ends up with rather than about the escaping used to get it there.
     */
    private function decodedJsValue(string $dir): ?string
    {
        if (!preg_match('/^window\\.MATOMO_CONTAINER_URL=(.*);\\n$/s', $this->writtenJs($dir), $m)) {
            return null;
        }
        $decoded = json_decode($m[1], true);
        return is_string($decoded) ? $decoded : null;
    }

    /**
     * A plain-HTTP container URL is refused. This is the whole point of the
     * guard: the script is loaded into every authenticated GLPI page, so an
     * http:// source is a downgrade an attacker on the path can rewrite.
     */
    public function testHttpUrlIsRejectedAndNotStored(): void
    {
        $dir = $this->reset();
        MatomoConfig::saveConfig(['container_url' => 'http://stats.example.com/js/container_abc.js']);

        self::assertSame([], Config::$store, 'a rejected URL must not be persisted');
        self::assertSame('', $this->writtenJs($dir), 'a rejected URL must not reach the JS file');
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

    /** The nominal case still works, and reaches both the config and the file. */
    public function testHttpsUrlIsStoredAndWritten(): void
    {
        $dir = $this->reset();
        $url = 'https://stats.convergent.tn/js/container_XYZ.js';
        MatomoConfig::saveConfig(['container_url' => $url]);

        self::assertSame($url, MatomoConfig::getContainerUrl());
        self::assertSame($url, $this->decodedJsValue($dir), 'the browser must read back the exact URL');
        self::assertSame(INFO, Session::$messages[0][0] ?? null);
    }

    /** An empty value is a legitimate way to switch the tracking off. */
    public function testEmptyUrlClearsTheConfiguration(): void
    {
        $dir = $this->reset();
        MatomoConfig::saveConfig(['container_url' => '  ']);

        self::assertSame('', MatomoConfig::getContainerUrl());
        self::assertSame("window.MATOMO_CONTAINER_URL=\"\";\n", $this->writtenJs($dir));
    }

    /**
     * The URL is written into a .js file. If it were concatenated raw, a stored
     * value could close the string and append arbitrary JavaScript — a stored
     * XSS on every page of GLPI. json_encode is what prevents it; this test
     * fails the day someone "simplifies" it into string concatenation.
     */
    public function testWrittenJsCannotBeEscapedFromTheStringLiteral(): void
    {
        $dir = $this->reset();
        $payload = 'https://x/a.js";alert(document.cookie);//';
        MatomoConfig::writeConfigJs($payload);

        // The payload survives as *data*: it decodes back to itself, which is
        // only possible if the quote never terminated the literal early.
        self::assertSame($payload, $this->decodedJsValue($dir));
    }

    /** A </script> in the value must not be able to close the surrounding tag. */
    public function testWrittenJsEscapesClosingScriptTag(): void
    {
        $dir = $this->reset();
        $payload = 'https://x/</script><script>alert(1)</script>';
        MatomoConfig::writeConfigJs($payload);

        self::assertStringNotContainsString('</script>', $this->writtenJs($dir));
        self::assertSame($payload, $this->decodedJsValue($dir));
    }

    /** The written file is always valid, parseable JavaScript. */
    public function testWrittenJsIsSyntacticallyValid(): void
    {
        $dir = $this->reset();
        MatomoConfig::writeConfigJs('https://x/a.js?q="\\\'&<>');

        self::assertMatchesRegularExpression(
            '/^window\.MATOMO_CONTAINER_URL=".*";\n$/s',
            $this->writtenJs($dir)
        );
    }

    /**
     * Saving is an administrative action: without the `config` UPDATE right it
     * must not proceed. Fail closed — the check comes first, before any write.
     */
    public function testSavingRequiresTheConfigurationRight(): void
    {
        $dir = $this->reset();
        Session::$rightGranted = false;

        $denied = false;
        try {
            MatomoConfig::saveConfig(['container_url' => 'https://stats.example.com/js/c.js']);
        } catch (RuntimeException $e) {
            $denied = true;
        }

        self::assertTrue($denied, 'the right must be checked');
        self::assertSame([], Config::$store, 'nothing may be written without the right');
        self::assertSame('', $this->writtenJs($dir));
    }

    /** A missing key must not raise; it behaves like an empty value. */
    public function testMissingKeyIsTreatedAsEmpty(): void
    {
        $this->reset();
        MatomoConfig::saveConfig([]);
        self::assertSame('', MatomoConfig::getContainerUrl());
    }

    /** Reading a never-configured plugin returns '' rather than failing. */
    public function testUnconfiguredPluginReturnsEmptyUrl(): void
    {
        $this->reset();
        self::assertSame('', MatomoConfig::getContainerUrl());
    }
}
