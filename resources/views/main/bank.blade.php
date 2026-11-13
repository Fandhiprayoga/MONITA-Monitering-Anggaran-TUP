@extends('layouts.app')

@section('title', 'Master Data Bank | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Master Data Bank</h5>
            <a href="{{ route('bank.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1" aria-hidden="true"></i>Tambah Bank
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
                            <th scope="col" class="text-white">Kode Bank</th>
                            <th scope="col" class="text-white">Inisial</th>
                            <th scope="col" class="text-white">Nama Bank</th>
                            <th scope="col" class="text-white">Status</th>
                            <th scope="col" class="text-white">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banks as $bank)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $bank->kode_bank }}</td>
                                <td>{{ $bank->inisial }}</td>
                                <td>{{ $bank->nama_bank }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $bank->status === 'aktif' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $bank->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('bank.edit', $bank) }}"
                                        class="btn btn-sm btn-icon btn-light-warning"
                                        aria-label="Ubah {{ $bank->nama_bank }}" title="Ubah">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('bank.destroy', $bank) }}"
                                        class="d-inline" onsubmit="return confirm('Hapus bank {{ $bank->nama_bank }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-light-danger"
                                            aria-label="Hapus {{ $bank->nama_bank }}" title="Hapus">
                                            <i class="ti ti-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="ti ti-database-off f-30" aria-hidden="true"></i>
                                        <span>Belum ada data bank.</span>
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
