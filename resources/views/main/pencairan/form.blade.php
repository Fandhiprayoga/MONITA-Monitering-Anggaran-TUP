@extends('layouts.app')

@section('title', 'Form ' . $jenisNama . ' | MONITA')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/monita/pencairan-grid.css') }}">
    @if (in_array($jenis, ['impresfund', 'beban', 'honor'], true))
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
        <style>
            .unit-select2 .select2-selection--single {
                min-height: 44px;
                border: 1px solid #ced4da;
                border-radius: 8px;
                padding: 0.65rem 2rem 0.65rem 0.75rem;
            }
            .unit-select2 .select2-selection--single .select2-selection__rendered {
                padding: 0;
                line-height: 21px;
                color: #212529;
            }
            .unit-select2 .select2-selection--single .select2-selection__arrow {
                height: 42px;
                right: 8px;
            }
            .unit-select2.select2-container--focus .select2-selection--single {
                border-color: #2689e2;
                box-shadow: 0 0 0 1px var(--bs-primary);
            }
        </style>
    @endif
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0">Form Pencairan Anggaran - {{ $jenisNama }}</h5>
            <button id="grid-help-button" type="button" class="btn btn-sm btn-outline-secondary"
                data-bs-toggle="modal" data-bs-target="#grid-help-modal" aria-controls="grid-help-modal"
                aria-haspopup="dialog">Bantuan</button>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label">Triwulan</label>
                    <input type="text" class="form-control" value="TW {{ $activeTw }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Pengajuan</label>
                    <input type="text" class="form-control" value="{{ isset($pengajuan) ? $pengajuan->created_at->format('d M Y') : now()->format('d M Y') }}" readonly>
                </div>
                <div class="col-md-4">
                    <label for="pilih-unit" class="form-label">Unit</label>
                    @if (($canChooseUnit ?? false) && !isset($pengajuan))
                        <select id="pilih-unit" name="unit_kode_pp" form="form-pencairan" class="form-select"
                            data-drk-url="{{ route('pencairan.drk-options') }}" @disabled($unitError)>
                            <option value="">-- Pilih Unit --</option>
                            @foreach ($unitOptions as $code => $name)
                                <option value="{{ $code }}" @selected($selectedUnit === (string) $code)>{{ $code }} - {{ $name }}</option>
                            @endforeach
                        </select>
                        @if ($unitError)
                            <div class="alert alert-danger mt-2" role="alert">{{ $unitError }}</div>
                        @elseif (count($unitOptions) === 0)
                            <div class="alert alert-warning mt-2" role="alert">Daftar unit pada sheet users kosong.</div>
                        @endif
                    @else
                    <input type="text" class="form-control"
                        value="{{ $selectedUnitName ?? session('user_data.nama_pp') ?? session('user_data.username', '-') }}" readonly>
                    @endif
                </div>
            </div>

            @if (in_array($jenis, ['impresfund', 'beban', 'honor'], true))
                <div id="save-message" class="alert d-none" role="alert"></div>
                <form id="form-pencairan" data-jenis="{{ $jenis }}" data-menu="{{ $activeMenu ?? 'pengajuan' }}" novalidate>
                    @if (isset($pengajuan))
                        <input type="hidden" name="unit_kode_pp" value="{{ $pengajuan->kode_pp }}">
                    @endif
                    @csrf
                    @include('main.pencairan.partials.header')
                    @include('main.pencairan.partials.detail-' . $jenis)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
                        <a href="{{ $backUrl ?? route('pencairan.pengajuan', ['jenis' => $jenis]) }}" class="btn btn-outline-secondary">Kembali</a>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" data-save-draft class="btn btn-outline-primary">Simpan {{ isset($pengajuan) && $pengajuan->status === 'returned' ? 'Perbaikan' : 'sebagai Draft' }}</button>
                            <button type="submit" class="btn btn-primary">Submit Pengajuan</button>
                        </div>
                    </div>
                    <p id="pengajuan-actions-info" class="text-muted small text-end mt-2 mb-0">
                        Perubahan belum tersimpan sampai tombol simpan berhasil. Isi terkunci setelah submit.
                    </p>
                </form>
                <noscript><div class="alert alert-danger mt-3">Aktifkan JavaScript untuk mengisi rincian dan menghitung total.</div></noscript>
            @else
                <div class="alert alert-info mb-0">
                    Formulir pengajuan pencairan {{ $jenisNama }} belum terhubung ke backend.
                    Halaman ini siap diisi setelah data pencairan tersedia.
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @if (in_array($jenis, ['impresfund', 'beban', 'honor'], true))
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/id.min.js"></script>
        <script type="module">
            import { mountGrid } from "{{ asset('js/monita/pencairan-grid.js') }}";
            import { mountBudgetHeader } from "{{ asset('js/monita/pencairan-header.js') }}";
            import { mountSubmission } from "{{ asset('js/monita/pencairan-submit.js') }}";
            const form = document.getElementById('form-pencairan');
            const initial = {{ Illuminate\Support\Js::from(isset($pengajuan) ? ['uuid' => $pengajuan->uuid, 'lock_version' => $pengajuan->lock_version, 'drk_id' => $pengajuan->drk_source_id, 'uraian_pengajuan' => $pengajuan->uraian_pengajuan, 'details' => $pengajuan->detailRows()] : null) }};
            const jenisPenghasilanOptions = {{ Illuminate\Support\Js::from($jenisPenghasilanOptions ?? []) }};
            if (initial) {
                form.querySelector('#pilih-drk').value = initial.drk_id ?? '';
                form.querySelector('#uraian-pengajuan').value = initial.uraian_pengajuan ?? '';
            }
            mountBudgetHeader(form);
            const grid = mountGrid(form, { jenisPenghasilanOptions });
            if (initial) grid.load(initial.details);
            mountSubmission(form, grid, initial, {{ Illuminate\Support\Js::from(route('pencairan.store')) }});
            if (typeof window.jQuery?.fn.select2 === 'function') {
                for (const [id, placeholder] of [['pilih-unit', '-- Pilih Unit --'], ['pilih-drk', '-- Pilih DRK --']]) {
                    const element = document.getElementById(id);
                    if (!element) continue;
                    const selector = window.jQuery(element).select2({
                        width: '100%',
                        placeholder,
                        language: 'id',
                    });
                    selector.next('.select2-container').addClass('unit-select2');
                    selector.on('select2:select', () => {
                        element.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    if (id === 'pilih-drk') {
                        document.getElementById('drk-search-controls').hidden = true;
                        element.addEventListener('drk:options-updated', () => {
                            selector.select2('close');
                            selector.trigger('change.select2');
                        });
                    }
                }
            } else {
                console.error('Select2 untuk pilihan unit/DRK gagal dimuat.');
                const message = document.getElementById('save-message');
                message.className = 'alert alert-warning';
                message.textContent = 'Pencarian Select2 gagal dimuat. Pilihan biasa dan kolom Cari DRK tetap dapat digunakan.';
            }
        </script>
    @endif
@endpush
