<h6 class="mb-3">Informasi Anggaran</h6>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div id="drk-search-controls">
        <label for="cari-drk" class="form-label">Cari DRK</label>
        <input id="cari-drk" type="search" class="form-control mb-2"
            placeholder="Ketik kode DRK, kode/nama akun, uraian, atau tipe anggaran..."
            aria-controls="pilih-drk" aria-describedby="hasil-cari-drk"
            @disabled($drkError || count($drkOptions) === 0)>
        </div>
        <label for="pilih-drk" class="form-label">Pilih DRK <span class="text-danger">*</span></label>
        <select id="pilih-drk" name="drk_id" class="form-select" required
            aria-describedby="drk-info" @disabled($drkError || count($drkOptions) === 0)>
            <option value="">-- Pilih DRK --</option>
            @foreach ($drkOptions as $option)
                <option value="{{ $option['id'] }}" data-header="{{ json_encode($option) }}">
                    {{ $option['drk_tup'] }} - SIMKUG: {{ $option['kode_drk'] ?: 'Belum tersedia' }} - {{ $option['kode_akun'] }} - {{ $option['nama_akun'] }} - {{ $option['uraian_drk'] }} ({{ $option['tipe'] }})
                </option>
            @endforeach
        </select>
        <small id="drk-info" class="text-muted">
            <span id="hasil-cari-drk" role="status" aria-live="polite">{{ count($drkOptions) }} DRK ditemukan</span>
        </small>
        @if ($drkError)
            <div data-drk-source-message class="alert alert-danger mt-2 mb-0" role="alert">{{ $drkError }}</div>
        @elseif (count($drkOptions) === 0)
            <div data-drk-source-message class="alert alert-info mt-2 mb-0" role="status">Tidak ada DRK untuk unit yang dipilih pada triwulan ini.</div>
        @endif
        <div data-header-error class="alert alert-danger mt-2 mb-0 d-none" role="alert"></div>
    </div>
    <input id="kode-akun" name="kode_akun" type="hidden">
    <input id="nama-akun" name="nama_akun" type="hidden">
    <input id="kode-pp" name="kode_pp" type="hidden">
    <input id="nama-pp" name="nama_pp" type="hidden">
    <input id="kode-drk" name="kode_drk" type="hidden">
    <input id="uraian-drk" name="uraian_drk" type="hidden">
    @foreach (['saldo_awal' => 'Saldo Awal TW', 'anggaran_terpakai' => 'Anggaran Terpakai TW', 'saldo_terkini' => 'Saldo Terkini TW'] as $field => $label)
        <div class="col-md-4">
            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" class="form-control bg-light" type="text" readonly
                placeholder="Pilih DRK terlebih dahulu" aria-describedby="saldo-info">
        </div>
    @endforeach
    <div class="col-12">
        <small id="saldo-info" class="text-muted">
            Saldo Awal = Anggaran, Anggaran Terpakai = Realisasi, Saldo Terkini = Saldo pada data unit TW {{ $activeTw }} (Rp).
        </small>
    </div>
    <div class="col-12">
        <label for="uraian-pengajuan" class="form-label">Uraian Pengajuan (Sebagai Narasi Input SIMKUG)</label>
        <textarea id="uraian-pengajuan" name="uraian_pengajuan" class="form-control" rows="3"></textarea>
    </div>
    <div class="col-md-6">
        <label for="nominal-diajukan" class="form-label">Nominal Diajukan (Rp)</label>
        <input id="nominal-diajukan" class="form-control bg-light fw-bold" type="text" value="0" readonly>
        <small class="text-muted">{{ $jenis !== 'impresfund' ? 'Total nominal sebelum pajak (bruto).' : 'Total subtotal seluruh rincian.' }}</small>
    </div>
</div>
