/**
 * map.js — peta isometrik dua tingkat.
 *
 * Tingkat 1 menampilkan seluruh kabupaten dengan penanda kecamatan. Memilih
 * satu kecamatan memperbesar peta ke titik itu dan memunculkan kantor-kantor
 * layanannya. Mengarahkan kursor ke sebuah kantor memunculkan kartu pratinjau.
 *
 * Tanpa berkas ini halaman tetap bisa dipakai: penanda kecamatan dan kantor
 * adalah tautan biasa.
 */

const peta = document.getElementById('peta');

if (peta) {
    const world = document.getElementById('iso-world');
    const preview = document.getElementById('iso-preview');
    const back = document.getElementById('iso-back');
    const desaToggle = document.getElementById('iso-desa');
    const scope = document.getElementById('iso-scope');
    const hint = document.getElementById('iso-hint');

    const kecs = [...peta.querySelectorAll('.iso-kec')];
    const marks = [...peta.querySelectorAll('.iso-mark')];
    const tiles = [...peta.querySelectorAll('.iso-cell')];
    const villages = [...peta.querySelectorAll('.iso-villages')];

    const fallbackZoom = Number(peta.dataset.zoom || 2.2);
    let current = '';
    let hideTimer = null;

    kecs.forEach((kec, index) => {
        kec.style.setProperty('--delay', index * 30 + 'ms');
        kec.addEventListener('click', (event) => {
            event.preventDefault();
            enter(kec.dataset.district);
        });
    });

    marks.forEach((mark) => {
        mark.addEventListener('mouseenter', () => show(mark));
        mark.addEventListener('focus', () => show(mark));
        mark.addEventListener('mouseleave', scheduleHide);
        mark.addEventListener('blur', scheduleHide);
    });

    if (preview) {
        preview.addEventListener('mouseenter', () => window.clearTimeout(hideTimer));
        preview.addEventListener('mouseleave', scheduleHide);
    }

    if (back) {
        back.addEventListener('click', exit);
    }

    if (desaToggle) {
        desaToggle.addEventListener('click', () => {
            const on = peta.classList.toggle('show-desa');
            desaToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (preview && !preview.hidden) {
            hide();
        } else if (current !== '') {
            exit();
        }
    });

    // Kecamatan yang dibuka langsung lewat /kecamatan/{slug}.
    if (peta.dataset.active) {
        enter(peta.dataset.active, false);
    }

    function enter(slug, animate = true) {
        const kec = kecs.find((k) => k.dataset.district === slug);

        if (!kec) {
            return;
        }

        current = slug;
        hide();

        const left = parseFloat(kec.style.left);
        const top = parseFloat(kec.style.top);

        if (!animate) {
            world.style.transition = 'none';
            window.requestAnimationFrame(() => {
                world.style.transition = '';
            });
        }

        // Tiap kecamatan punya tingkat perbesarannya sendiri, dihitung dari
        // luas bidangnya, supaya yang sempit tidak membuat pin bertumpuk.
        const zoom = Number(kec.dataset.zoom) || fallbackZoom;

        // Label dikecilkan balik dengan angka yang sama agar tetap terbaca.
        peta.style.setProperty('--zoom', String(zoom));

        // Diperbesar dari titik tengah panggung, lalu digeser supaya kecamatan
        // yang dipilih berhenti tepat di tengah — bukan di tepi layar.
        world.style.transformOrigin = '50% 50%';
        world.style.transform = 'scale(' + zoom + ') translate(' + (50 - left) + '%, ' + (50 - top) + '%)';

        peta.classList.add('is-zoomed');
        tiles.forEach((tile) => tile.classList.toggle('is-shown', tile.dataset.district === slug));
        villages.forEach((group) => group.classList.toggle('is-shown', group.dataset.district === slug));
        marks.forEach((mark) => mark.classList.toggle('is-shown', mark.dataset.district === slug));
        kecs.forEach((k) => k.classList.toggle('is-faded', k.dataset.district !== slug));

        if (back) {
            back.hidden = false;
        }

        // Tombol nama desa hanya berguna bila kecamatan ini punya datanya.
        if (desaToggle) {
            desaToggle.hidden = !villages.some((g) => g.dataset.district === slug);
        }

        if (scope) {
            scope.textContent = 'Kecamatan ' + kec.dataset.name + ' · ' + kec.dataset.places + ' kantor';
        }

        if (hint) {
            hint.textContent = 'Arahkan kursor ke sebuah kantor untuk melihat layanan yang tersedia.';
        }
    }

    function exit() {
        current = '';
        hide();

        // Kembali ke ukuran normal: pin dan label tidak lagi dikecilkan balik.
        peta.style.setProperty('--zoom', '1');
        world.style.transform = '';
        peta.classList.remove('is-zoomed');
        tiles.forEach((tile) => tile.classList.remove('is-shown'));
        villages.forEach((group) => group.classList.remove('is-shown'));
        marks.forEach((mark) => mark.classList.remove('is-shown'));
        kecs.forEach((k) => k.classList.remove('is-faded'));

        if (back) {
            back.hidden = true;
        }

        if (desaToggle) {
            desaToggle.hidden = true;
            desaToggle.setAttribute('aria-pressed', 'false');
        }

        peta.classList.remove('show-desa');

        if (scope) {
            scope.textContent = kecs.length + ' kecamatan';
        }

        if (hint) {
            hint.textContent = 'Klik sebuah kecamatan untuk memperbesar dan melihat kantor layanan di dalamnya.';
        }
    }

    function scheduleHide() {
        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(hide, 220);
    }

    function hide() {
        window.clearTimeout(hideTimer);

        if (preview) {
            preview.hidden = true;
        }

        marks.forEach((mark) => mark.classList.remove('is-active'));
    }

    function show(mark) {
        if (!preview) {
            return;
        }

        window.clearTimeout(hideTimer);
        marks.forEach((other) => other.classList.toggle('is-active', other === mark));

        const data = mark.dataset;
        const services = (data.services || '').split('|').filter(Boolean);
        const extra = Number(data.serviceCount || 0) - services.length;

        preview.innerHTML = '';
        preview.hidden = false;

        // Foto gedung bila admin sudah mengunggahnya; kalau belum, ikon
        // kategori yang sama dengan pin di peta sebagai penggantinya.
        const figure = document.createElement('div');
        figure.className = 'iso-preview__photo';

        if (data.photo) {
            const img = document.createElement('img');
            img.src = data.photo;
            img.alt = 'Gedung ' + data.name;
            img.loading = 'lazy';
            img.decoding = 'async';
            figure.classList.add('iso-preview__photo--real');
            figure.append(img);
        } else {
            figure.style.background = tint(data.color);
            figure.style.color = data.color;

            const icon = mark.querySelector('.iso-pin__icon svg');

            if (icon) {
                const copy = icon.cloneNode(true);
                copy.setAttribute('width', '58');
                copy.setAttribute('height', '58');
                copy.setAttribute('stroke-width', '1.4');
                figure.append(copy);
            }
        }

        const badge = document.createElement('span');
        badge.className = 'chip';
        badge.textContent = data.categoryLabel;

        const title = document.createElement('h3');
        title.textContent = data.name;

        const addr = document.createElement('p');
        addr.className = 'iso-preview__addr';
        addr.textContent = data.address || data.desc;

        const list = document.createElement('ul');
        list.className = 'iso-preview__services';

        services.forEach((name) => {
            const li = document.createElement('li');
            li.textContent = name;
            list.append(li);
        });

        if (extra > 0) {
            const li = document.createElement('li');
            li.className = 'is-more';
            li.textContent = '+' + extra + ' layanan lain';
            list.append(li);
        }

        const quota = document.createElement('p');
        quota.className = 'iso-preview__quota';
        quota.textContent = 'Kuota hari ini: ' + data.quota + ' tersisa';

        const actions = document.createElement('div');
        actions.className = 'iso-preview__actions';

        const go = document.createElement('a');
        go.className = 'btn btn--primary btn--small';
        go.href = data.maps;
        go.target = '_blank';
        go.rel = 'noopener noreferrer';
        go.textContent = 'Go · Google Maps';

        const detail = document.createElement('a');
        detail.className = 'btn btn--ghost btn--small';
        detail.href = mark.getAttribute('href');
        detail.textContent = 'Halaman lengkap';

        actions.append(go, detail);
        preview.append(figure, badge, title, addr, list, quota, actions);

        place(mark);
    }

    /** Kartu diletakkan dekat kantor, tetapi tidak boleh keluar dari bingkai peta. */
    function place(mark) {
        const box = peta.getBoundingClientRect();
        const spot = mark.getBoundingClientRect();
        const card = preview.getBoundingClientRect();

        let left = spot.left - box.left + spot.width / 2 - card.width / 2;
        let top = spot.top - box.top - card.height - 14;

        if (top < 8) {
            top = spot.top - box.top + spot.height + 14;
        }

        left = Math.max(8, Math.min(left, box.width - card.width - 8));
        top = Math.max(8, Math.min(top, box.height - card.height - 8));

        preview.style.left = left + 'px';
        preview.style.top = top + 'px';
    }

    function tint(hex) {
        return 'linear-gradient(160deg, ' + hex + '1f, ' + hex + '3d)';
    }

    /* ------------------------------------------- fokus ke satu jenis fasilitas
     *
     * Legenda sekaligus penyaring. Berbeda dengan memperbesar ke satu
     * kecamatan, mode ini memunculkan seluruh fasilitas sejenis di satu
     * kabupaten sekaligus — pertanyaannya bukan "apa saja di kecamatan ini",
     * melainkan "di mana saja puskesmas".
     */
    const legenda = document.getElementById('iso-legend');
    let jenis = '';

    if (legenda) {
        legenda.addEventListener('click', (event) => {
            const tombol = event.target.closest('.iso-legend__btn');

            if (!tombol) {
                return;
            }

            // Menekan jenis yang sedang aktif mematikannya lagi.
            saring(tombol.dataset.filter === jenis ? '' : tombol.dataset.filter);
        });
    }

    function saring(kategori) {
        jenis = kategori;

        // Fokus jenis dan perbesaran kecamatan tidak bisa aktif bersamaan:
        // keduanya menentukan pin mana yang terlihat.
        if (kategori !== '') {
            exit();
        }

        peta.classList.toggle('is-filtered', kategori !== '');
        marks.forEach((mark) => {
            mark.classList.toggle('is-match', kategori !== '' && mark.dataset.category === kategori);
        });

        if (legenda) {
            [...legenda.querySelectorAll('.iso-legend__btn')].forEach((btn) => {
                btn.setAttribute('aria-pressed', String(btn.dataset.filter === kategori));
            });
        }

        const cocok = marks.filter((m) => m.classList.contains('is-match'));

        if (scope) {
            scope.textContent = kategori === ''
                ? kecs.length + ' kecamatan'
                : cocok.length + ' ' + (cocok[0]?.dataset.categoryLabel || 'fasilitas');
        }

        if (hint) {
            hint.textContent = kategori === ''
                ? 'Klik sebuah kecamatan untuk memperbesar dan melihat kantor layanan di dalamnya.'
                : 'Menampilkan seluruh ' + (cocok[0]?.dataset.categoryLabel || 'fasilitas')
                    + ' di Kabupaten Tuban. Tekan lagi jenisnya untuk kembali.';
        }

        hide();
    }
}
