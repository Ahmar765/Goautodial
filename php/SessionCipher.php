<?php
namespace creamy;
require_once __DIR__ . '/RuntimeConfig.php';

final class SessionCipher
{
    private $key;

    public function __construct()
    {
        $hex = RuntimeConfig::required('SESSION_ENCRYPTION_KEY');
        if (!preg_match('/^[a-f0-9]{64}$/i', $hex) || !extension_loaded('openssl')) {
            throw new \RuntimeException('A 32-byte hexadecimal session key and OpenSSL are required.');
        }
        $this->key = hex2bin($hex);
    }

    public function encrypt($data)
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($data, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Session encryption failed.');
        }
        return 'v2:' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt($data)
    {
        if (!is_string($data) || substr($data, 0, 3) !== 'v2:') {
            return ''; // Old sessions are invalidated by this security update.
        }
        $payload = base64_decode(substr($data, 3), true);
        if ($payload === false || strlen($payload) < 28) {
            return '';
        }
        $plaintext = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
            substr($payload, 0, 12), substr($payload, 12, 16));
        return $plaintext === false ? '' : $plaintext;
    }
}
