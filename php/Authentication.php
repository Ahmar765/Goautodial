<?php
namespace creamy;
require_once __DIR__ . '/LegacyPassword.php';

final class Authentication
{
    public static function userFromResponse($body, $password, $identity)
    {
        if (!is_string($body) || !is_string($password) || $password === '') {
            return null;
        }
        $user = json_decode($body);
        if (!is_object($user) || ($user->result ?? '') !== 'success'
            || !is_string($user->active ?? null) || strtoupper($user->active) !== 'Y'
            || !isset($user->user_id) || !is_scalar($user->user_id) || (string) $user->user_id === ''
            || !isset($user->user_level) || !is_scalar($user->user_level) || !preg_match('/^[1-9]$/', (string) $user->user_level)
            || (int) $user->user_level < 1 || (int) $user->user_level > 9
            || !isset($user->pass) || !is_string($user->pass)) {
            return null;
        }
        try {
            $hash = (int) ($user->bcrypt ?? 0) > 0
                ? LegacyPassword::hash($password, $user->cost ?? 0, $user->salt ?? '') : $password;
        } catch (\Throwable $exception) {
            return null;
        }
        if (!hash_equals($user->pass, $hash)) {
            return null;
        }
        $username = $user->user ?? $user->userno ?? $user->user_name ?? $identity;
        if (!is_string($username) || $username === '' || filter_var($username, FILTER_VALIDATE_EMAIL)) {
            // Email login must resolve to an actual backend account name.
            return null;
        }
        $level = (int) $user->user_level;
        $roles = array(9 => 0, 8 => 1, 7 => 2);
        return array(
            'id' => (string) $user->user_id, 'user' => $username,
            'name' => (string) ($user->full_name ?? $username), 'email' => (string) ($user->email ?? ''),
            'role' => $roles[$level] ?? 3, 'level' => $level,
            'user_group' => (string) ($user->user_group ?? ''),
            'phone_login' => (string) ($user->phone_login ?? ''), 'phone_pass' => (string) ($user->phone_pass ?? ''),
            'ha1' => (string) ($user->ha1 ?? ''), 'realm' => (string) ($user->realm ?? ''),
            'bcrypt' => (int) ($user->bcrypt ?? 0), 'use_webrtc' => (string) ($user->use_webrtc ?? '0'),
            'password_hash' => $hash, 'avatar' => (string) ($user->avatar ?? '')
        );
    }
}
