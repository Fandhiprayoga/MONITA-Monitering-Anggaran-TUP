<?php

namespace App\Services;

class PencairanSlaReportCsv
{
    public function write(iterable $rows): void
    {
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('Tidak dapat membuka output CSV Report SLA.');
        }

        try {
            if (fwrite($output, "\xEF\xBB\xBF") === false) {
                throw new \RuntimeException('Tidak dapat menulis CSV Report SLA.');
            }
            $this->writeRow($output, [
                'Nomor Pengajuan', 'Uraian Pengajuan', 'Kode Unit', 'Nama Unit', 'Jenis',
                'Tgl Pengajuan', 'Tgl Verifikasi', 'Tgl Transaksi', 'Tgl Selesai',
                'Durasi Verifikasi (hari kerja)', 'Hasil Verifikasi', 'Target Verifikasi (hari kerja)', 'Keterangan Verifikasi',
                'Proses Verifikasi Aktif', 'Target Verifikasi Aktif (hari kerja)',
                'Durasi Transaksi (hari kerja)', 'Hasil Transaksi', 'Target Transaksi (hari kerja)', 'Keterangan Transaksi',
                'Proses Transaksi Aktif', 'Target Transaksi Aktif (hari kerja)', 'Status Pengajuan',
            ]);

            foreach ($rows as $row) {
                $pengajuan = $row['pengajuan'];
                $verification = $row['stages']['verifikasi'];
                $transaction = $row['stages'][$row['transaction_stage']];
                $this->writeRow($output, [
                    $pengajuan->nomor_pengajuan, $pengajuan->uraian_pengajuan, $pengajuan->kode_pp,
                    $pengajuan->nama_pp, $pengajuan->jenisLabel(),
                    $pengajuan->first_submitted_at->format('d/m/Y'),
                    ($verification['finished_at'] ?? null)?->format('d/m/Y'),
                    $pengajuan->completion?->completion_date?->format('d/m/Y'),
                    ($transaction['finished_at'] ?? null)?->format('d/m/Y'),
                    ...$this->stageColumns($verification, $row['active_stage'] === 'verifikasi' ? $row['active'] : null),
                    ...$this->stageColumns($transaction, $row['active_stage'] === $row['transaction_stage'] ? $row['active'] : null),
                    $pengajuan->statusLabel(),
                ]);
            }
        } finally {
            fclose($output);
        }
    }

    private function stageColumns(?array $measurement, ?array $active): array
    {
        return [
            PencairanSlaReportPresentation::duration($measurement),
            $measurement ? PencairanSlaReportPresentation::resultLabel($measurement['result']) : null,
            $measurement['target_days'] ?? null,
            $measurement['problem'] ?? null,
            $active ? PencairanSlaReportPresentation::activeLabel($active['status']) : null,
            $active['target_days'] ?? null,
        ];
    }

    /** @param resource $output */
    private function writeRow($output, array $fields): void
    {
        $fields = array_map(static function ($field) {
            if (is_string($field) && preg_match('/^[\s\x00-\x1f]*[=+\-@\t\r\n]/u', $field)) {
                return "'".$field;
            }

            return $field;
        }, $fields);

        if (fputcsv($output, $fields, ',', '"', '', "\r\n") === false) {
            throw new \RuntimeException('Tidak dapat menulis baris CSV Report SLA.');
        }
    }
}
