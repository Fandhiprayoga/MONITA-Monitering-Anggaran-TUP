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
            self::Completed => $jenis === 'impresfund' ? 'Dicairkan' : 'Diajukan ke TUB',
            self::Cancelled => 'Dibatalkan',
        };
    }
}