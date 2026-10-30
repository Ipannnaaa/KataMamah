<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Unggahan berkas warga. Disimpan DI LUAR web root (storage/uploads) dan
 * hanya bisa dibaca lewat FileController yang memeriksa hak akses.
 */
final class UploadService
{
    /**
     * Mengambil semua file dari input bernama "berkas[<requirement_id>]",
     * memvalidasinya, lalu memindahkannya ke storage.
     *
     * $allowed adalah daftar id syarat milik layanan yang dipilih. Kunci di
     * luar daftar itu ditolak: tanpa batas ini, lampiran bisa dikirim atas
     * nama syarat layanan kantor lain, dan jumlah berkas yang masuk sama
     * sekali tidak dibatasi oleh formulirnya.
     *
     * @param array<string, mixed>|null $field  $_FILES['berkas']
     * @param array<int, int> $allowed
     * @return array<int, array{requirement_id: int|null, path: string, original_name: string, mime: string, size: int}>
     */
    public static function collect(?array $field, array $allowed): array
    {
        if ($field === null || !isset($field['name']) || !is_array($field['name'])) {
            return [];
        }

        $stored = [];

        try {
            foreach ($field['name'] as $requirementId => $name) {
                $error = (int) ($field['error'][$requirementId] ?? UPLOAD_ERR_NO_FILE);

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Berkas "' . $name . '" gagal diunggah (kode ' . $error . ').');
                }

                if (!is_numeric($requirementId) || !in_array((int) $requirementId, $allowed, true)) {
                    throw new RuntimeException('Ada lampiran yang bukan syarat layanan ini.');
                }

                $stored[] = self::store(
                    (string) $field['tmp_name'][$requirementId],
                    (string) $name,
                    (int) ($field['size'][$requirementId] ?? 0),
                    (int) $requirementId
                );
            }
        } catch (RuntimeException $e) {
            // Berkas yang sudah telanjur pindah ikut dibuang; satu lampiran
            // yang ditolak tidak boleh meninggalkan sisa di penyimpanan.
            self::discard($stored);

            throw $e;
        }

        return $stored;
    }

    /**
     * Menghapus berkas yang sudah tersimpan tetapi antreannya batal dibuat.
     *
     * Tanpa ini, tiap permohonan yang gagal di tengah jalan meninggalkan
     * dokumen pribadi warga di penyimpanan tanpa satu pun baris yang
     * menunjuknya — tidak terbaca siapa pun, dan tidak pernah terhapus.
     *
     * @param array<int, array{path: string}> $files
     */
    public static function discard(array $files): void
    {
        foreach ($files as $file) {
            $path = self::absolutePath((string) ($file['path'] ?? ''));

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{requirement_id: int|null, path: string, original_name: string, mime: string, size: int}
     */
    public static function store(string $tmpPath, string $originalName, int $size, ?int $requirementId): array
    {
        $max = (int) config('app.upload.max_bytes', 3145728);

        if ($size <= 0 || $size > $max) {
            throw new RuntimeException('Ukuran berkas "' . $originalName . '" melebihi ' . round($max / 1048576, 1) . ' MB.');
        }

        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Berkas tidak dikenali sebagai unggahan yang sah.');
        }

        // Tipe diambil dari isi berkas, bukan dari ekstensi atau header klien.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);
        $allowed = (array) config('app.upload.mimes', []);

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Tipe berkas "' . $mime . '" tidak diizinkan. Gunakan PDF, JPG, atau PNG.');
        }

        $dir = rtrim((string) config('app.upload.path', BASE_PATH . '/storage/uploads'), '/') . '/' . date('Y/m');

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder penyimpanan berkas tidak dapat dibuat.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $name;

        if (!move_uploaded_file($tmpPath, $target)) {
            throw new RuntimeException('Berkas gagal dipindahkan ke penyimpanan.');
        }

        @chmod($target, 0644);

        return [
            'requirement_id' => $requirementId,
            'path' => date('Y/m') . '/' . $name,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public static function absolutePath(string $relative): string
    {
        $base = rtrim((string) config('app.upload.path', BASE_PATH . '/storage/uploads'), '/');
        $clean = str_replace(['..', '\\'], '', $relative);

        return $base . '/' . ltrim($clean, '/');
    }
}
