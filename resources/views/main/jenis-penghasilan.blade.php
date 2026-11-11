@extends('layouts.app')

@section('title', 'Jenis Penghasilan | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Daftar Jenis Penghasilan</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalJenisPenghasilan">
                <i class="ti ti-plus me-1" aria-hidden="true"></i>Tambah Jenis Penghasilan
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
                                    <a href="{{ route('jenis-penghasilan.index', ['edit' => $jenisPenghasilan->id]) }}"
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

    <div class="modal fade" id="modalJenisPenghasilan" tabindex="-1" aria-labelledby="modalJenisPenghasilanTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ $editingJenisPenghasilan->exists
                ? route('jenis-penghasilan.update', $editingJenisPenghasilan)
                : route('jenis-penghasilan.store') }}" class="modal-content">
                @csrf
                @if ($editingJenisPenghasilan->exists)
                    @method('PATCH')
                @endif
                <div class="modal-header bg-secondary">
                    <h5 class="modal-title text-white" id="modalJenisPenghasilanTitle">
                        {{ $editingJenisPenghasilan->exists ? 'Ubah Jenis Penghasilan' : 'Tambah Jenis Penghasilan' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="kode-jenis" class="form-label">Kode Jenis</label>
                        <input id="kode-jenis" name="kode_jenis" class="form-control" maxlength="30"
                            value="{{ old('kode_jenis', $editingJenisPenghasilan->kode_jenis) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama-jenis-pph" class="form-label">Nama Jenis PPh</label>
                        <input id="nama-jenis-pph" name="nama_jenis_pph" class="form-control" maxlength="255"
                            value="{{ old('nama_jenis_pph', $editingJenisPenghasilan->nama_jenis_pph) }}" required>
                    </div>
                    <div>
                        <label for="tarif-pph" class="form-label">Tarif PPh (%)</label>
                        <input id="tarif-pph" name="tarif_pph" type="number" class="form-control"
                            min="0" max="100" step="0.01"
                            value="{{ old('tarif_pph', $editingJenisPenghasilan->tarif_pph) }}" required>
                        <small class="form-text text-muted">Isi 0 sampai 100 persen, maksimal dua angka desimal.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        {{ $editingJenisPenghasilan->exists ? 'Simpan Perubahan' : 'Tambah' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($editingJenisPenghasilan->exists || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalJenisPenghasilan')).show();
                });
            </script>
        @endpush
    @endif
@endsection
