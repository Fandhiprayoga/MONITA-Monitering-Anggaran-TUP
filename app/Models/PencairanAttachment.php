<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PencairanAttachment extends Model
{
    protected $table = 'pengajuan_attachments';
    protected $guarded = ['id'];
    protected $hidden = ['disk', 'path'];
    public function completion() { return $this->belongsTo(PencairanCompletion::class, 'completion_id'); }
}