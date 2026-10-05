<?php
namespace creamy;
final class AvatarImage
{
    public static function decode($base64)
    {
        if (!is_string($base64) || strlen($base64) > 2800000) return null;
        $data = base64_decode($base64, true);
        if ($data === false || strlen($data) > 2097152) return null;
        $size = @getimagesizefromstring($data);
        $types = array(IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_GIF => 'image/gif');
        if (!$size || !isset($types[$size[2]]) || $size[0] > 4096 || $size[1] > 4096) return null;
        return array('type' => $types[$size[2]], 'data' => base64_encode($data));
    }
}
