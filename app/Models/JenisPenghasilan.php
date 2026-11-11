<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPenghasilan extends Model
{
    protected $table = 'jenis_penghasilan';

    protected $fillable = [
        'kode_jenis',
        'nama_jenis_pph',
        'tarif_pph',
    ];

    protected function casts(): array
    {
        return [
            'tarif_pph' => 'decimal:2',
        ];
    }
}
