@extends('layouts.app')

@section('title', ($jenisPenghasilan->exists ? 'Ubah' : 'Tambah') . ' Jenis Penghasilan | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">{{ $jenisPenghasilan->exists ? 'Ubah Jenis Penghasilan' : 'Tambah Jenis Penghasilan' }}</h5>
            <a href="{{ route('jenis-penghasilan.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Kembali
            </a>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                action="{{ $jenisPenghasilan->exists ? route('jenis-penghasilan.update', $jenisPenghasilan) : route('jenis-penghasilan.store') }}">
                @csrf
                @if ($jenisPenghasilan->exists)
                    @method('PATCH')
                @endif

                <div class="mb-3">
                    <label for="kode-jenis" class="form-label">Kode Jenis</label>
                    <input id="kode-jenis" name="kode_jenis" class="form-control" maxlength="30"
                        value="{{ old('kode_jenis', $jenisPenghasilan->kode_jenis) }}" required>
                </div>
                <div class="mb-3">
                    <label for="nama-jenis-pph" class="form-label">Nama Jenis PPh</label>
                    <input id="nama-jenis-pph" name="nama_jenis_pph" class="form-control" maxlength="255"
                        value="{{ old('nama_jenis_pph', $jenisPenghasilan->nama_jenis_pph) }}" required>
                </div>
                <div class="mb-3">
                    <label for="tarif-pph" class="form-label">Tarif PPh (%)</label>
                    <input id="tarif-pph" name="tarif_pph" type="number" class="form-control"
                        min="0" max="100" step="0.01"
                        value="{{ old('tarif_pph', $jenisPenghasilan->tarif_pph) }}" required>
                    <small class="form-text text-muted">Isi 0 sampai 100 persen, maksimal dua angka desimal.</small>
                </div>
                <div class="mb-4">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3"
                        maxlength="10000">{{ old('deskripsi', $jenisPenghasilan->deskripsi) }}</textarea>
                    <small class="form-text text-muted">Opsional, maksimal 10.000 karakter.</small>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('jenis-penghasilan.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        {{ $jenisPenghasilan->exists ? 'Simpan Perubahan' : 'Tambah' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
