<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Foto gedung kantor.
 *
 * Sengaja terpisah dari UploadService. Berkas warga bersifat pribadi dan
 * disimpan di luar web root, sedangkan foto gedung justru harus bisa dilihat
 * siapa saja — menyajikannya lewat PHP tiap kali halaman dibuka hanya
 * memperlambat tanpa menambah keamanan apa pun.
 *
 * Yang menggantikan perlindungan itu:
 *
 *  1. tipe berkas dibaca dari isinya (finfo), bukan dari nama atau header klien;
 *  2. nama berkas diacak dan ekstensinya diambil dari daftar yang diizinkan,
 *     jadi tidak ada cara menaruh berkas .php di sana;
 *  3. gambar diperiksa getimagesize() — berkas yang bukan gambar sungguhan
 *     ditolak meski tipenya mengaku image/*;
 *  4. bila ekstensi GD aktif, gambar dikodekan ulang sehingga apa pun yang
 *     menumpang di dalamnya (termasuk EXIF) hilang;
 *  5. public/uploads/.htaccess mematikan seluruh handler skrip di folder itu.
 */
final class PhotoService
{
    /**
     * Menyimpan satu foto dan mengembalikan nama berkasnya (tanpa folder).
     *
     * @param array<string, mixed> $file satu entri dari $_FILES
     */
    public static function store(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Tidak ada foto yang dipilih.');
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Foto terlalu besar untuk diunggah. Maksimal ' . self::maxLabel() . '.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Foto gagal diunggah (kode ' . $error . ').');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Berkas tidak dikenali sebagai unggahan yang sah.');
        }

        $max = (int) config('app.photo.max_bytes', 2097152);
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > $max) {
            throw new RuntimeException('Ukuran foto melebihi ' . self::maxLabel() . '.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $allowed = (array) config('app.photo.mimes', []);

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Tipe berkas "' . $mime . '" bukan foto. Gunakan JPG, PNG, atau WebP.');
        }

        // Tipe yang benar belum tentu gambar yang benar: berkas rusak atau
        // yang hanya meniru header akan gagal di sini.
        $dimensi = @getimagesize($tmp);

        if ($dimensi === false) {
            throw new RuntimeException('Berkas tidak dapat dibaca sebagai gambar.');
        }

        $minWidth = (int) config('app.photo.min_width', 400);

        if ((int) $dimensi[0] < $minWidth) {
            throw new RuntimeException(
                'Foto terlalu kecil (' . $dimensi[0] . ' px). Lebar minimal ' . $minWidth . ' px.'
            );
        }

        $dir = self::directory();
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $name;

        if (!self::reencode($tmp, $target, $mime, (int) $dimensi[0], (int) $dimensi[1])
            && !move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Foto gagal dipindahkan ke penyimpanan.');
        }

        @chmod($target, 0644);

        return $name;
    }

    /**
     * Menghapus foto lama. Nama berkas dari basis data tidak dipercaya
     * begitu saja: hanya nama acak buatan store() yang akan cocok.
     */
    public static function delete(?string $name): void
    {
        if ($name === null || $name === '' || !self::isOwnName($name)) {
            return;
        }

        $path = self::directory() . '/' . $name;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** URL publik foto, atau null bila kantor belum berfoto. */
    public static function url(?string $name): ?string
    {
        if ($name === null || $name === '' || !self::isOwnName($name)) {
            return null;
        }

        return url(rtrim((string) config('app.photo.url', '/uploads/lokasi'), '/') . '/' . $name);
    }

    /**
     * Bentuk nama yang sah: 32 huruf heksadesimal + ekstensi yang diizinkan.
     * Apa pun selain itu — path, nama asli, sisa data lama — ditolak, jadi
     * tidak ada jalan keluar dari folder foto.
     */
    public static function isOwnName(string $name): bool
    {
        $ext = implode('|', array_map('preg_quote', array_values((array) config('app.photo.mimes', []))));

        return $ext !== '' && preg_match('/^[0-9a-f]{32}\.(' . $ext . ')$/', $name) === 1;
    }

    /**
     * Mengodekan ulang gambar ke ukuran wajar. Mengembalikan false bila GD
     * tidak tersedia atau formatnya tidak didukung — penyimpan lalu memakai
     * berkas aslinya, yang tetap sudah lolos pemeriksaan tipe dan ukuran.
     */
    private static function reencode(string $tmp, string $target, string $mime, int $width, int $height): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $sumber = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default => false,
        };

        if (!$sumber instanceof \GdImage) {
            return false;
        }

        $maxWidth = (int) config('app.photo.max_width', 1600);

        if ($width > $maxWidth) {
            $tinggi = (int) round($height * ($maxWidth / $width));
            $kecil = imagecreatetruecolor($maxWidth, $tinggi);

            // PNG dan WebP bisa punya bagian tembus pandang; tanpa dua baris
            // ini latarnya berubah jadi hitam setelah diperkecil.
            imagealphablending($kecil, false);
            imagesavealpha($kecil, true);
            imagecopyresampled($kecil, $sumber, 0, 0, 0, 0, $maxWidth, $tinggi, $width, $height);
            imagedestroy($sumber);
            $sumber = $kecil;
        }

        $quality = (int) config('app.photo.quality', 82);

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($sumber, $target, $quality),
            'image/png' => imagepng($sumber, $target, 6),
            'image/webp' => function_exists('imagewebp') && imagewebp($sumber, $target, $quality),
            default => false,
        };

        imagedestroy($sumber);

        return $ok && is_file($target);
    }

    private static function directory(): string
    {
        $dir = rtrim((string) config('app.photo.path', BASE_PATH . '/public/uploads/lokasi'), '/');

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
        }

        return $dir;
    }

    private static function maxLabel(): string
    {
        return round(((int) config('app.photo.max_bytes', 2097152)) / 1048576, 1) . ' MB';
    }
}
