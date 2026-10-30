/**
 * queue.js — hanya syarat berkas milik layanan terpilih yang ditampilkan.
 * Tanpa JS, semua kelompok berkas tampil (tetap bisa dikirim).
 */

const form = document.getElementById('form-antrean');

if (form) {
    const groups = form.querySelectorAll('.req-group');
    const radios = form.querySelectorAll('input[name="service_id"]');

    const sync = () => {
        const selected = form.querySelector('input[name="service_id"]:checked');

        if (!selected) {
            return;
        }

        groups.forEach((group) => {
            const match = group.dataset.forService === selected.value;
            group.hidden = !match;

            // Berkas di kelompok yang tersembunyi tidak boleh ikut terkirim.
            group.querySelectorAll('input[type="file"]').forEach((input) => {
                input.disabled = !match;
            });
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', sync));
    sync();
}
