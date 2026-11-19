<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PencairanSlaPolicy extends Model
{
    protected $fillable = [
        'effective_from', 'verifikasi', 'transaksi_imprestfund', 'transaksi_beban', 'transaksi_honor', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'verifikasi' => 'integer',
            'transaksi_imprestfund' => 'integer',
            'transaksi_beban' => 'integer',
            'transaksi_honor' => 'integer',
        ];
    }
}
