@extends('layouts.app')

@section('title', 'Pengaturan Pengajuan Pencairan | MONITA')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Pengaturan Pengajuan Pencairan</h5>
            <p class="text-muted mb-0">Atur akses pengajuan untuk semua jenis formulir pencairan anggaran.</p>
        </div>
        <div class="card-body">
            @include('main.pencairan.partials.feedback')
            <p>Status saat ini: <span class="badge {{ $isOpen ? 'text-bg-success' : 'text-bg-warning' }}">{{ $isOpen ? 'Buka' : 'Tutup' }}</span></p>
            <p class="text-muted">Saat ditutup, pengguna biasa tidak dapat membuat, mengedit, menyimpan, atau mengirim pengajuan. Admin tetap dapat mengajukan dan memproses pengajuan yang sudah masuk.</p>
            <form method="POST" action="{{ route('settings.pencairan.update') }}">
                @csrf
                <input type="hidden" name="status" value="{{ $isOpen ? 'closed' : 'open' }}">
                <button type="submit" class="btn {{ $isOpen ? 'btn-warning' : 'btn-primary' }}"
                    onclick="return confirm('{{ $isOpen ? 'Tutup' : 'Buka' }} pengajuan pencairan anggaran?')">
                    {{ $isOpen ? 'Tutup Pengajuan' : 'Buka Pengajuan' }}
                </button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat Status Pengajuan</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead><tr><th>Waktu ({{ config('app.timezone') }})</th><th>Status sebelumnya</th><th>Status baru</th><th>Diubah oleh</th></tr></thead>
                    <tbody>
                        @forelse ($histories as $history)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($history->created_at, config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $history->from_open ? 'Buka' : 'Tutup' }}</td>
                                <td>{{ $history->to_open ? 'Buka' : 'Tutup' }}</td>
                                <td>{{ $history->actor_username }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">Belum ada perubahan status. Status awal: Buka.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $histories->links() }}
        </div>
    </div>
@endsection
