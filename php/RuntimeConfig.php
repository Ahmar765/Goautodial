<?php
namespace creamy;

/** Deployment settings, supplied by environment or a file outside the web root. */
final class RuntimeConfig
{
    private static $settings;

    public static function value($name, $default = '')
    {
        if (self::$settings === null) {
            self::$settings = array();
            $file = getenv('GOAUTODIAL_CONFIG_FILE');
            if ($file !== false && $file !== '') {
                $resolved = realpath($file);
                $root = str_replace('\\', '/', realpath(dirname(__DIR__)));
                $normalized = $resolved === false ? '' : str_replace('\\', '/', $resolved);
                if ($resolved === false || !is_file($resolved) || !is_readable($resolved)
                    || stripos($normalized, $root . '/') === 0) {
                    throw new \RuntimeException('Configuration must be readable and outside the application directory.');
                }
                $settings = require $resolved;
                if (!is_array($settings)) {
                    throw new \RuntimeException('Deployment configuration must return an array.');
                }
                self::$settings = $settings;
            }
        }
        $environment = getenv($name);
        return $environment !== false ? $environment : (self::$settings[$name] ?? $default);
    }

    public static function production()
    {
        return self::value('APP_ENV', 'production') !== 'development';
    }

    public static function required($name)
    {
        $value = self::value($name);
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException('Missing deployment setting: ' . $name);
        }
        return $value;
    }

    public static function url($name, $default = '')
    {
        $value = self::value($name, $default);
        $parts = is_string($value) ? parse_url($value) : false;
        if ($parts === false || empty($parts['host']) || !in_array($parts['scheme'] ?? '', array('https', 'http'), true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (self::production() && $parts['scheme'] !== 'https')) {
            throw new \RuntimeException('Invalid deployment URL: ' . $name);
        }
        return rtrim($value, '/');
    }

    public static function secureRequest()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        // Trust forwarded HTTPS only from explicitly configured proxy addresses.
        $proxies = array_filter(array_map('trim', explode(',', self::value('TRUSTED_PROXIES'))));
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', $proxies, true)
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
