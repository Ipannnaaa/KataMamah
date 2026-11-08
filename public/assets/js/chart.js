/**
 * Interaksi untuk grafik di panel: tooltip dan tombol seri.
 *
 * Grafiknya sendiri sudah digambar penuh di server. Berkas ini hanya
 * menambah lapisan di atasnya, jadi kalau JavaScript mati atau gagal
 * dimuat, grafiknya tetap tampil dan angkanya tetap bisa dibaca dari tabel
 * di bawahnya.
 */

const sasaran = document.querySelectorAll('[data-judul][data-isi]');

if (sasaran.length > 0) {
    const balon = document.createElement('div');
    balon.className = 'balon';
    balon.hidden = true;
    balon.setAttribute('role', 'tooltip');
    document.body.appendChild(balon);

    // Seri yang sedang dimatikan lewat tombol di kepala grafik. Barisnya
    // ikut hilang dari tooltip supaya angkanya tidak menyesatkan.
    const dimatikan = new Set();

    const susun = (judul, isi) => {
        const baris = isi.split('|').filter((b) => {
            const cocok = b.match(/^([a-z_]*):/);
            return cocok === null || cocok[1] === '' || !dimatikan.has(cocok[1]);
        });

        balon.textContent = '';

        const kepala = document.createElement('strong');
        kepala.textContent = judul;
        balon.appendChild(kepala);

        baris.forEach((b) => {
            const span = document.createElement('span');
            span.textContent = b.replace(/^[a-z_]*:/, '');
            balon.appendChild(span);
        });
    };

    const letakkan = (event) => {
        const lebar = balon.offsetWidth;
        const tinggi = balon.offsetHeight;

        // Dijaga supaya tidak keluar layar: di dekat tepi kanan balonnya
        // dipindah ke kiri kursor, di dekat tepi atas dipindah ke bawahnya.
        let x = event.clientX + 14;
        let y = event.clientY - tinggi - 12;

        if (x + lebar > window.innerWidth - 8) {
            x = event.clientX - lebar - 14;
        }

        if (y < 8) {
            y = event.clientY + 18;
        }

        balon.style.left = `${Math.max(8, x)}px`;
        balon.style.top = `${y}px`;
    };

    const sembunyikan = () => {
        balon.hidden = true;
        document.querySelectorAll('.is-disorot').forEach((el) => el.classList.remove('is-disorot'));
    };

    sasaran.forEach((el) => {
        el.addEventListener('pointerenter', (event) => {
            susun(el.dataset.judul || '', el.dataset.isi || '');
            balon.hidden = false;
            letakkan(event);
            el.classList.add('is-disorot');
        });

        el.addEventListener('pointermove', letakkan);
        el.addEventListener('pointerleave', sembunyikan);
    });

    // Menggulir halaman tidak memicu pointerleave di semua peramban, jadi
    // balonnya bisa tertinggal mengambang di tempat yang salah.
    window.addEventListener('scroll', sembunyikan, { passive: true });

    document.querySelectorAll('.grafik__kunci .kunci').forEach((tombol) => {
        tombol.addEventListener('click', () => {
            const kunci = tombol.dataset.deret;

            if (!kunci) {
                return;
            }

            const aktif = tombol.getAttribute('aria-pressed') === 'true';
            tombol.setAttribute('aria-pressed', aktif ? 'false' : 'true');

            if (aktif) {
                dimatikan.add(kunci);
            } else {
                dimatikan.delete(kunci);
            }

            const grafik = tombol.closest('.grafik');

            if (grafik === null) {
                return;
            }

            grafik.querySelectorAll(`.grafik__deret[data-deret="${CSS.escape(kunci)}"]`)
                .forEach((g) => g.classList.toggle('is-mati', aktif));
        });
    });
}
