<?php

namespace App\Enums;

enum PencairanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Verified = 'verified';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(string $jenis): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Menunggu Verifikasi',
            self::Returned => 'Dikembalikan untuk Perbaikan',
            self::Verified => 'Terverifikasi / Menunggu Transaksi',
            self::Completed => $jenis === 'imprestfund' ? 'Dicairkan' : 'Diajukan ke TUB',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-light-secondary',
            self::Submitted => 'bg-light-warning',
            self::Returned => 'bg-light-danger',
            self::Verified => 'bg-light-info',
            self::Completed => 'bg-light-success',
            self::Cancelled => 'bg-light-dark',
        };
    }
}