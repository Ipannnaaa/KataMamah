<?php

declare(strict_types=1);

/**
 * Tata letak peta isometrik.
 *
 * Peta digambar sendiri, bukan dari penyedia tile: setiap kecamatan menjadi
 * satu platform melayang, dan landmark berdiri di atasnya. Posisi platform
 * dihitung dari koordinat asli kecamatan, jadi arah barat–timur dan
 * utara–selatan tetap benar meski gayanya ilustratif.
 */

return [
    // Kanvas SVG. Semua angka di bawah memakai satuan ini.
    'width' => 1440,
    'height' => 800,

    // Sisa ruang laut di sekeliling pulau setelah pulau diperbesar otomatis
    // sampai memenuhi kanvas. Makin kecil, makin besar pulaunya.
    'fit_padding' => 54,

    /**
     * Batas wilayah Kabupaten Tuban sebagai [bujur, lintang].
     *
     * SUMBER: OpenStreetMap, relasi 9676649 (batas administratif kabupaten),
     * diambil lewat Nominatim lalu disederhanakan Douglas–Peucker dari 9.103
     * titik menjadi 147 titik (toleransi 0,0029°).
     *
     * LISENSI: data © kontributor OpenStreetMap, ODbL 1.0. Bila peta ini
     * dipublikasikan, atribusi "© OpenStreetMap contributors" wajib
     * ditampilkan — lihat partial peta di app/Views/home/index.php.
     *
     * Penyederhanaan ini memotong detail kecil garis pantai, dan empat
     * poligon kecil lepas pantai tidak disertakan. Untuk bentuk yang lebih
     * rinci, ambil ulang dengan toleransi lebih kecil; tidak ada kode yang
     * perlu diubah karena proyeksi dan pembagian kecamatan menyesuaikan.
     */
    'outline' => [
        [111.5701, -6.9467], [111.5877, -6.9638], [111.6055, -6.9674], [111.6223, -6.9827],
        [111.6241, -6.9898], [111.6526, -7.0002], [111.6601, -7.0392], [111.6718, -7.0406],
        [111.6741, -7.0499], [111.6952, -7.0544], [111.6941, -7.0687], [111.7133, -7.0814],
        [111.7192, -7.0782], [111.7267, -7.0840], [111.7362, -7.0778], [111.7625, -7.0879],
        [111.7749, -7.0820], [111.8165, -7.0872], [111.8242, -7.1004], [111.8566, -7.1151],
        [111.8560, -7.1253], [111.8602, -7.1239], [111.8620, -7.1297], [111.8647, -7.1241],
        [111.8667, -7.1292], [111.8671, -7.1263], [111.8779, -7.1333], [111.8863, -7.1217],
        [111.9003, -7.1288], [111.8992, -7.1363], [111.9112, -7.1380], [111.9207, -7.1321],
        [111.9304, -7.1328], [111.9334, -7.1602], [111.9426, -7.1547], [111.9549, -7.1322],
        [111.9767, -7.1436], [111.9772, -7.1617], [111.9836, -7.1640], [111.9837, -7.1488],
        [111.9937, -7.1276], [111.9947, -7.1149], [112.0049, -7.1306], [112.0104, -7.1303],
        [112.0034, -7.1082], [112.0144, -7.1017], [112.0056, -7.0940], [112.0204, -7.0878],
        [112.0154, -7.0730], [112.0187, -7.0677], [112.0260, -7.0692], [112.0316, -7.0789],
        [112.0435, -7.0709], [112.0468, -7.0750], [112.0398, -7.0784], [112.0415, -7.0821],
        [112.0549, -7.0726], [112.0824, -7.0654], [112.0856, -7.0856], [112.1248, -7.0884],
        [112.1176, -7.0965], [112.1225, -7.1082], [112.1308, -7.0968], [112.1419, -7.1047],
        [112.1516, -7.1010], [112.1461, -7.1070], [112.1579, -7.1105], [112.1662, -7.0978],
        [112.1783, -7.0978], [112.1835, -7.0888], [112.1746, -7.0845], [112.1803, -7.0752],
        [112.1847, -7.0760], [112.1842, -7.0837], [112.2047, -7.0741], [112.2180, -7.0730],
        [112.2159, -7.0450], [112.2223, -7.0373], [112.2033, -7.0295], [112.2146, -7.0094],
        [112.2019, -6.9443], [112.2034, -6.9228], [112.1979, -6.9151], [112.1803, -6.9131],
        [112.1692, -6.8925], [112.1667, -6.8987], [112.1442, -6.8959], [112.1435, -6.9010],
        [112.1436, -6.8950], [112.1410, -6.9017], [112.1234, -6.9026], [112.1050, -6.8949],
        [112.0714, -6.8946], [112.0662, -6.8925], [112.0680, -6.8870], [112.0653, -6.8926],
        [112.0521, -6.8864], [112.0108, -6.8345], [112.0069, -6.8187], [111.9978, -6.8072],
        [112.0031, -6.7984], [111.9973, -6.8070], [111.9944, -6.8042], [112.0017, -6.7969],
        [111.9948, -6.8037], [111.9913, -6.8013], [111.9803, -6.7735], [111.9609, -6.7605],
        [111.9448, -6.7632], [111.9032, -6.7877], [111.8986, -6.7882], [111.8953, -6.7798],
        [111.8985, -6.7883], [111.8876, -6.7926], [111.8825, -6.7805], [111.8843, -6.7940],
        [111.8382, -6.8009], [111.8077, -6.7934], [111.7659, -6.7717], [111.7612, -6.7750],
        [111.7510, -6.7705], [111.7191, -6.7698], [111.6913, -6.7541], [111.6844, -6.7706],
        [111.6772, -6.7709], [111.6755, -6.7750], [111.6698, -6.7639], [111.6602, -6.7801],
        [111.6643, -6.7916], [111.6571, -6.8069], [111.6611, -6.8167], [111.6527, -6.8254],
        [111.6490, -6.8229], [111.6357, -6.8288], [111.6192, -6.8216], [111.6105, -6.8458],
        [111.6146, -6.8626], [111.6095, -6.8710], [111.6151, -6.8819], [111.6000, -6.8928],
        [111.6025, -6.9024], [111.6081, -6.9020], [111.6025, -6.9116], [111.5861, -6.9097],
        [111.5775, -6.9138], [111.5731, -6.9263], [111.5769, -6.9423],
    ],

    // Perbandingan sumbu isometrik. Hanya menentukan kemiringan pandangan;
    // ukuran akhir diatur otomatis oleh fit_padding di atas.
    'spread_x' => 560,
    'spread_y' => 200,

    // Tebal daratan pulau.
    'land_depth' => 58,

    // Penghalusan garis pantai saat digambar (batas aslinya tidak diubah).
    // shore_simplify: tonjolan di bawah sekian piksel dibuang — meander
    // sungai di selatan jadi tidak terbaca sebagai duri.
    // shore_smooth: berapa kali sudutnya dibulatkan (Chaikin).
    // shore_relax: pelemasan Laplacian — menarik tiap titik ke tengah-tengah
    // tetangganya, sehingga teluk dan tanjung besar ikut melandai. Inilah yang
    // paling menentukan "bulat"-nya, tetapi sengaja dimatikan: pada 6 siluetnya
    // terlalu bulat dan tidak lagi terbaca sebagai Tuban.
    'shore_simplify' => 15,
    'shore_relax' => 0,
    'shore_relax_strength' => 0.5,
    'shore_smooth' => 3,

    // Jari-jari lingkaran tempat kantor layanan disusun saat peta diperbesar
    // ke satu kecamatan (tingkat 2). Jangan terlalu lebar: setelah dikalikan
    // zoom_scale, pin terluar bisa keluar bingkai.
    'ring_x' => 128,
    'ring_y' => 62,

    // Perbesaran saat satu kecamatan dipilih. Nilai sebenarnya dihitung per
    // kecamatan agar bidangnya mengisi sekitar zoom_fill bagian layar;
    // zoom_scale hanya dipakai bila bidangnya tidak terbentuk.
    'zoom_scale' => 2.2,
    'zoom_fill' => 0.88,
    'zoom_max' => 6.0,

    // Posisi landmark di atas platform, relatif terhadap titik tengahnya.
    // Diurutkan dari barat ke timur supaya arahnya tetap masuk akal, dan
    // ketinggiannya dibuat berbeda-beda supaya namanya tidak sebaris.
    'slots' => [
        [0, -20],
        [-62, 4],
        [62, 4],
        [0, 30],
        [-96, -14],
        [96, -14],
        [-34, -44],
        [34, -44],
    ],

    // Warna garis batas antar kecamatan di atas pulau.
    'border_color' => '#F2F7F0',
    'border_width' => 1.6,

    // Warna bangunan landmark per kategori.
    'category_colors' => [
        'kecamatan' => '#E8A33D',
        'kelurahan' => '#E8C53D',
        'dukcapil' => '#4C7DF0',
        'puskesmas' => '#E35D6A',
        'rumah_sakit' => '#D64550',
        'samsat' => '#2BB3A3',
        'polsek' => '#3F51B5',
        'pemadam' => '#E04A2F',
        'satpol_pp' => '#5A6E3A',
        'pengadilan' => '#6D4C9F',
        'pengadilan_agama' => '#2E7D6B',
        'kejaksaan' => '#A8761C',
        'kua' => '#0F8A6A',
        'bpjs' => '#1E88A8',
        'pertanahan' => '#7D6A4F',
        'perpustakaan' => '#8E5BA6',
        'pos' => '#C2641F',
        'pdam' => '#1D8FC4',
        'terminal' => '#4D7C8A',
        'sekolah' => '#9575CD',
        'pasar' => '#F08A3C',
        'perizinan' => '#3FA796',
        'dinas' => '#6D8EA0',
        'lainnya' => '#7E8C87',
    ],

    // Label kategori untuk ditampilkan ke pengunjung.
    'category_labels' => [
        'kecamatan' => 'Kantor Kecamatan',
        'kelurahan' => 'Kelurahan / Desa',
        'dukcapil' => 'Dukcapil',
        'puskesmas' => 'Puskesmas',
        'rumah_sakit' => 'Rumah Sakit',
        'samsat' => 'Samsat',
        'polsek' => 'Kepolisian',
        'pemadam' => 'Pemadam Kebakaran',
        'satpol_pp' => 'Satpol PP',
        'pengadilan' => 'Pengadilan Negeri',
        'pengadilan_agama' => 'Pengadilan Agama',
        'kejaksaan' => 'Kejaksaan',
        'kua' => 'KUA',
        'bpjs' => 'BPJS',
        'pertanahan' => 'Pertanahan (BPN)',
        'perpustakaan' => 'Perpustakaan',
        'pos' => 'Kantor Pos',
        'pdam' => 'PDAM',
        'terminal' => 'Terminal',
        'sekolah' => 'Pendidikan',
        'pasar' => 'Pasar',
        'perizinan' => 'Perizinan',
        'dinas' => 'Dinas',
        'lainnya' => 'Lainnya',
    ],
];
