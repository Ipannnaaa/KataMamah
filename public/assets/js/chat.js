/**
 * chat.js — polling pesan baru tiap 4 detik dan kirim pesan tanpa reload.
 * Bila JS mati, halaman chat tetap menampilkan pesan yang sudah ada.
 */

const chat = document.querySelector('.chat');
const list = document.getElementById('chat-list');
const form = document.getElementById('chat-form');

if (chat && list) {
    const endpoint = chat.dataset.endpoint;
    const token = chat.dataset.token;

    let lastId = lastRenderedId();
    let polling = true;

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const field = form.querySelector('textarea[name="body"]');
            const body = field.value.trim();

            if (body === '') {
                return;
            }

            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        Accept: 'application/json',
                    },
                    body: new URLSearchParams({ body, _token: token }).toString(),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Pesan gagal dikirim.');
                }

                field.value = '';
                await poll();
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        });
    }

    window.setInterval(() => {
        if (polling) {
            poll();
        }
    }, 4000);

    document.addEventListener('visibilitychange', () => {
        polling = document.visibilityState === 'visible';
    });

    async function poll() {
        try {
            const response = await fetch(endpoint + '?after=' + lastId, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            (data.messages || []).forEach(append);
        } catch (error) {
            // Jaringan putus sesaat bukan alasan menampilkan error ke warga.
        }
    }

    function append(message) {
        const empty = list.querySelector('.chat__empty');

        if (empty) {
            empty.remove();
        }

        const item = document.createElement('li');
        item.className = 'bubble' + (message.mine ? ' bubble--mine' : '');

        const who = document.createElement('span');
        who.className = 'bubble__who';
        who.textContent = message.sender_name;

        const body = document.createElement('span');
        body.className = 'bubble__body';
        body.textContent = message.body;

        const time = document.createElement('span');
        time.className = 'bubble__time';
        time.textContent = message.at.slice(11, 16).replace(':', '.');

        item.append(who, body, time);
        list.appendChild(item);
        list.scrollTop = list.scrollHeight;

        lastId = Math.max(lastId, message.id);
    }

    function lastRenderedId() {
        return Number(list.dataset.lastId || 0);
    }

    list.scrollTop = list.scrollHeight;
}
