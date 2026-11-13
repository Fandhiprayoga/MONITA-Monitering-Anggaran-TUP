@extends('layouts.app')

@section('title', ($bank->exists ? 'Ubah' : 'Tambah') . ' Bank | MONITA')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">{{ $bank->exists ? 'Ubah Bank' : 'Tambah Bank' }}</h5>
            <a href="{{ route('bank.index') }}" class="btn btn-outline-secondary">
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

            <form method="POST" action="{{ $bank->exists ? route('bank.update', $bank) : route('bank.store') }}">
                @csrf
                @if ($bank->exists)
                    @method('PATCH')
                @endif

                <div class="mb-3">
                    <label for="kode-bank" class="form-label">Kode Bank</label>
                    <input id="kode-bank" name="kode_bank" class="form-control" maxlength="30"
                        value="{{ old('kode_bank', $bank->kode_bank) }}" required>
                </div>
                <div class="mb-3">
                    <label for="inisial-bank" class="form-label">Inisial</label>
                    <input id="inisial-bank" name="inisial" class="form-control" maxlength="30"
                        value="{{ old('inisial', $bank->inisial) }}" required>
                </div>
                <div class="mb-3">
                    <label for="nama-bank" class="form-label">Nama Bank</label>
                    <input id="nama-bank" name="nama_bank" class="form-control" maxlength="255"
                        value="{{ old('nama_bank', $bank->nama_bank) }}" required>
                </div>
                <div class="mb-4">
                    <label for="status-bank" class="form-label">Status</label>
                    <select id="status-bank" name="status" class="form-select" required>
                        <option value="aktif" @selected(old('status', $bank->status) === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $bank->status) === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('bank.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        {{ $bank->exists ? 'Simpan Perubahan' : 'Tambah' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
