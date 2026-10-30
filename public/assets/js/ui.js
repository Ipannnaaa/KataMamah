/**
 * ui.js — perilaku kecil yang dipakai semua halaman.
 * Tidak ada dependensi; aman bila gagal dimuat (halaman tetap berfungsi).
 */

/* Reveal saat elemen masuk layar. Kelas .reveal diatur di anim.css. */
const revealables = document.querySelectorAll('.reveal');

if (revealables.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });

    revealables.forEach((el) => observer.observe(el));
} else {
    revealables.forEach((el) => el.classList.add('is-in'));
}

/* Flash sukses hilang sendiri; flash error dibiarkan agar terbaca. */
document.querySelectorAll('.flash--ok').forEach((flash) => {
    window.setTimeout(() => {
        flash.style.transition = 'opacity 400ms';
        flash.style.opacity = '0';
        window.setTimeout(() => flash.remove(), 400);
    }, 6000);
});

/* Input kode pemesanan: huruf besar dan format PREFIX-XXXXX saat diketik. */
const codeInput = document.querySelector('.input-code');

if (codeInput instanceof HTMLInputElement) {
    const prefix = (codeInput.dataset.prefix || 'TCM').toUpperCase();

    codeInput.addEventListener('input', () => {
        let clean = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        let used = prefix;

        // Awalan lama tetap diterima agar kode yang sudah beredar bisa dilacak.
        for (const known of [prefix, 'WIA', 'TCM']) {
            if (clean.startsWith(known)) {
                used = known;
                clean = clean.slice(known.length);
                break;
            }
        }

        codeInput.value = clean === '' ? '' : used + '-' + clean.slice(0, 5);
    });
}
