@extends('layouts.app')

@section('title', 'Transaksi ' . $jenisNama . ' | MONITA')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Transaksi Pengajuan {{ $jenisNama }}</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="bg-secondary text-white">
                        <tr class="text-center">
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Unit</th>
                            <th>Uraian</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada data transaksi {{ $jenisNama }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
