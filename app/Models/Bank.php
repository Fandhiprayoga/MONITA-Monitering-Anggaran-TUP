<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = [
        'kode_bank',
        'inisial',
        'nama_bank',
        'status',
    ];
}
