<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
abstract class PencairanAudit extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false;
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit pengajuan tidak dapat diubah.'));
        static::deleting(fn () => throw new \LogicException('Audit pengajuan tidak dapat dihapus.'));
    }
}