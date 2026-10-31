<?php

namespace App\Services;

use App\Models\PencairanActor;
use App\Models\PencairanPengajuan;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PencairanService
{
    /** Lazy resolution keeps return/cancel/download independent of Sheets credential initialization. */
    private ?GoogleSheetService $sheets;

    public function __construct(mixed $sheets = null)
    {
        $this->sheets = $sheets;
    }

    public function actor(Request $request): array
    {
        $user = $request->session()->get('user_data');
        abort_unless($request->session()->get('user_authenticated') === true && is_array($user), 403, 'Silakan login kembali.');
        $role = $user['role'] ?? $request->session()->get('user_role');
        abort_unless(in_array($role, ['user', 'admin'], true) && $request->session()->get('user_role', $role) === $role, 403, 'Session peran tidak valid.');
        $username = trim((string) ($user['username'] ?? ''));
        $pp = strtoupper(trim((string) ($user['kode_pp'] ?? '')));
        abort_unless($username !== '' && strlen($username) <= 255 && ($role === 'admin' || $pp !== '') && strlen($pp) <= 100, 403, 'Identitas akun tidak valid.');
        $stable = config('pencairan.actor_source_no_stable');
        $identity = $stable ? trim((string) ($user['no'] ?? '')) : $username;
        abort_if($identity === '', 403, 'ID aktor tidak tersedia.');
        $key = hash('sha256', config('pencairan.actor_namespace').'|'.($stable ? 'no:' : 'username:').$identity);
        return ['source_key' => $key, 'username' => $username, 'name' => $username,
            'role' => $role, 'kode_pp' => $pp, 'nama_pp' => mb_substr((string) ($user['nama_pp'] ?? $pp), 0, 255)];
    }

    public function authorize(Request $request, PencairanPengajuan $pengajuan): array
    {
        $actor = $this->actor($request);
        abort_unless($actor['role'] === 'admin' || $pengajuan->kode_pp === $actor['kode_pp'], 403, 'Akses PP ditolak.');
        return $actor;
    }

    private function localActor(array $actor): PencairanActor
    {
        // insertOrIgnore handles concurrent first use; history snapshots never follow profile changes.
        DB::table('pengajuan_actors')->insertOrIgnore([
            'source_key' => $actor['source_key'], 'username' => $actor['username'],
            'name' => $actor['name'], 'role' => $actor['role'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return PencairanActor::where('source_key', $actor['source_key'])->firstOrFail();
    }

    private function validatePayload(Request $request, string $operation): array
    {
        $actor = $this->actor($request);
        if ($request->filled('kode_pp') && !is_string($request->input('kode_pp'))) { PencairanDecimal::invalid('kode_pp', 'Kode PP harus berupa teks.'); }
        if (in_array($operation, ['store', 'update'], true) && $request->filled('kode_pp') && strtoupper(trim((string) $request->input('kode_pp'))) !== $actor['kode_pp']) {
            PencairanDecimal::invalid('kode_pp', 'Kode PP harus sesuai akun yang sedang login.');
        }
        foreach (array_keys($request->all()) as $field) {
            if (preg_match('/bukti|referensi|reference|tub/i', $field)) { PencairanDecimal::invalid($field, 'Referensi eksternal tidak dicatat dalam MONITA.'); }
        }
        foreach (['nomor_bukti', 'nomor_bukti_pencairan', 'bukti_pencairan', 'referensi_tub', 'nomor_tub', 'tub_reference', 'external_reference', 'nomor_referensi', 'reference_number'] as $field) {
            if ($request->exists($field)) { PencairanDecimal::invalid($field, 'Referensi eksternal tidak dicatat dalam MONITA.'); }
        }
        $rules = match ($operation) {
            'store', 'update' => ['jenis' => ($operation === 'store' ? 'required' : 'sometimes').'|in:impresfund,beban,honor',
                'drk_id' => 'nullable|string|max:64', 'uraian_pengajuan' => 'nullable|string|max:5000',
                'details' => 'present|array|max:500', 'details.*' => 'array'],
            'return' => ['remark' => 'required|string|max:5000', 'confirmed' => 'required|accepted'],
            'verify', 'cancel' => ['confirmed' => 'required|accepted', 'remark' => 'nullable|string|max:5000'],
            'complete' => ['confirmed' => 'required|accepted', 'completion_date' => 'required|date_format:Y-m-d',
                'notes' => 'nullable|string|max:5000', 'attachments' => 'nullable|array|max:5',
                'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240',
                    function ($attribute, $file, $fail) {
                        if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) { $fail('Konten lampiran harus PDF/JPEG/PNG.'); }
                        }
                    }]],
            'submit' => ['confirmed' => 'sometimes|accepted'],
        };
        if ($operation !== 'store') { $rules['lock_version'] = 'required|integer|min:0'; }
        if (in_array($operation, ['submit', 'complete'], true)) { $rules['idempotency_key'] = 'required|string|max:100'; }
        $data = Validator::make($request->all(), $rules, [
            'required' => ':attribute wajib diisi.', 'present' => ':attribute wajib disertakan.',
            'accepted' => 'Konfirmasi tindakan wajib disetujui.', 'string' => ':attribute harus berupa teks.',
            'array' => ':attribute harus berupa daftar.', 'integer' => ':attribute harus berupa bilangan bulat.',
            'in' => 'Pilihan :attribute tidak valid.', 'min' => ':attribute tidak boleh kurang dari :min.',
            'max' => ':attribute melebihi batas :max.', 'date_format' => 'Tanggal wajib valid dengan format YYYY-MM-DD.',
            'file' => 'Lampiran harus berupa berkas valid.', 'mimes' => 'Lampiran hanya PDF/JPEG/PNG.',
            'mimetypes' => 'Konten lampiran hanya PDF/JPEG/PNG.',
        ])->validate();
        if ($operation === 'return' && trim($data['remark']) === '') { PencairanDecimal::invalid('remark', 'Alasan pengembalian wajib diisi.'); }
        return $data;
    }

    /** Returns typed records plus normalized frontend records. All arithmetic is decimal. */
    public function calculate(string $jenis, array $rows, bool $complete = false): array
    {
        if (count($rows) > 500) { PencairanDecimal::invalid('details', 'Maksimal 500 baris.'); }
        $keys = match ($jenis) {
            'impresfund' => ['no', 'uraian', 'volume', 'tarif', 'subtotal'],
            'beban' => ['no', 'nominal', 'pajak', 'neto', 'bank', 'nama', 'rekening'],
            'honor' => ['no', 'kewarganegaraan', 'nominal', 'pajak', 'neto', 'penerima', 'nama', 'bank', 'rekening', 'identitas', 'npwp'],
        };
        $numeric = $jenis === 'impresfund' ? ['volume', 'tarif', 'subtotal'] : ['nominal', 'pajak', 'neto'];
        $totals = ['total_bruto' => BigDecimal::zero(), 'total_pajak' => BigDecimal::zero(), 'total_neto' => BigDecimal::zero()];
        $records = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) { PencairanDecimal::invalid("details.$index", 'Baris harus berupa objek.'); }
            $raw = []; $numbers = [];
            foreach ($keys as $key) {
                $value = $row[$key] ?? null;
                $field = "details.$index.$key";
                if (in_array($key, $numeric, true)) {
                    $numbers[$key] = PencairanDecimal::raw($value, $field);
                    $raw[$key] = $numbers[$key] === null ? '' : PencairanDecimal::format($numbers[$key]);
                } else {
                    if ($value !== null && !is_string($value)) { PencairanDecimal::invalid($field, 'Harus berupa teks (nol awal dipertahankan).'); }
                    if (mb_strlen($value ?? '') > ($key === 'uraian' ? 2000 : 255)) { PencairanDecimal::invalid($field, 'Teks terlalu panjang.'); }
                    $raw[$key] = $value ?? '';
                }
            }
            if ($jenis === 'honor') {
                $raw['kewarganegaraan'] = strtoupper(trim($raw['kewarganegaraan']));
                if ($raw['kewarganegaraan'] !== '' && !in_array($raw['kewarganegaraan'], ['WNI', 'WNA'], true)) { PencairanDecimal::invalid("details.$index.kewarganegaraan", 'Pilih WNI atau WNA.'); }
            }
            $active = false;
            foreach ($raw as $key => $value) {
                if ($key === 'no') { continue; }
                if ($key === 'pajak' && ($numbers['pajak'] === null || BigDecimal::of($numbers['pajak'])->isZero())) { continue; }
                if (trim($value) !== '') { $active = true; }
            }
            if (!$active) { continue; }
            if ($complete) {
                $required = $jenis === 'impresfund' ? ['uraian', 'volume', 'tarif'] : ['nominal', 'pajak', 'neto', 'bank', 'nama', 'rekening'];
                if ($jenis === 'honor') { $required = array_merge($required, ['kewarganegaraan', 'penerima', 'identitas']); }
                foreach ($required as $key) {
                    if (trim($raw[$key]) === '') { PencairanDecimal::invalid("details.$index.$key", 'Rincian ini wajib diisi sebelum submit.'); }
                }
            }
            $record = ['urutan' => count($records) + 1, 'raw_data' => $raw];
            if ($jenis === 'impresfund') {
                if ($numbers['volume'] !== null && BigDecimal::of($numbers['volume'])->isZero()) { PencairanDecimal::invalid("details.$index.volume", 'Volume harus lebih dari nol.'); }
                $effective = $numbers['subtotal'] ?? PencairanDecimal::multiply($numbers['volume'], $numbers['tarif'], "details.$index.subtotal");
                $record += ['uraian' => $raw['uraian'] ?: null, 'volume' => $numbers['volume'], 'tarif' => $numbers['tarif'], 'subtotal_manual' => $numbers['subtotal'], 'subtotal_efektif' => $effective];
                $bruto = $effective ?? '0'; $tax = '0'; $net = $bruto;
            } else {
                $bruto = $numbers['nominal'] ?? '0'; $tax = $numbers['pajak'] ?? '0'; $net = $numbers['neto'] ?? '0';
                if ($numbers['nominal'] !== null && BigDecimal::of($tax)->isGreaterThan($bruto)) { PencairanDecimal::invalid("details.$index.pajak", 'Pajak tidak boleh melebihi bruto.'); }
                $record += ['nominal' => $numbers['nominal'], 'pajak' => $numbers['pajak'], 'neto' => $numbers['neto'], 'bank' => $raw['bank'] ?: null, 'nama_rekening' => $raw['nama'] ?: null, 'nomor_rekening' => $raw['rekening'] === '' ? null : $raw['rekening']];
                if ($jenis === 'honor') { $record += ['kewarganegaraan' => $raw['kewarganegaraan'] ?: null, 'nama_penerima' => $raw['penerima'] ?: null, 'nomor_identitas' => $raw['identitas'] === '' ? null : $raw['identitas'], 'npwp' => $raw['npwp'] === '' ? null : $raw['npwp']]; }
            }
            foreach (['total_bruto' => $bruto, 'total_pajak' => $tax, 'total_neto' => $net] as $key => $value) { $totals[$key] = $totals[$key]->plus($value); }
            $records[] = $record;
        }
        foreach ($totals as $key => $value) {
            if ($value->isGreaterThan(config('pencairan.max_total'))) { PencairanDecimal::invalid('details', 'Total maksimal 5 triliun.'); }
            $totals[$key] = (string) $value->toScale(2);
        }
        if ($complete && (!$records || !BigDecimal::of($totals['total_bruto'])->isPositive())) { PencairanDecimal::invalid('details', 'Minimal satu rincian lengkap dan total bruto lebih dari nol.'); }
        return [$records, $totals];
    }

    private function drk(PencairanPengajuan $p, bool $required): ?array
    {
        if (!$p->drk_source_id) {
            if ($required) { PencairanDecimal::invalid('drk_id', 'Pilih DRK sebelum submit.'); }
            return null;
        }
        try {
            $this->sheets ??= app(GoogleSheetService::class);
            $options = $this->sheets->getPencairanDrkOptions($p->kode_pp, $p->triwulan, $p->tahun_anggaran);
        }
        catch (\Throwable $e) {
            // Draft data must remain saveable when the source is unavailable.
            // No browser snapshot is trusted; submit/verify always resolve again.
            if (!$required) { return null; }
            abort(503, 'Gagal memperoleh saldo terbaru. Silakan coba kembali.');
        }
        $matches = array_values(array_filter($options, fn ($o) => ($o['id'] ?? null) === $p->drk_source_id && strtoupper(trim($o['kode_pp'] ?? '')) === $p->kode_pp));
        if (count($matches) !== 1) { PencairanDecimal::invalid('drk_id', count($matches) > 1 ? 'Identitas DRK ambigu/duplikat. Hubungi administrator.' : 'DRK tidak aktif atau tidak tersedia untuk PP/periode ini.'); }
        $option = $matches[0];
        if ($required) {
            foreach (['saldo_awal', 'anggaran_terpakai', 'saldo_terkini'] as $key) {
                try { $value = PencairanDecimal::raw($option[$key] ?? null, $key, true, false); }
                catch (ValidationException $e) { abort(503, 'Data saldo sumber tidak valid.'); }
                abort_if($value === null, 503, 'Data saldo sumber tidak lengkap.');
                abort_if(BigDecimal::of($value)->abs()->isGreaterThan('9999999999999999.99'), 503, 'Data saldo sumber melebihi kapasitas penyimpanan.');
                $option[$key.'_decimal'] = $value;
            }
            if (BigDecimal::of($p->total_bruto)->isGreaterThan($option['saldo_terkini_decimal'])) { PencairanDecimal::invalid('saldo', 'Nominal diajukan '.$p->total_bruto.' melebihi saldo terkini '.$option['saldo_terkini_decimal'].'.'); }
            $option['checked_at'] = now()->toISOString();
        }
        return $option;
    }

    private function checkPeriod(PencairanPengajuan $p): void
    {
        if ($p->tahun_anggaran !== now()->year || $p->triwulan !== (int) ceil(now()->month / 3)) {
            PencairanDecimal::invalid('period', $p->status === 'returned'
                ? 'Pengajuan dikembalikan sudah melewati triwulan asal. Menunggu pembatalan admin, lalu buat pengajuan baru pada triwulan berjalan.'
                : 'Draft sudah melewati triwulan aktif. Silakan buat pengajuan baru pada triwulan berjalan.');
        }
    }

    private function checkState(PencairanPengajuan $p, string $operation, array $actor): void
    {
        abort_unless($actor['role'] === 'admin' || $p->kode_pp === $actor['kode_pp'], 403, 'Akses PP ditolak.');
        $admin = in_array($operation, ['return', 'verify', 'complete'], true);
        if ($admin) { abort_unless($actor['role'] === 'admin', 403, 'Tindakan tidak diizinkan untuk peran ini.'); }
        if (in_array($operation, ['update', 'submit'], true)) {
            abort_unless($actor['kode_pp'] !== '' && $p->kode_pp === $actor['kode_pp'], 403, 'Pengajuan hanya dapat diubah atau disubmit untuk PP sendiri.');
        }
        $allowed = match ($operation) {
            'update', 'submit' => ['draft', 'returned'], 'return' => ['submitted', 'verified'],
            'verify' => ['submitted'], 'complete' => ['verified'],
            'cancel' => $actor['role'] === 'admin' ? ($p->kode_pp === $actor['kode_pp'] ? ['draft', 'submitted', 'returned'] : ['submitted', 'returned']) : ['draft', 'returned'],
        };
        abort_unless(in_array($p->status, $allowed, true), 409, 'Status telah berubah atau transisi tidak diizinkan. Muat ulang pengajuan.');
    }

    private function history(PencairanPengajuan $p, array $actor, PencairanActor $local, string $action, ?string $from, ?string $remark = null): void
    {
        $p->histories()->create(['revision_no' => $p->revision_no ?: null, 'action' => $action,
            'from_status' => $from, 'to_status' => $p->status, 'actor_id' => $local->id,
            'actor_name' => $actor['name'], 'actor_role' => $actor['role'], 'remark' => $remark, 'created_at' => now()]);
    }

    private function response(PencairanPengajuan $p): array
    {
        return ['id' => $p->id, 'lock_version' => $p->lock_version, 'redirect_url' => '/pencairan/pengajuan/'.$p->id];
    }

    public function store(Request $request): array
    {
        $actor = $this->actor($request);
        abort_unless($actor['kode_pp'] !== '', 403, 'PP akun belum tersedia untuk membuat pengajuan.');
        $data = $this->validatePayload($request, 'store');
        [$records, $totals] = $this->calculate($data['jenis'], $data['details']);
        $p = new PencairanPengajuan(['jenis' => $data['jenis'], 'status' => 'draft', 'kode_pp' => $actor['kode_pp'], 'nama_pp' => $actor['nama_pp'],
            'tahun_anggaran' => now()->year, 'triwulan' => (int) ceil(now()->month / 3), 'drk_source_id' => $data['drk_id'] ?? null,
            'uraian_pengajuan' => $data['uraian_pengajuan'] ?? null, 'lock_version' => 0, 'revision_no' => 0] + $totals);
        $p->header_snapshot = $this->drk($p, false);
        return DB::transaction(function () use ($p, $records, $actor) {
            // A retry must insert a fresh model, not reuse an instance marked persisted before rollback.
            $draft = clone $p;
            $local = $this->localActor($actor);
            $draft->created_by_actor_id = $local->id; $draft->save();
            $draft->details()->createMany($records);
            $this->history($draft, $actor, $local, 'create', null);
            return $this->response($draft);
        }, 5);
    }

    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        if (!array_is_list($value)) { ksort($value); }
        return array_map(fn ($v) => $this->canonical($v), $value);
    }

    private function payloadHash(Request $r, string $operation): string
    {
        $payload = $r->except(['_token', 'idempotency_key', 'attachments']);
        if ($operation === 'complete') {
            $payload['attachments'] = array_map(fn ($f) => ['name' => $f->getClientOriginalName(), 'hash' => hash_file('sha256', $f->getRealPath())], $r->file('attachments', []));
        }
        return hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
    }

    private function retryResponse(PencairanPengajuan $p, array $actor, string $operation, array $data, ?string $hash): ?array
    {
        if ($hash === null) { return null; }
        $saved = DB::table('pengajuan_idempotencies')->join('pengajuan_actors', 'pengajuan_actors.id', '=', 'pengajuan_idempotencies.actor_id')
            ->where('source_key', $actor['source_key'])->where('pengajuan_id', $p->id)->where('operation', $operation)->where('key', $data['idempotency_key'])
            ->select('pengajuan_idempotencies.*')->first();
        if (!$saved) { return null; }
        abort_unless(hash_equals($saved->payload_hash, $hash), 409, 'Idempotency key telah digunakan dengan payload berbeda.');
        return json_decode($saved->response, true, 512, JSON_THROW_ON_ERROR);
    }

    public function update(Request $r, PencairanPengajuan $p): array { return $this->act($r, $p, 'update'); }
    public function submit(Request $r, PencairanPengajuan $p): array { return $this->act($r, $p, 'submit'); }
    public function returnToUser(Request $r, PencairanPengajuan $p): array { return $this->act($r, $p, 'return'); }
    public function verify(Request $r, PencairanPengajuan $p): array { return $this->act($r, $p, 'verify'); }
    public function cancel(Request $r, PencairanPengajuan $p): array { return $this->act($r, $p, 'cancel'); }
    public function complete(Request $r, PencairanPengajuan $p): array { return $this->act($r, $p, 'complete'); }

    private function act(Request $request, PencairanPengajuan $p, string $operation): array
    {
        $actor = $this->authorize($request, $p);
        $data = $this->validatePayload($request, $operation);
        $hash = in_array($operation, ['submit', 'complete'], true) ? $this->payloadHash($request, $operation) : null;
        if ($response = $this->retryResponse($p, $actor, $operation, $data, $hash)) { return $response; }
        $this->checkState($p, $operation, $actor);
        abort_unless($p->lock_version === (int) $data['lock_version'], 409, 'Pengajuan telah berubah. Muat ulang sebelum mencoba lagi.');
        $records = null; $totals = null; $drk = null;
        if ($operation === 'update') {
            if (isset($data['jenis']) && $data['jenis'] !== $p->jenis) { PencairanDecimal::invalid('jenis', 'Jenis draft tidak dapat diganti.'); }
            [$records, $totals] = $this->calculate($p->jenis, $data['details']);
            $candidate = clone $p;
            $candidate->drk_source_id = $data['drk_id'] ?? null;
            // A previously selected DRK is retained offline when saving revisions without changing it.
            $drk = $candidate->drk_source_id === $p->drk_source_id ? $p->header_snapshot : $this->drk($candidate, false);
        }
        if (in_array($operation, ['submit', 'verify'], true)) {
            if ($operation === 'submit') { $this->checkPeriod($p); }
            [, $totals] = $this->calculate($p->jenis, $p->detailRows(), true);
            $candidate = clone $p; $candidate->fill($totals);
            if (trim($p->uraian_pengajuan ?? '') === '') { PencairanDecimal::invalid('uraian_pengajuan', 'Uraian pengajuan wajib diisi sebelum submit.'); }
            $drk = $this->drk($candidate, true);
        }
        if ($operation === 'cancel' && $actor['role'] === 'admin' && trim($data['remark'] ?? '') === '') { PencairanDecimal::invalid('remark', 'Alasan pembatalan admin wajib diisi.'); }
        if ($operation === 'complete') {
            $date = $data['completion_date'];
            if (!$p->verified_at || $date < $p->verified_at->timezone(config('app.timezone'))->toDateString() || $date > now()->toDateString()) {
                PencairanDecimal::invalid('completion_date', 'Tanggal proses harus sejak verifikasi aktif sampai hari ini.');
            }
        }
        $uploads = [];
        try {
            return DB::transaction(function () use ($request, $p, $operation, $actor, $data, $hash, $records, $totals, $drk, &$uploads) {
                // Compensate storage from a rolled-back deadlock attempt before retrying.
                foreach ($uploads as [$disk, $path]) { Storage::disk($disk)->delete($path); }
                $uploads = [];
                // Write first: SQLite must acquire its writer lock before any transaction SELECT,
                // avoiding a deferred read transaction upgrading to a conflicting write lock.
                $updated = PencairanPengajuan::whereKey($p->id)->where('lock_version', (int) $data['lock_version'])->update(['lock_version' => DB::raw('lock_version + 1')]);
                if ($updated !== 1 && ($response = $this->retryResponse($p, $actor, $operation, $data, $hash))) { return $response; }
                abort_unless($updated === 1, 409, 'Pengajuan telah berubah. Muat ulang sebelum mencoba lagi.');
                $current = PencairanPengajuan::findOrFail($p->id);
                $this->checkState($current, $operation, $actor);
                if ($operation === 'submit') { $this->checkPeriod($current); }
                $local = $this->localActor($actor); $from = $current->status;
                if ($totals) { $current->fill($totals); }
                if ($operation === 'update') {
                    $current->fill(['drk_source_id' => $data['drk_id'] ?? null, 'uraian_pengajuan' => $data['uraian_pengajuan'] ?? null, 'header_snapshot' => $drk]);
                    $current->details()->delete(); $current->details()->createMany($records);
                }
                if ($drk && in_array($operation, ['submit', 'verify'], true)) {
                    $current->header_snapshot = $drk;
                    foreach (['saldo_awal', 'anggaran_terpakai', 'saldo_terkini'] as $key) { $current->{$key.'_snapshot'} = $drk[$key.'_decimal']; }
                    $current->saldo_checked_at = $drk['checked_at'];
                }
                switch ($operation) {
                    case 'submit':
                        if (!$current->nomor_pengajuan) {
                            $year = now()->year;
                            DB::table('pengajuan_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
                            DB::table('pengajuan_number_sequences')->where('year', $year)->increment('last_number');
                            $number = DB::table('pengajuan_number_sequences')->where('year', $year)->value('last_number');
                            $current->tahun_nomor = $year; $current->nomor_urut = $number;
                            $current->nomor_pengajuan = str_pad((string) $number, 6, '0', STR_PAD_LEFT).'/'.$year.'/'.now()->format('dm').'/'.$current->kode_pp;
                            $current->first_submitted_at = now();
                        }
                        $current->revision_no++; $current->last_submitted_at = now(); $current->status = 'submitted';
                        break;
                    case 'return': $current->status = 'returned'; $current->verified_at = null; $current->verified_by = null; break;
                    case 'verify': $current->status = 'verified'; $current->verified_at = now(); $current->verified_by = $local->id; break;
                    case 'cancel': $current->status = 'cancelled'; $current->cancelled_at = now(); $current->cancelled_by = $local->id; break;
                    case 'complete':
                        $current->status = 'completed'; $current->completed_at = now(); $current->completed_by = $local->id;
                        $completion = $current->completion()->create(['completion_date' => $data['completion_date'], 'notes' => $data['notes'] ?? null, 'actor_id' => $local->id]);
                        foreach ($request->file('attachments', []) as $file) {
                            $disk = config('pencairan.attachment_disk');
                            abort_if($disk === 'public' || config('filesystems.disks.'.$disk.'.visibility') === 'public', 503, 'Lampiran harus disimpan pada disk private.');
                            $path = $file->store('pencairan/'.$current->id, $disk);
                            if (!$path) { throw new \RuntimeException('Lampiran tidak dapat disimpan.'); }
                            $uploads[] = [$disk, $path];
                            $completion->attachments()->create(['disk' => $disk, 'path' => $path,
                                'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                                'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $local->id]);
                        }
                        break;
                }
                $current->save();
                if ($operation === 'submit') {
                    $current->revisions()->create(['revision_no' => $current->revision_no, 'submitted_by' => $local->id, 'submitted_at' => now(), 'schema_version' => 1,
                        'snapshot' => ['header' => $current->only(['jenis', 'kode_pp', 'nama_pp', 'tahun_anggaran', 'triwulan', 'drk_source_id', 'nomor_pengajuan', 'uraian_pengajuan']),
                            'details' => $current->detailRows(),
                            'detail_values' => $current->details->map(fn ($row) => $row->only(['urutan', 'volume', 'tarif', 'subtotal_manual', 'subtotal_efektif', 'nominal', 'pajak', 'neto']))->all(),
                            'totals' => $totals, 'budget' => $drk]]);
                }
                $this->history($current, $actor, $local, $operation === 'update' ? 'save' : $operation, $from, $data['remark'] ?? null);
                $response = $this->response($current);
                if ($hash !== null) { DB::table('pengajuan_idempotencies')->insert(['actor_id' => $local->id, 'pengajuan_id' => $current->id, 'operation' => $operation, 'key' => $data['idempotency_key'], 'payload_hash' => $hash, 'response' => json_encode($response), 'created_at' => now(), 'updated_at' => now()]); }
                return $response;
            }, 5);
        } catch (\Throwable $e) {
            foreach ($uploads as [$disk, $path]) { Storage::disk($disk)->delete($path); }
            throw $e;
        }
    }
}