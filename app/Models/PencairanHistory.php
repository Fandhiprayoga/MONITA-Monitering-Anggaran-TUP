<?php
namespace App\Models;
class PencairanHistory extends PencairanAudit
{
    protected $table = 'pengajuan_histories';
    protected $casts = ['created_at' => 'datetime'];

    public function getDisplayRemarkAttribute(): ?string
    {
        if (!$this->remark || $this->action !== 'simkug') {
            return $this->remark;
        }

        $details = json_decode($this->remark, true);
        if (!is_array($details) || !is_array($details['sesudah'] ?? null)) {
            return $this->remark;
        }

        return self::simkugRemark($details['sesudah']['dc'] ?? null);
    }

    public static function simkugRemark(?string $dc): string
    {
        return match ($dc) {
            'D' => 'Posisi D/C diatur ke Debit.',
            'C' => 'Posisi D/C diatur ke Kredit.',
            default => 'Data D/C diperbarui.',
        };
    }
}