<?php

namespace App\Services;

class PencairanSlaReportPresentation
{
    public static function duration(?array $measurement): ?int
    {
        $duration = $measurement['duration_days'] ?? null;

        return $duration === 0 && $measurement['result'] === 'tepat_waktu' ? 1 : $duration;
    }

    public static function resultLabel(string $result): string
    {
        return match ($result) {
            'tepat_waktu' => 'Tepat waktu',
            'terlambat' => 'Melewati SLA',
            'data_missing' => 'Tidak dapat dihitung',
        };
    }

    public static function activeLabel(string $status): string
    {
        return match ($status) {
            'terlambat' => 'Masih berjalan — lewat tenggat',
            'data_missing' => 'Masih berjalan — waktu mulai tidak tersedia',
            default => 'Masih berjalan',
        };
    }
}
