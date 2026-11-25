export function mountSimkugHeaderCopy(panel) {
    const status = panel.querySelector('[data-header-copy-status]');
    panel.querySelectorAll('[data-header-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.closest('[data-header-copy-item]').querySelector('[data-header-copy-value]');
            status.textContent = '';
            button.disabled = true;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('Clipboard tidak tersedia.');
                await navigator.clipboard.writeText(value.textContent);
                status.classList.remove('text-danger');
                status.classList.add('text-success');
                status.textContent = `${button.getAttribute('aria-label').replace(/^Salin /, '')} berhasil disalin.`;
            } catch (error) {
                status.classList.remove('text-success');
                status.classList.add('text-danger');
                status.textContent = 'Gagal menyalin otomatis. Pilih nilai dan tekan Ctrl+C atau Cmd+C.';
            } finally {
                button.disabled = false;
            }
        });
    });
}
