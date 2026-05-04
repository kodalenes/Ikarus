<?php
declare(strict_types=1);

/**
 * FileUploader — Reusable file upload utility.
 *
 * Usage example:
 *   $up  = new FileUploader('../assets/uploads');
 *   $url = $up->upload($_FILES['avatar'], 'teams', 'team_7');
 *   if ($up->hasErrors()) { ... }
 *   $up->delete($oldUrl);
 */
class FileUploader
{
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private string $fsBase;     // Absolute filesystem path to upload root
    private string $webBase;    // Web-relative path prefix (relative to )
    private array  $mimes;
    private int    $maxBytes;
    private array  $errors = [];

    /** Preset profiles keyed by name */
    private static array $PROFILES = [
        'image'  => ['mimes' => ['image/jpeg','image/png','image/webp','image/gif'], 'mb' => 2],
        'avatar' => ['mimes' => ['image/jpeg','image/png','image/webp'],             'mb' => 1],
    ];

    /**
     * @param string $fsBase   Absolute dir for uploads, e.g. dirname(__DIR__).'/assets/uploads'
     * @param string $webBase  Web-relative prefix from , e.g. '../assets/uploads'
     * @param string $profile  'image' | 'avatar' (or override via $mimes/$maxMb)
     * @param array  $mimes    Override allowed MIME types
     * @param int    $maxMb    Override max size in MB
     */
    public function __construct(
        string $fsBase  = '',
        string $webBase = '../assets/uploads',
        string $profile = 'image',
        array  $mimes   = [],
        int    $maxMb   = 0
    ) {
        $this->fsBase  = $fsBase ?: dirname(__DIR__) . '/assets/uploads';
        $this->webBase = rtrim($webBase, '/');

        $p            = self::$PROFILES[$profile] ?? self::$PROFILES['image'];
        $this->mimes  = $mimes  ?: $p['mimes'];
        $this->maxBytes = ($maxMb ?: $p['mb']) * 1_048_576;
    }

    /**
     * Upload a file.
     *
     * @param array  $file      $_FILES['field']
     * @param string $subfolder Sub-directory, e.g. 'teams'
     * @param string $prefix    Filename prefix, e.g. 'team_7'
     * @return string|null      Web-relative path on success, null on failure
     */
    public function upload(array $file, string $subfolder = '', string $prefix = 'upload'): ?string
    {
        $this->errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->errMsg($file['error']);
            return null;
        }
        if ($file['size'] > $this->maxBytes) {
            $this->errors[] = 'File exceeds ' . round($this->maxBytes / 1_048_576, 1) . ' MB limit.';
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $this->mimes, true)) {
            $this->errors[] = 'File type not allowed.';
            return null;
        }

        $ext = self::MIME_EXT[$mime] ?? 'bin';
        $dir = $this->fsBase . ($subfolder ? '/' . trim($subfolder, '/') : '');

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->errors[] = 'Could not create upload directory.';
            return null;
        }

        $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->errors[] = 'Failed to save the file.';
            return null;
        }

        $sub = $subfolder ? "/$subfolder" : '';
        return "{$this->webBase}{$sub}/{$name}";
    }

    /**
     * Delete a file given its web-relative path (as returned by upload()).
     */
    public function delete(string $webPath): bool
    {
        if (!$webPath) return false;
        // Strip webBase prefix to get the relative sub-path
        $rel = ltrim(str_replace($this->webBase, '', $webPath), '/');
        $abs = $this->fsBase . '/' . $rel;
        return file_exists($abs) && unlink($abs);
    }

    public function errors(): array   { return $this->errors; }
    public function hasErrors(): bool { return !empty($this->errors); }
    public function firstError(): string { return $this->errors[0] ?? 'Unknown error.'; }

    private function errMsg(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
            UPLOAD_ERR_PARTIAL   => 'Partial upload — please try again.',
            UPLOAD_ERR_NO_FILE   => 'No file provided.',
            default              => "Upload error (code {$code}).",
        };
    }
}