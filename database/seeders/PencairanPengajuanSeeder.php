<?php

namespace Database\Seeders;

use App\Enums\PencairanStatus;
use App\Models\PencairanActor;
use App\Models\PencairanPengajuan;
use App\Services\PencairanService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class PencairanPengajuanSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new LogicException('Seeder pencairan dummy hanya boleh dijalankan pada environment local/testing.');
        }

        DB::transaction(function () {
            $admin = $this->actor('admin', 'KUG');
            // Serialize repeated runs without truncating or changing existing submissions.
            PencairanActor::whereKey($admin->id)->lockForUpdate()->firstOrFail();

            foreach (['SIF', 'KUG', 'AKA'] as $kodePp) {
                $creator = $this->actor('user', $kodePp);
                foreach (['impresfund', 'beban', 'honor'] as $jenis) {
                    foreach (PencairanStatus::cases() as $status) {
                        $description = '[DUMMY] '.ucfirst($jenis).' '.$kodePp.' — '.$status->value;
                        if (PencairanPengajuan::where('created_by_actor_id', $creator->id)
                            ->where('jenis', $jenis)->where('uraian_pengajuan', $description)->exists()) {
                            continue;
                        }
                        $this->pengajuan($creator, $admin, $kodePp, $jenis, $status, $description);
                    }
                }
            }
        }, 5);
    }

    private function actor(string $role, string $kodePp): PencairanActor
    {
        $username = 'dummy-pencairan-'.$role.'-'.strtolower($kodePp);
        $key = hash('sha256', 'monita-dummy-pencairan-v1|'.$username);
        DB::table('pengajuan_actors')->insertOrIgnore([
            'source_key' => $key, 'username' => $username,
            'name' => 'Dummy '.ucfirst($role).' '.$kodePp, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return PencairanActor::where('source_key', $key)->firstOrFail();
    }

    private function pengajuan(PencairanActor $creator, PencairanActor $admin, string $kodePp,
        string $jenis, PencairanStatus $status, string $description): void
    {
        // Keep all timestamps within the active quarter, including its first day.
        $createdAt = now()->startOfDay()->addHours(8)->min(now());
        $submittedAt = $createdAt->copy()->addMinutes(15)->min(now());
        $verifiedAt = $submittedAt->copy()->addMinutes(15)->min(now());
        $finishedAt = $verifiedAt->copy()->addMinutes(15)->min(now());
        [$records, $totals] = app(PencairanService::class)->calculate($jenis, $this->rows($jenis, $kodePp), true);
        $budget = [
            'id' => hash('sha256', 'dummy-drk|'.$kodePp.'|'.$jenis),
            'dummy' => true, 'kode_pp' => $kodePp, 'nama_pp' => config('units.'.$kodePp, $kodePp),
            'drk_tup' => 'DUMMY-'.strtoupper($jenis).'-'.$kodePp,
            'kode_drk' => 'DUMMY-DRK-'.$kodePp, 'tipe' => 'Operasional',
            'kode_akun' => match ($jenis) { 'impresfund' => '521111', 'beban' => '521211', 'honor' => '521213' },
            'nama_akun' => 'Anggaran dummy '.ucfirst($jenis),
            'uraian_drk' => 'Kegiatan dummy untuk pengujian, bukan DRK Google Sheets',
            'saldo_awal' => '100.000.000,00', 'anggaran_terpakai' => '10.000.000,00',
            'saldo_terkini' => '90.000.000,00',
        ];
        $p = PencairanPengajuan::create([
            'jenis' => $jenis, 'status' => 'draft', 'kode_pp' => $kodePp, 'nama_pp' => $budget['nama_pp'],
            'created_by_actor_id' => $creator->id, 'tahun_anggaran' => now()->year,
            'triwulan' => (int) ceil(now()->month / 3), 'drk_source_id' => $budget['id'],
            'header_snapshot' => $budget, 'uraian_pengajuan' => $description,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ] + $totals);
        $p->details()->createMany($records);
        $this->history($p, $creator, 'create', null, 'draft', $createdAt);

        if ($status === PencairanStatus::Draft) {
            return;
        }

        $year = now()->year;
        DB::table('pengajuan_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $sequence = DB::table('pengajuan_number_sequences')->where('year', $year)->lockForUpdate()->first();
        $number = max((int) $sequence->last_number,
            (int) PencairanPengajuan::where('tahun_nomor', $year)->max('nomor_urut')) + 1;
        DB::table('pengajuan_number_sequences')->where('year', $year)->update(['last_number' => $number]);
        $budget += ['saldo_awal_decimal' => '100000000.00', 'anggaran_terpakai_decimal' => '10000000.00',
            'saldo_terkini_decimal' => '90000000.00', 'checked_at' => $submittedAt->toISOString()];
        $p->fill([
            'status' => 'submitted', 'tahun_nomor' => $year, 'nomor_urut' => $number,
            'nomor_pengajuan' => sprintf('%06d/%d/%s/%s', $number, $year, $submittedAt->format('dm'), $kodePp),
            'revision_no' => 1, 'lock_version' => 1, 'first_submitted_at' => $submittedAt,
            'last_submitted_at' => $submittedAt, 'header_snapshot' => $budget,
            'saldo_awal_snapshot' => '100000000.00', 'anggaran_terpakai_snapshot' => '10000000.00',
            'saldo_terkini_snapshot' => '90000000.00', 'saldo_checked_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ])->save();
        $p->revisions()->create([
            'revision_no' => 1, 'submitted_by' => $creator->id, 'submitted_at' => $submittedAt, 'schema_version' => 1,
            'snapshot' => [
                'header' => $p->only(['jenis', 'kode_pp', 'nama_pp', 'tahun_anggaran', 'triwulan',
                    'drk_source_id', 'nomor_pengajuan', 'uraian_pengajuan']),
                'details' => $p->detailRows(),
                'detail_values' => $p->details->map(fn ($row) => $row->only(['urutan', 'volume', 'tarif',
                    'subtotal_manual', 'subtotal_efektif', 'nominal', 'pajak', 'neto']))->all(),
                'totals' => $totals, 'budget' => $budget,
            ],
        ]);
        $this->history($p, $creator, 'submit', 'draft', 'submitted', $submittedAt);

        if (in_array($status, [PencairanStatus::Returned, PencairanStatus::Cancelled], true)) {
            $cancelled = $status === PencairanStatus::Cancelled;
            $p->status = $status->value;
            $p->lock_version++;
            $p->updated_at = $verifiedAt;
            if ($cancelled) {
                $p->cancelled_at = $verifiedAt;
                $p->cancelled_by = $admin->id;
            }
            $p->save();
            $this->history($p, $admin, $cancelled ? 'cancel' : 'return', 'submitted', $status->value,
                $verifiedAt, $cancelled ? 'Dummy: kegiatan dibatalkan.' : 'Dummy: mohon perbaiki rincian pengajuan.');
        }

        if (in_array($status, [PencairanStatus::Verified, PencairanStatus::Completed], true)) {
            $budget['checked_at'] = $verifiedAt->toISOString();
            $p->fill(['status' => 'verified', 'verified_at' => $verifiedAt, 'verified_by' => $admin->id,
                'saldo_checked_at' => $verifiedAt, 'header_snapshot' => $budget,
                'lock_version' => 2, 'updated_at' => $verifiedAt])->save();
            $this->history($p, $admin, 'verify', 'submitted', 'verified', $verifiedAt);
        }

        if ($status === PencairanStatus::Completed) {
            $p->fill(['status' => 'completed', 'completed_at' => $finishedAt, 'completed_by' => $admin->id,
                'lock_version' => 3, 'updated_at' => $finishedAt])->save();
            $p->completion()->create([
                'completion_date' => $finishedAt->toDateString(), 'actor_id' => $admin->id,
                'notes' => 'Penyelesaian dummy, tidak ada pembayaran atau pengiriman ke TUB sebenarnya.',
                'created_at' => $finishedAt, 'updated_at' => $finishedAt,
            ]);
            $this->history($p, $admin, 'complete', 'verified', 'completed', $finishedAt);
        }
    }

    private function history(PencairanPengajuan $p, PencairanActor $actor, string $action,
        ?string $from, string $to, CarbonInterface $at, ?string $remark = null): void
    {
        $p->histories()->create([
            'revision_no' => $p->revision_no ?: null, 'action' => $action,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'actor_role' => $actor->role,
            'remark' => $remark, 'created_at' => $at,
        ]);
    }

    private function rows(string $jenis, string $kodePp): array
    {
        if ($jenis === 'impresfund') {
            return [
                ['no' => '001', 'uraian' => 'ATK kegiatan '.$kodePp, 'volume' => '5', 'tarif' => '75.000', 'subtotal' => ''],
                ['no' => '002', 'uraian' => 'Konsumsi rapat '.$kodePp, 'volume' => '20', 'tarif' => '35.000', 'subtotal' => ''],
                ['no' => '003', 'uraian' => 'Transportasi operasional '.$kodePp, 'volume' => '3', 'tarif' => '150.000', 'subtotal' => '425.000'],
            ];
        }

        $rows = [];
        foreach (['Andi Dummy', 'Budi Dummy', 'Citra Dummy'] as $index => $name) {
            $row = [
                'no' => sprintf('%03d', $index + 1), 'nominal' => '1.000.000', 'pajak' => '50.000',
                'neto' => '950.000', 'bank' => 'BANK DUMMY', 'nama' => $name,
                'rekening' => '000000000'.($index + 1),
            ];
            if ($jenis === 'honor') {
                $row += ['kewarganegaraan' => $index === 2 ? 'WNA' : 'WNI', 'penerima' => $name,
                    'identitas' => $index === 2 ? 'DUMMY-PASPOR-003' : '000000000000000'.($index + 1),
                    'npwp' => $index === 2 ? '' : '00.000.000.0-000.00'.($index + 1)];
            }
            $rows[] = $row;
        }

        return $rows;
    }
}