<?php

declare(strict_types=1);

/**
 * Pemasang lewat peramban.
 *
 * Isi basis data dibuat oleh database/seed.php, yang memang skrip baris
 * perintah. Di hosting bersama akses baris perintah sering tidak ada, jadi
 * berkas ini menjembatani: membuat tabel dari database/schema.sql lalu
 * menjalankan seeder, dari halaman biasa.
 *
 * Berkas ini duduk di dalam web root, artinya alamatnya bisa dibuka siapa
 * saja, dan yang dikerjakannya menghapus seluruh isi basis data. Karena itu:
 *
 *   1. Ia mati total selama INSTALL_KEY di .env kosong. Itu keadaan bawaan.
 *   2. Kunci tidak pernah lewat URL, supaya tidak ikut tercatat di log
 *      akses server.
 *   3. Semua yang mengubah data lewat POST, jadi tidak bisa terpicu oleh
 *      tautan atau perayap.
 *
 * HAPUS BERKAS INI setelah pemasangan selesai.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\DB;

$kunciAsli = (string) env('INSTALL_KEY', '');

// Tanpa kunci di .env, berkas ini berpura-pura tidak ada.
if (strlen($kunciAsli) < 16) {
    http_response_code(404);
    exit('Not Found');
}

$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$kunciDikirim = (string) ($_POST['kunci'] ?? '');
$sah = $kunciDikirim !== '' && hash_equals($kunciAsli, $kunciDikirim);

if ($post && !$sah) {
    usleep(300000);
}

$aksi = $sah ? (string) ($_POST['aksi'] ?? '') : '';
$pesan = [];
$galat = [];

/* ------------------------------------------------------------- keadaan */

$tersambung = false;
$jumlahTabel = 0;
$jumlahKecamatan = null;

try {
    $jumlahTabel = (int) DB::column(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
    );
    $tersambung = true;

    if ($jumlahTabel > 0) {
        $jumlahKecamatan = (int) DB::column('SELECT COUNT(*) FROM districts');
    }
} catch (Throwable $e) {
    $galat[] = 'Tidak bisa menyambung ke basis data: ' . $e->getMessage();
    $galat[] = 'Periksa DB_NAME, DB_USER, dan DB_PASS di .env. Basis datanya '
        . 'sendiri harus sudah dibuat lewat menu MySQL Databases di cPanel, '
        . 'karena pengguna MySQL di hosting bersama tidak boleh membuat basis data.';
}

/* -------------------------------------------------------------- tindakan */

set_time_limit(300);
ignore_user_abort(true);

if ($aksi === 'tabel' && $tersambung) {
    try {
        $berkas = BASE_PATH . '/database/schema.sql';
        $sql = (string) file_get_contents($berkas);

        DB::pdo()->exec($sql);

        $jumlahTabel = (int) DB::column(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        $jumlahKecamatan = (int) DB::column('SELECT COUNT(*) FROM districts');

        $pesan[] = 'Tabel dibuat ulang dari database/schema.sql. Sekarang ada '
            . $jumlahTabel . ' tabel, semuanya kosong.';
    } catch (Throwable $e) {
        $galat[] = 'Gagal membuat tabel: ' . $e->getMessage();
    }
}

if ($aksi === 'isi' && $tersambung) {
    $kosongkan = isset($_POST['kosongkan']);

    if ($jumlahTabel === 0) {
        $galat[] = 'Tabelnya belum ada. Jalankan langkah pertama dulu.';
    } elseif ($jumlahKecamatan > 0 && !$kosongkan) {
        $galat[] = 'Basis data sudah berisi ' . $jumlahKecamatan . ' kecamatan. '
            . 'Centang "kosongkan dulu" kalau memang ingin menimpa seluruh isinya.';
    } else {
        /*
         * seed.php membaca $argv dan menulis ke STDERR, dua hal yang cuma ada
         * di baris perintah. Keduanya disiapkan di sini, di lingkup global,
         * supaya skrip itu bisa dipakai apa adanya tanpa disalin ulang.
         */
        $argv = $kosongkan ? ['seed.php', '--fresh'] : ['seed.php'];
        $argc = count($argv);

        if (!defined('STDERR')) {
            define('STDERR', fopen('php://output', 'wb'));
        }

        try {
            ob_start();
            require BASE_PATH . '/database/seed.php';
            $keluaran = (string) ob_get_clean();

            $pesan[] = $keluaran;
            $jumlahKecamatan = (int) DB::column('SELECT COUNT(*) FROM districts');
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $galat[] = 'Gagal mengisi data: ' . $e->getMessage();
        }
    }
}

$selesai = $tersambung && $jumlahTabel > 0 && ($jumlahKecamatan ?? 0) > 0;

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Pasang Tuban Civic Map</title>
<style>
    :root { color-scheme: light; }
    body {
        margin: 0; padding: 32px 16px;
        font: 16px/1.6 system-ui, "Segoe UI", sans-serif;
        color: #15201c; background: #f4f6f5;
    }
    main { max-width: 640px; margin: 0 auto; }
    h1 { font-size: 1.4rem; margin: 0 0 4px; }
    .sub { color: #5a6761; margin: 0 0 24px; }
    section {
        background: #fff; border: 1px solid #dfe5e2; border-radius: 10px;
        padding: 20px; margin-bottom: 16px;
    }
    h2 { font-size: 1rem; margin: 0 0 12px; }
    dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 16px; margin: 0; }
    dt { color: #5a6761; }
    dd { margin: 0; font-variant-numeric: tabular-nums; }
    button {
        font: inherit; padding: 9px 18px; border-radius: 7px; cursor: pointer;
        border: 1px solid #0e6b5c; background: #0e6b5c; color: #fff;
    }
    button.sekunder { background: #fff; color: #0e6b5c; }
    input[type=password] {
        font: inherit; padding: 9px 12px; border: 1px solid #c7d0cc;
        border-radius: 7px; width: 100%; box-sizing: border-box;
    }
    label.centang { display: block; margin: 10px 0; color: #5a6761; }
    pre {
        background: #f4f6f5; border-radius: 7px; padding: 12px;
        overflow-x: auto; margin: 0; font-size: .85rem; white-space: pre-wrap;
    }
    .baik { border-left: 4px solid #0e6b5c; }
    .buruk { border-left: 4px solid #b03b2e; }
    .buruk p:first-of-type { margin-top: 0; }
    .ingat { background: #fff6e8; border-color: #e8c89a; }
    code { background: #eef1f0; padding: 1px 5px; border-radius: 4px; font-size: .9em; }
</style>
</head>
<body>
<main>

<h1>Pasang Tuban Civic Map</h1>
<p class="sub">Membuat tabel dan mengisi datanya, untuk hosting yang tidak punya akses baris perintah.</p>

<?php if (!$sah): ?>

    <section>
        <h2>Masukkan kunci pemasangan</h2>
        <p>Kuncinya ada di baris <code>INSTALL_KEY</code> pada berkas <code>.env</code> di server.</p>
        <?php if ($post): ?>
            <p style="color:#b03b2e">Kunci tidak cocok.</p>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="kunci" autocomplete="off" autofocus>
            <p><button type="submit">Lanjut</button></p>
        </form>
    </section>

<?php else: ?>

    <?php foreach ($galat as $g): ?>
        <section class="buruk"><p><?= e($g) ?></p></section>
    <?php endforeach; ?>

    <?php foreach ($pesan as $p): ?>
        <section class="baik"><pre><?= e(trim($p)) ?></pre></section>
    <?php endforeach; ?>

    <section>
        <h2>Keadaan sekarang</h2>
        <dl>
            <dt>Basis data</dt>
            <dd><?= e((string) config('database.name')) ?><?= $tersambung ? ' (tersambung)' : ' (gagal)' ?></dd>
            <dt>Tabel</dt>
            <dd><?= $jumlahTabel ?></dd>
            <dt>Kecamatan terisi</dt>
            <dd><?= $jumlahKecamatan === null ? 'belum ada tabelnya' : $jumlahKecamatan ?></dd>
        </dl>
    </section>

    <?php if ($tersambung): ?>

        <section>
            <h2>1. Buat tabel</h2>
            <p>Menjalankan <code>database/schema.sql</code>. Berkas itu menghapus
            tabel lama lebih dulu, jadi seluruh isi basis data ini hilang.</p>
            <form method="post" onsubmit="return confirm('Seluruh isi basis data ini akan hilang. Lanjutkan?')">
                <input type="hidden" name="kunci" value="<?= e($kunciDikirim) ?>">
                <input type="hidden" name="aksi" value="tabel">
                <button type="submit" class="sekunder">Buat tabel</button>
            </form>
        </section>

        <section>
            <h2>2. Isi data</h2>
            <p>Menjalankan <code>database/seed.php</code>: 20 kecamatan, 132 kantor
            layanan, 631 layanan, beserta akun dan antrean contoh.</p>
            <form method="post" onsubmit="return confirm('Jalankan seeder sekarang?')">
                <input type="hidden" name="kunci" value="<?= e($kunciDikirim) ?>">
                <input type="hidden" name="aksi" value="isi">
                <label class="centang">
                    <input type="checkbox" name="kosongkan"> kosongkan dulu isinya
                </label>
                <button type="submit" class="sekunder">Isi data</button>
            </form>
        </section>

    <?php endif; ?>

    <?php if ($selesai): ?>
        <section class="ingat">
            <h2>Sudah selesai? Hapus berkas ini</h2>
            <p>Selama <code>public/pasang.php</code> masih ada di server, siapa pun
            yang tahu kuncinya bisa mengosongkan basis data. Hapus berkasnya lewat
            File Manager, atau kosongkan lagi <code>INSTALL_KEY</code> di
            <code>.env</code>.</p>
        </section>
    <?php endif; ?>

<?php endif; ?>

</main>
</body>
</html>
