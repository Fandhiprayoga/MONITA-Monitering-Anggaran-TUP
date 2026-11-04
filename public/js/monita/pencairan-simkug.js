export function mountSimkugCopy(panel) {
    const status = panel.querySelector('[data-simkug-copy-status]');
    panel.querySelectorAll('[data-simkug-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const input = button.parentElement.querySelector('input, textarea');
            status.textContent = '';
            button.disabled = true;
            try {
                if (!navigator.clipboard?.writeText) {
                    throw new Error('Clipboard tidak tersedia.');
                }
                await navigator.clipboard.writeText(input.value);
                status.classList.remove('text-danger');
                status.classList.add('text-success');
                status.textContent = `${button.getAttribute('aria-label').replace(/^Salin /, '')} berhasil disalin.`;
            } catch (error) {
                input.focus();
                input.select();
                status.classList.remove('text-success');
                status.classList.add('text-danger');
                status.textContent = 'Gagal menyalin otomatis. Nilai sudah dipilih; tekan Ctrl+C atau Cmd+C untuk menyalin.';
            } finally {
                button.disabled = false;
            }
        });
    });
}
