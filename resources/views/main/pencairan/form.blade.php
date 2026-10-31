@extends('layouts.app')

@section('title', 'Form ' . $jenisNama . ' | MONITA')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/monita/pencairan-grid.css') }}">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Form Pencairan Anggaran - {{ $jenisNama }}</h5>
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
                    <label class="form-label">Unit</label>
                    <input type="text" class="form-control"
                        value="{{ session('user_data.nama_pp') ?? session('user_data.username', '-') }}" readonly>
                </div>
            </div>

            @if (in_array($jenis, ['impresfund', 'beban', 'honor'], true))
                <div id="save-message" class="alert d-none" role="alert"></div>
                <form id="form-pencairan" data-jenis="{{ $jenis }}" novalidate>
                    @csrf
                    @include('main.pencairan.partials.header')
                    @include('main.pencairan.partials.detail-' . $jenis)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
                        <a href="{{ route('pencairan.pengajuan') }}" class="btn btn-outline-secondary">Kembali</a>
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
        <script type="module">
            import { mountGrid } from "{{ asset('js/monita/pencairan-grid.js') }}";
            import { mountBudgetHeader } from "{{ asset('js/monita/pencairan-header.js') }}";
            import { mountSubmission } from "{{ asset('js/monita/pencairan-submit.js') }}";
            const form = document.getElementById('form-pencairan');
            const initial = {{ Illuminate\Support\Js::from(isset($pengajuan) ? ['id' => $pengajuan->id, 'lock_version' => $pengajuan->lock_version, 'drk_id' => $pengajuan->drk_source_id, 'uraian_pengajuan' => $pengajuan->uraian_pengajuan, 'details' => $pengajuan->detailRows()] : null) }};
            if (initial) {
                form.querySelector('#pilih-drk').value = initial.drk_id ?? '';
                form.querySelector('#uraian-pengajuan').value = initial.uraian_pengajuan ?? '';
            }
            mountBudgetHeader(form);
            const grid = mountGrid(form);
            if (initial) grid.load(initial.details);
            mountSubmission(form, grid, initial, {{ Illuminate\Support\Js::from(route('pencairan.store')) }});
        </script>
    @endif
@endpush
