<?php
/**
 * Stubs of the few GLPI core classes the plugin touches, shared by every test
 * file (run.php and PHPUnit may load them in any order). The plugin's own files
 * are always the real, shipped ones.
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

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
            $all = self::$store[$ctx] ?? [];
            return $keys === [] ? $all : array_intersect_key($all, array_flip($keys));
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
        public static bool $active = true;
        public function isActivated(string $k): bool { return self::$active && $k === 'matomo'; }
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
        /** @var array<string, bool> "module:right" => granted, for haveRight() */
        public static array $rights = [];
        public static int $loginUserId = 0;
        public static bool $haveRightThrows = false;

        public static function getLoginUserID(): int|false
        {
            return self::$loginUserId > 0 ? self::$loginUserId : false;
        }

        public static function haveRight(string $module, int $right): bool
        {
            if (self::$haveRightThrows) {
                throw new RuntimeException('session unavailable');
            }
            return self::$rights[$module . ':' . $right] ?? false;
        }

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

if (!class_exists('GLPIKey')) {
    /** Reversible fake sealing; get() — the master key — must never be used by the plugin. */
    class GLPIKey
    {
        public static int $getCalls = 0;

        public function get(): string
        {
            self::$getCalls++;
            return 'GLPI-MASTER-KEY';
        }

        public function encrypt(string $s): string { return 'sealed:' . base64_encode($s); }

        public function decrypt(?string $s): ?string
        {
            if ($s === null || !str_starts_with($s, 'sealed:')) {
                return null;
            }
            return (string) base64_decode(substr($s, 7), true);
        }
    }
}

require_once __DIR__ . '/../src/Config.php';
