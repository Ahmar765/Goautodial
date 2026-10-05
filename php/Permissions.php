<?php
namespace creamy;

final class Permissions
{
    public static function forResponse($response, $type)
    {
        $encoded = is_object($response) ? ($response->data->permissions ?? null) : null;
        $permissions = is_string($encoded) ? json_decode($encoded) : null;
        if (!is_object($permissions) || ($response->result ?? '') !== 'success') {
            throw new \RuntimeException('Backend group permissions unavailable.');
        }
        if ($type === 'sidebar') return $response;
        $types = array_map('trim', explode(',', $type));
        $result = new \stdClass();
        foreach ($types as $name) {
            if ($name === '' || !isset($permissions->{$name}) || !is_object($permissions->{$name})) {
                throw new \RuntimeException('Backend group permission missing.');
            }
            $result->{$name} = $permissions->{$name};
        }
        return count($types) === 1 ? $result->{$types[0]} : $result;
    }
}
