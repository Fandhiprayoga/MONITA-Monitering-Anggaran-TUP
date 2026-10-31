<?php

namespace App\Models;

use App\Enums\PencairanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PencairanPengajuan extends Model
{
    protected $table = 'pengajuans';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'header_snapshot' => 'array', 'total_bruto' => 'decimal:2',
            'total_pajak' => 'decimal:2', 'total_neto' => 'decimal:2',
            'saldo_awal_snapshot' => 'decimal:2', 'anggaran_terpakai_snapshot' => 'decimal:2',
            'saldo_terkini_snapshot' => 'decimal:2', 'saldo_checked_at' => 'datetime',
            'first_submitted_at' => 'datetime', 'last_submitted_at' => 'datetime',
            'verified_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'tahun_anggaran' => 'integer', 'triwulan' => 'integer',
            'revision_no' => 'integer', 'lock_version' => 'integer',
        ];
    }

    public function details()
    {
        return match ($this->jenis) {
            'impresfund' => $this->impresfundDetails(),
            'beban' => $this->bebanDetails(),
            'honor' => $this->honorDetails(),
        };
    }

    public function impresfundDetails() { return $this->hasMany(PencairanImpresfundDetail::class, 'pengajuan_id')->orderBy('urutan'); }
    public function bebanDetails() { return $this->hasMany(PencairanBebanDetail::class, 'pengajuan_id')->orderBy('urutan'); }
    public function honorDetails() { return $this->hasMany(PencairanHonorDetail::class, 'pengajuan_id')->orderBy('urutan'); }

    public function detailRows(): array
    {
        $relation = $this->jenis.'Details';
        $rows = $this->relationLoaded('details') ? $this->details : $this->{$relation};
        return $rows->map(fn ($row) => $row->raw_data)->all();
    }

    public function histories() { return $this->hasMany(PencairanHistory::class, 'pengajuan_id')->orderBy('id'); }
    public function revisions() { return $this->hasMany(PencairanRevision::class, 'pengajuan_id')->orderBy('revision_no'); }
    public function completion() { return $this->hasOne(PencairanCompletion::class, 'pengajuan_id'); }
    public function statusLabel(): string { return PencairanStatus::from($this->status)->label($this->jenis); }

    public function scopeForActor(Builder $query, array $actor): Builder
    {
        return $actor['role'] === 'admin' ? $query : $query->where('kode_pp', $actor['kode_pp']);
    }
}