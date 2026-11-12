@extends('layouts.app')

@section('title', 'Master Data Bank | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Master Data Bank</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBank">
                <i class="ti ti-plus me-1" aria-hidden="true"></i>Tambah Bank
            </button>
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
                                <td>{{ $bank->nama_bank }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $bank->status === 'aktif' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $bank->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('bank.index', ['edit' => $bank->id]) }}"
                                        class="btn btn-sm btn-icon btn-light-warning"
                                        aria-label="Ubah {{ $bank->nama_bank }}" title="Ubah">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
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

    <div class="modal fade" id="modalBank" tabindex="-1" aria-labelledby="modalBankTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ $editingBank->exists
                ? route('bank.update', $editingBank)
                : route('bank.store') }}" class="modal-content">
                @csrf
                @if ($editingBank->exists)
                    @method('PATCH')
                @endif
                <div class="modal-header bg-secondary">
                    <h5 class="modal-title text-white" id="modalBankTitle">
                        {{ $editingBank->exists ? 'Ubah Bank' : 'Tambah Bank' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="kode-bank" class="form-label">Kode Bank</label>
                        <input id="kode-bank" name="kode_bank" class="form-control" maxlength="30"
                            value="{{ old('kode_bank', $editingBank->kode_bank) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama-bank" class="form-label">Nama Bank</label>
                        <input id="nama-bank" name="nama_bank" class="form-control" maxlength="255"
                            value="{{ old('nama_bank', $editingBank->nama_bank) }}" required>
                    </div>
                    <div>
                        <label for="status-bank" class="form-label">Status</label>
                        <select id="status-bank" name="status" class="form-select" required>
                            <option value="aktif" @selected(old('status', $editingBank->status) === 'aktif')>Aktif</option>
                            <option value="nonaktif" @selected(old('status', $editingBank->status) === 'nonaktif')>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        {{ $editingBank->exists ? 'Simpan Perubahan' : 'Tambah' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($editingBank->exists || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBank')).show();
                });
            </script>
        @endpush
    @endif
@endsection
