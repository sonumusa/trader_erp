<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Secure file upload service (spec §44).
 *
 * Rules:
 *   * storage lives OUTSIDE the webroot (storage/uploads) — never served
 *     directly; downloads go through an authenticated, permission-checked route
 *   * extension + MIME-type whitelist per category (documents / images)
 *   * size cap (default 5 MB)
 *   * stored under a random name (no user-controlled path), original name kept
 *     only in the DB (escaped on render)
 *   * the upload is recorded in attachments linked to a document
 */
final class UploadService
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    /** When false, skips is_uploaded_file() so unit tests can feed temp files. */
    public static bool $strictUploadCheck = true;

    /** category => allowed extensions => allowed mime prefixes */
    private const CATEGORIES = [
        'image' => [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp',
        ],
        'document' => [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain', 'csv' => 'text/csv',
        ],
    ];

    /** Upload dir (webroot-adjacent but outside public/). */
    private static function dir(): string
    {
        $dir = APP_ROOT . '/storage/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Validate + store an uploaded file.
     * $file = $_FILES entry; $category ∈ image|document.
     * @return array{attachment_id:int, original_name:string, mime_type:string, size:int}
     */
    public static function store(array $file, string $category, int $companyId, string $documentType, int $documentId, ?int $userId = null): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $msg = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the size limit.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                default => 'The upload failed. Please try again.',
            };
            throw new \RuntimeException($msg);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $original = basename((string) ($file['name'] ?? ''));
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('The file must be between 1 byte and ' . self::MAX_BYTES . ' bytes (5 MB).');
        }
        if (self::$strictUploadCheck && !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Invalid upload source.');
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::CATEGORIES[$category][$ext])) {
            throw new \RuntimeException('File type .' . $ext . ' is not allowed for ' . $category . ' uploads.');
        }

        // Double-check the MIME with finfo (not the client-supplied type).
        // Accept the upload when finfo's type matches the expected type exactly,
        // or when it shares the same primary type (e.g. image/* for images).
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($fi, $tmp);
            finfo_close($fi);
        }
        $expected = self::CATEGORIES[$category][$ext];
        if ($mime !== '') {
            $ok = $mime === $expected;
            // Tolerance: OOXML documents often sniff as zip; text sniffing
            // may add charset parameters — both are safe within the whitelist.
            if (!$ok && $category === 'document') {
                $ok = ($mime === 'application/zip' && in_array($ext, ['docx', 'xlsx'], true))
                    || str_starts_with($mime, 'text/');
            }
            if (!$ok) {
                throw new \RuntimeException('The file content does not match its extension.');
            }
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = self::dir() . '/' . $storedName;

        $moved = self::$strictUploadCheck
            ? move_uploaded_file($tmp, $dest)
            : copy($tmp, $dest);
        if (!$moved) {
            throw new \RuntimeException('The file could not be stored. Check storage directory permissions.');
        }
        @chmod($dest, 0644);

        $now = date('Y-m-d H:i:s');
        Database::execute(
            'INSERT INTO attachments (company_id, document_type, document_id, original_name, stored_name, mime_type, size_bytes, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$companyId, $documentType, $documentId, mb_substr($original, 0, 255), $storedName, $mime ?: $expected, $size, $userId ?? AuthService::id(), $now]
        );

        return [
            'attachment_id' => (int) Database::lastInsertId(),
            'original_name' => $original,
            'mime_type'     => $mime ?: $expected,
            'size'          => $size,
        ];
    }

    /** Attachments for a document. */
    public static function forDocument(int $companyId, string $documentType, int $documentId): array
    {
        return Database::query(
            'SELECT a.*, u.name AS uploaded_name
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.company_id = ? AND a.document_type = ? AND a.document_id = ?
             ORDER BY a.id DESC',
            [$companyId, $documentType, $documentId]
        );
    }

    /** Find an attachment (with company check). */
    public static function find(int $companyId, int $id): ?array
    {
        return Database::row(
            'SELECT * FROM attachments WHERE id = ? AND company_id = ?',
            [$id, $companyId]
        );
    }

    /** Stream a stored file for download (auth/permission handled by caller). */
    public static function stream(array $attachment): array
    {
        $path = self::dir() . '/' . $attachment['stored_name'];
        if (!is_file($path)) {
            throw new \RuntimeException('The stored file is missing.');
        }
        return [
            'path'         => $path,
            'download_name' => $attachment['original_name'],
            'mime'         => $attachment['mime_type'] ?: 'application/octet-stream',
            'size'         => (int) $attachment['size_bytes'],
        ];
    }

    public static function delete(int $companyId, int $id): void
    {
        $att = self::find($companyId, $id);
        if ($att) {
            $path = self::dir() . '/' . $att['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
            Database::execute('DELETE FROM attachments WHERE id = ?', [$id]);
        }
    }
}
