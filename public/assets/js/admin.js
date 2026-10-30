/**
 * admin.js — count-up angka KPI pada panel petugas dan admin.
 * Angka asli tetap tertulis di HTML, jadi tanpa JS tampilannya sudah benar.
 */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reduceMotion) {
    document.querySelectorAll('.stat strong').forEach((node) => {
        const text = node.textContent.trim();
        const match = text.match(/^(\D*)([\d.]+)(.*)$/);

        if (!match) {
            return;
        }

        const target = Number(match[2].replace(/\./g, ''));

        if (!Number.isFinite(target) || target === 0) {
            return;
        }

        const [, prefix, , suffix] = match;
        const started = performance.now();
        const duration = 600;

        const step = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            const value = Math.round(target * progress);

            node.textContent = prefix + value.toLocaleString('id-ID') + suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                node.textContent = text;
            }
        };

        requestAnimationFrame(step);
    });
}
