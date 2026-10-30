<?php

/**
 * Arah geser transisi antar halaman, dan penanda bila peramban tidak
 * mendukung View Transitions sama sekali.
 *
 * Harus berupa skrip inline di <head>, bukan bagian dari ui.js: event
 * pagereveal menyala sangat awal di dokumen baru, kerap mendahului modul yang
 * dimuat di akhir <body>. Kalau terlambat, atributnya tidak sempat terpasang
 * dan arah gesernya salah — justru pada tombol kembali, satu-satunya saat
 * arah itu penting.
 *
 * Yang menentukan animasi adalah gaya dokumen BARU, jadi pagereveal yang
 * menentukan; pageswap ikut diatur agar dokumen lama memotret dirinya dengan
 * arah yang sama. Tanpa JavaScript, atribut ini tidak pernah ada dan CSS
 * memakai arah maju — sudah benar untuk klik tautan biasa.
 */

?>
<script>
(function () {
    // Peramban tanpa View Transitions memakai animasi masuk biasa.
    // Penandanya dipasang di sini, sebelum gambar pertama, supaya
    // animasinya tidak sempat berkedip lebih dulu.
    if (!('startViewTransition' in document)) {
        document.documentElement.classList.add('tanpa-transisi');
    }

    var arahkan = function (jenis) {
        document.documentElement.dataset.arah = jenis === 'traverse' ? 'mundur' : 'maju';
    };

    addEventListener('pagereveal', function (e) {
        if (e.viewTransition) {
            arahkan(window.navigation && navigation.activation && navigation.activation.navigationType);
        }
    });

    addEventListener('pageswap', function (e) {
        if (e.viewTransition) {
            arahkan(e.activation && e.activation.navigationType);
        }
    });
})();
</script>
