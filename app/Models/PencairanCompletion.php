<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PencairanCompletion extends Model
{
    protected $table = 'pengajuan_completions';
    protected $guarded = ['id'];
    protected $casts = ['completion_date' => 'date'];
    public function pengajuan() { return $this->belongsTo(PencairanPengajuan::class, 'pengajuan_id'); }
    public function attachments() { return $this->hasMany(PencairanAttachment::class, 'completion_id'); }
}