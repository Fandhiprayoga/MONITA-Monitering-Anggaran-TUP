@extends('layouts.app')

@section('title', 'Jenis Penghasilan | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Daftar Jenis Penghasilan</h5>
            <a href="{{ route('jenis-penghasilan.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1" aria-hidden="true"></i>Tambah Jenis Penghasilan
            </a>
        </div>

        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="bg-secondary text-center">
                        <tr>
                            <th scope="col" class="text-white">No</th>
                            <th scope="col" class="text-white">Kode Jenis</th>
                            <th scope="col" class="text-white">Nama Jenis PPh</th>
                            <th scope="col" class="text-white">Tarif PPh</th>
                            <th scope="col" class="text-white">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jenisPenghasilans as $jenisPenghasilan)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $jenisPenghasilan->kode_jenis }}</td>
                                <td>{{ $jenisPenghasilan->nama_jenis_pph }}</td>
                                <td class="text-end">{{ number_format((float) $jenisPenghasilan->tarif_pph, 2, ',', '.') }}%</td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('jenis-penghasilan.edit', $jenisPenghasilan) }}"
                                        class="btn btn-sm btn-icon btn-light-warning"
                                        aria-label="Ubah {{ $jenisPenghasilan->nama_jenis_pph }}" title="Ubah">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('jenis-penghasilan.destroy', $jenisPenghasilan) }}"
                                        class="d-inline" onsubmit="return confirm('Hapus jenis penghasilan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-light-secondary"
                                            aria-label="Hapus {{ $jenisPenghasilan->nama_jenis_pph }}" title="Hapus">
                                            <i class="ti ti-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="ti ti-database-off f-30" aria-hidden="true"></i>
                                        <span>Belum ada data jenis penghasilan.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
