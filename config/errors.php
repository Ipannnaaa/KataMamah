<?php

declare(strict_types=1);

/**
 * Teks halaman error per kode status.
 *
 * Dipisah ke sini supaya judul di <title>, judul di halaman, dan keterangannya
 * selalu sama — ketiganya dibaca lewat helper error_info(). Menambah kode baru
 * cukup menambah satu entri; tidak ada kode PHP yang perlu diubah.
 *
 * judul : dipakai di <title> dan sebagai judul besar halaman
 * ket   : apa yang terjadi
 * saran : apa langkah berikutnya bagi pengunjung
 */

return [
    'default' => [
        'judul' => 'Permintaan tidak dapat diproses',
        'ket' => 'Terjadi sesuatu yang tidak kami perkirakan.',
        'saran' => 'Coba ulangi, atau kembali ke peta layanan.',
    ],

    400 => [
        'judul' => 'Permintaan tidak dipahami',
        'ket' => 'Ada bagian dari permintaan yang tidak lengkap atau salah bentuk.',
        'saran' => 'Coba ulangi dari halaman sebelumnya.',
    ],

    403 => [
        'judul' => 'Anda tidak berhak membuka ini',
        'ket' => 'Halaman ini ada, tetapi di luar wilayah atau peran akun Anda.',
        'saran' => 'Kalau merasa seharusnya bisa, hubungi admin lokasi Anda.',
    ],

    404 => [
        'judul' => 'Halaman ini tidak ada di peta',
        'ket' => 'Alamatnya mungkin salah ketik, atau halamannya sudah dipindahkan.',
        'saran' => 'Coba cari layanannya lewat peta atau kotak pencarian.',
    ],

    405 => [
        'judul' => 'Cara pengiriman tidak didukung',
        'ket' => 'Halaman ini tidak menerima permintaan dengan cara tersebut.',
        'saran' => 'Kembali ke halaman sebelumnya lalu ulangi lewat tombol yang tersedia.',
    ],

    419 => [
        'judul' => 'Halaman kedaluwarsa',
        'ket' => 'Formulir dibiarkan terbuka terlalu lama, jadi tanda pengamannya sudah tidak berlaku.',
        'saran' => 'Muat ulang halamannya, lalu isi dan kirim sekali lagi.',
    ],

    429 => [
        'judul' => 'Terlalu banyak percobaan',
        'ket' => 'Permintaan dari perangkat ini dibatasi sementara untuk mencegah penyalahgunaan.',
        'saran' => 'Tunggu beberapa menit, lalu coba lagi.',
    ],

    500 => [
        'judul' => 'Ada yang salah di sisi kami',
        'ket' => 'Kesalahan ini sudah dicatat untuk diperiksa. Bukan karena kesalahan Anda.',
        'saran' => 'Coba beberapa saat lagi. Kalau antrean Anda sudah terdaftar, kodenya tetap aman.',
    ],

    503 => [
        'judul' => 'Layanan sedang tidak tersedia',
        'ket' => 'Sistem sedang dalam perawatan atau sedang sangat sibuk.',
        'saran' => 'Coba lagi beberapa saat lagi.',
    ],
];
