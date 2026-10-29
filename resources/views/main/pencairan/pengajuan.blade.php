@extends('layouts.app')

@section('title', 'Pengajuan | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Pengajuan Pencairan Anggaran</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPilihForm">
                <i class="ti ti-plus me-1"></i> Tambah Pengajuan
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Tanggal</th>
                            <th>Triwulan</th>
                            <th>Jenis</th>
                            <th>Uraian</th>
                            <th class="text-end">Nominal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Belum ada pengajuan. Klik "Tambah Pengajuan" untuk memilih formulir.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Pilih Form -->
    <div class="modal fade" id="modalPilihForm" tabindex="-1" aria-labelledby="modalPilihFormLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPilihFormLabel">Pilih Formulir Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="{{ route('pencairan.form', 'impresfund') }}"
                            class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="ti ti-wallet fs-4 text-primary me-3"></i>
                            <div>
                                <div class="fw-semibold">Form Impresfund</div>
                                <small class="text-muted">Pengajuan pencairan Impresfund</small>
                            </div>
                        </a>
                        <a href="{{ route('pencairan.form', 'beban') }}"
                            class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="ti ti-file-text fs-4 text-primary me-3"></i>
                            <div>
                                <div class="fw-semibold">Form Beban</div>
                                <small class="text-muted">Pengajuan pencairan Beban</small>
                            </div>
                        </a>
                        <a href="{{ route('pencairan.form', 'honor') }}"
                            class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="ti ti-report-money fs-4 text-primary me-3"></i>
                            <div>
                                <div class="fw-semibold">Form Honor</div>
                                <small class="text-muted">Pengajuan pencairan Honor</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
