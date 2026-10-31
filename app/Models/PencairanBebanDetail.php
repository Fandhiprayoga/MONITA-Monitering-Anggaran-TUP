<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PencairanBebanDetail extends Model
{
    protected $table = 'pengajuan_beban_details';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $casts = ['raw_data' => 'array', 'nominal' => 'decimal:2', 'pajak' => 'decimal:2', 'neto' => 'decimal:2'];
}