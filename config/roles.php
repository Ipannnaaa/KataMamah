<?php

declare(strict_types=1);

/**
 * Peran dan status antrean dikumpulkan di satu tempat agar label di view,
 * aturan transisi di QueueService, dan ENUM di basis data tidak berbeda.
 */
return [
    'list' => [
        'user' => 'Warga',
        'officer' => 'Petugas',
        'admin' => 'Admin lokasi',
        'superadmin' => 'Super admin',
    ],

    // Peran yang terikat pada satu lokasi dan wajib punya location_id.
    'location_bound' => ['officer', 'admin'],

    'home' => [
        'user' => '/akun',
        'officer' => '/petugas',
        'admin' => '/admin',
        'superadmin' => '/admin',
    ],

    'status' => [
        'baru' => 'Antrean baru',
        'ditinjau' => 'Ditinjau',
        'ditangani' => 'Ditangani',
        'selesai' => 'Telah selesai',
        'dibatalkan' => 'Dibatalkan',
    ],

    // Transisi yang diizinkan: status sekarang => status tujuan yang sah.
    'transitions' => [
        'baru' => ['ditinjau', 'dibatalkan'],
        'ditinjau' => ['ditangani', 'dibatalkan'],
        'ditangani' => ['selesai', 'dibatalkan'],
        'selesai' => [],
        'dibatalkan' => [],
    ],

    // Peran yang boleh melakukan transisi ke status tertentu.
    'transition_roles' => [
        'ditinjau' => ['officer', 'admin'],
        'ditangani' => ['officer', 'admin'],
        'selesai' => ['officer', 'admin'],
        'dibatalkan' => ['user', 'officer', 'admin'],
    ],

    'payment_status' => [
        'tidak_perlu' => 'Tidak perlu',
        // Tarifnya belum dikonfirmasi ke instansi, jadi belum bisa dikatakan
        // gratis maupun berbayar. Lihat services.fee yang bernilai NULL.
        'belum_pasti' => 'Belum dipastikan',
        'menunggu' => 'Menunggu',
        'lunas' => 'Lunas',
        'gagal' => 'Gagal',
    ],
];
