@extends('layouts.app')

@section('title', 'Verifikasi Pengajuan | MONITA')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Verifikasi Pengajuan Pencairan Anggaran</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="bg-secondary text-white">
                        <tr class="text-center">
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Unit</th>
                            <th>Jenis</th>
                            <th>Uraian</th>
                            <th>Nominal</th>
                            <th>Dokumen</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Belum ada pengajuan untuk diverifikasi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
