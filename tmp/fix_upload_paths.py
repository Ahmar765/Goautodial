from pathlib import Path
path = Path(__file__).resolve().parent.parent / 'php/CRMUtils.php'
text = path.read_text(encoding='utf-8')
start = text.index('\tpublic static function generateUploadRelativePath(')
end = text.index('\n\t/**', start)
text = text[:start] + '''    public static function generateUploadRelativePath($filename = null, $lockFile = false) {
        $relative = CRM_UPLOADS_DIRNAME . '/' . date('Y') . '/' . date('m') . '/';
        $directory = self::creamyBaseDirectoryPath() . $relative;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \\RuntimeException('Upload directory unavailable.');
        }
        $extension = is_string($filename) ? strtolower(pathinfo(basename(str_replace('\\\\', '/', $filename)), PATHINFO_EXTENSION)) : '';
        $allowed = array('pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'gif', 'wav', 'mp3', 'ogg', 'zip');
        if (!in_array($extension, $allowed, true)) $extension = 'dat';
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        if ($lockFile) {
            $handle = fopen($directory . $name, 'x');
            if ($handle === false) throw new \\RuntimeException('Upload reservation failed.');
            fclose($handle);
        }
        return $relative . $name;
    }
''' + text[end:]
path.write_text(text, encoding='utf-8')
print('Replaced user-controlled upload paths with generated filenames.')
