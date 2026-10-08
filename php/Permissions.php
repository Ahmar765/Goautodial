<?php
namespace creamy;

final class Permissions
{
    private static function defaultPermissions()
    {
        return (object) array(
            'read' => 'Y', 'create' => 'Y', 'update' => 'Y', 'delete' => 'Y',
            'dashboard_display' => 'Y', 'reportsanalytics_display' => 'Y',
            'recordings_display' => 'Y', 'support_display' => 'Y'
        );
    }

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
            if ($name === '') continue;
            if (isset($permissions->{$name}) && is_object($permissions->{$name})) {
                $result->{$name} = $permissions->{$name};
            } else {
                // Agar module JSON mein explicitly na ho (jaise servers, ivr, did),
                // to crash karne ke bajaye default permissions allow karein.
                $result->{$name} = self::defaultPermissions();
            }
        }
        return count($types) === 1 ? $result->{$types[0]} : $result;
    }
}
