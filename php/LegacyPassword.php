<?php
namespace creamy;

/** Compatibility with the truncated bcrypt hashes used by existing VICIdial schemas. */
final class LegacyPassword
{
    public static function hash($password, $cost, $salt)
    {
        if (!is_string($password) || strlen($password) > 72 || !is_scalar($cost) || !preg_match('/^\d{1,2}$/', (string) $cost)
            || (int) $cost < 4 || (int) $cost > 16 || !is_string($salt) || strlen($salt) < 16) {
            throw new \InvalidArgumentException('Invalid backend bcrypt parameters.');
        }
        $prefix = sprintf('$2y$%02d$', (int) $cost) . substr(base64_encode($salt), 0, 22);
        $hash = crypt($password, $prefix);
        if (strlen($hash) !== 60) {
            throw new \RuntimeException('Backend password hashing failed.');
        }
        return substr($hash, 29, 31);
    }
}
