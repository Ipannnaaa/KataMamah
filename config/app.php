<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Tuban Civic Map'),
    'short_name' => env('APP_SHORT_NAME', 'TCM'),
    'wilayah' => env('APP_WILAYAH', 'Tuban'),

    // URL dasar tanpa garis miring di akhir, mis. http://localhost/wia/public
    'url' => env('APP_URL', 'http://localhost'),

    // Sub-folder yang perlu dipotong dari path request bila aplikasi tidak
    // dipasang di root domain. Untuk vhost sendiri, biarkan kosong.
    'base_path' => env('APP_BASE_PATH', ''),

    'debug' => (bool) env('APP_DEBUG', true),
    'https' => (bool) env('APP_HTTPS', false),

    // Lama layar "Mengalihkan…" sebelum pindah sendiri, dalam detik bulat
    // (meta refresh tidak menerima pecahan). 0 atau kurang dibulatkan ke 1.
    'redirect_delay' => 2,

    'timezone' => 'Asia/Jakarta',

    'session_name' => 'wia_session',
    'session_timeout' => 3600, // 60 menit idle

    'upload' => [
        'path' => BASE_PATH . '/storage/uploads',
        'max_bytes' => 3 * 1024 * 1024,
        'mimes' => [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ],
    ],

    /*
     * Foto gedung kantor. Berbeda dari berkas warga di atas: ini gambar
     * publik yang muncul di setiap halaman kantor, jadi disimpan DI DALAM
     * web root supaya disajikan langsung oleh server web beserta cache-nya,
     * bukan lewat PHP tiap kali dibuka.
     *
     * Yang menjaga folder itu tetap aman: ekstensi berkas ditentukan dari
     * tipe hasil sniffing isi berkas (bukan dari nama yang dikirim klien),
     * namanya diacak, dan public/uploads/.htaccess mematikan semua handler
     * skrip di sana.
     */
    'photo' => [
        'path' => BASE_PATH . '/public/uploads/lokasi',
        'url' => '/uploads/lokasi',
        'max_bytes' => 2 * 1024 * 1024,
        'mimes' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        // Foto lebih lebar dari ini dikecilkan — tapi hanya bila ekstensi GD
        // aktif. Tanpa GD berkas disimpan apa adanya; lihat PhotoService.
        'max_width' => 1600,
        'min_width' => 400,
        'quality' => 82,
    ],

    'rate_limit' => [
        'login' => ['max' => 5, 'decay' => 900],
        'track' => ['max' => 10, 'decay' => 600],
        'chat' => ['max' => 20, 'decay' => 60],
        'report' => ['max' => 5, 'decay' => 900],
        // Mengirim ulang tautan verifikasi. Ketat karena tiap percobaan
        // berarti satu email ke alamat yang ditentukan peminta.
        'verify' => ['max' => 3, 'decay' => 900],
    ],

    'queue' => [
        // Awalan kode pemesanan. Mengubahnya tidak memengaruhi kode lama yang
        // sudah terbit; pencarian tetap mengenali keduanya.
        'code_prefix' => env('APP_CODE_PREFIX', 'TCM'),
        'max_days_ahead' => 30,
        'slots' => [
            '08:00' => '08.00 – 09.00',
            '09:00' => '09.00 – 10.00',
            '10:00' => '10.00 – 11.00',
            '11:00' => '11.00 – 12.00',
            '13:00' => '13.00 – 14.00',
            '14:00' => '14.00 – 15.00',
        ],
    ],
];
