<?php
namespace App\Models;
class PencairanRevision extends PencairanAudit
{
    protected $table = 'pengajuan_revisions';
    protected $casts = ['snapshot' => 'array', 'submitted_at' => 'datetime'];
}