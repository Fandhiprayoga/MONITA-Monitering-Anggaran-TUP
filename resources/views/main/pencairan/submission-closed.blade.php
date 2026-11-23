@extends('layouts.app')

@section('title', 'Pengajuan Ditutup | MONITA')

@section('content')
    <div class="alert alert-warning" role="alert">
        <strong>Pengajuan sedang ditutup</strong><br>
        {{ \App\Services\PencairanSubmissionSettings::CLOSED_MESSAGE }}
    </div>
    <a href="{{ route('pencairan.pengajuan') }}" class="btn btn-outline-secondary">Kembali ke Daftar Pengajuan</a>
@endsection
