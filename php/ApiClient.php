<?php
namespace creamy;
require_once __DIR__ . '/RuntimeConfig.php';

final class ApiClient
{
    public static function post($url, $fields, $timeout = 30, $multipart = false)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('Native PHP cURL is required.');
        }
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', array('https', 'http'), true)
            || (RuntimeConfig::production() && $parts['scheme'] !== 'https')
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('Invalid API transport.');
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => is_array($fields) && !$multipart ? http_build_query($fields) : $fields,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => array('Accept: application/json')
        ));
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $failed = $body === false || $status < 200 || $status >= 300;
        curl_close($curl);
        if ($failed) {
            // Do not log request payloads, passwords or remote response bodies.
            error_log('GOautodial backend request failed (HTTP ' . $status . ').');
            return null;
        }
        return $body;
    }
}
