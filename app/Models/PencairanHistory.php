<?php
namespace App\Models;
class PencairanHistory extends PencairanAudit
{
    protected $table = 'pengajuan_histories';
    protected $casts = ['created_at' => 'datetime'];
}