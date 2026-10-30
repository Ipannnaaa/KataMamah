/**
 * search.js — saran langsung untuk smart search.
 *
 * Formnya tetap form biasa: tanpa JavaScript, Enter mengirim ke /cari dan
 * halaman hasil menampilkan temuan yang sama. Berkas ini hanya menambahkan
 * daftar saran yang bisa dipilih dengan panah atas/bawah.
 */

document.querySelectorAll('.smart-search').forEach(setup);

function setup(form) {
    const input = form.querySelector('.smart-search__input');
    const box = form.querySelector('.smart-search__results');
    const endpoint = form.dataset.endpoint;

    if (!input || !box || !endpoint) {
        return;
    }

    let timer = null;
    let items = [];
    let cursor = -1;
    let lastTerm = '';

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => run(input.value.trim()), 180);
    });

    input.addEventListener('keydown', (event) => {
        if (box.hidden || items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(cursor + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(cursor - 1);
        } else if (event.key === 'Enter' && cursor >= 0) {
            event.preventDefault();
            window.location.href = items[cursor].url;
        } else if (event.key === 'Escape') {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) {
            close();
        }
    });

    // Contoh kata kunci di halaman pembuka.
    document.querySelectorAll('.chip--try').forEach((chip) => {
        chip.addEventListener('click', () => {
            input.value = chip.dataset.q || chip.textContent.trim();
            input.focus();
            run(input.value);
        });
    });

    async function run(term) {
        if (term.length < 2) {
            close();

            return;
        }

        if (term === lastTerm && !box.hidden) {
            return;
        }

        lastTerm = term;

        try {
            const response = await fetch(endpoint + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                close();

                return;
            }

            const data = await response.json();
            render(data.results || [], term);
        } catch (error) {
            close(); // jaringan bermasalah: form biasa tetap bisa dipakai
        }
    }

    function render(results, term) {
        items = results;
        cursor = -1;
        box.innerHTML = '';

        if (results.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'smart-search__empty';
            empty.textContent = 'Tidak ada yang cocok dengan "' + term + '".';
            box.append(empty);
            open();

            return;
        }

        results.forEach((item, index) => {
            const row = document.createElement('a');
            row.className = 'smart-search__item';
            row.href = item.url;
            row.id = 'ss-item-' + index;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', 'false');

            const type = document.createElement('span');
            type.className = 'result__type result__type--' + item.type;
            type.textContent = item.label;

            const body = document.createElement('span');
            body.className = 'result__body';

            const title = document.createElement('strong');
            title.textContent = item.title;

            const sub = document.createElement('small');
            sub.textContent = item.subtitle;

            body.append(title, sub);

            const badge = document.createElement('span');
            badge.className = 'result__badge';
            badge.textContent = item.badge;

            row.append(type, body, badge);
            row.addEventListener('mouseenter', () => move(index));
            box.append(row);
        });

        open();
    }

    function move(next) {
        const rows = [...box.querySelectorAll('.smart-search__item')];

        if (rows.length === 0) {
            return;
        }

        cursor = (next + rows.length) % rows.length;

        rows.forEach((row, index) => {
            const on = index === cursor;
            row.classList.toggle('is-cursor', on);
            row.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        input.setAttribute('aria-activedescendant', 'ss-item-' + cursor);
    }

    function open() {
        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function close() {
        box.hidden = true;
        cursor = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }
}
