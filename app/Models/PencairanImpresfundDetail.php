<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PencairanImpresfundDetail extends Model
{
    protected $table = 'pengajuan_impresfund_details';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $casts = ['raw_data' => 'array', 'volume' => 'decimal:2', 'tarif' => 'decimal:2', 'subtotal_manual' => 'decimal:2', 'subtotal_efektif' => 'decimal:2'];
}