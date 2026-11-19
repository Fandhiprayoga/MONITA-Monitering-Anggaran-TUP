@extends('layouts.app')

@section('title', 'Target SLA | MONITA')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Target SLA Pencairan</h5>
            <p class="text-muted mb-0">Atur target hari kerja tiap tahap untuk Monitoring SLA dan Report SLA.</p>
        </div>
        <div class="card-body">
            @include('main.pencairan.partials.feedback')
            <h6>Target yang berlaku hari ini</h6>
            <dl class="row mb-3">
                @foreach ($stages as $stage => $label)
                    <div class="col-6 col-lg-3 mb-2">
                        <dt>{{ $label }}</dt>
                        <dd class="mb-0">{{ $activeTargets[$stage] }} hari kerja</dd>
                    </div>
                @endforeach
            </dl>
            <hr>
            <h6>Jadwalkan target baru</h6>
            <p class="text-muted">Target baru hanya berlaku pada tahap yang dimulai sejak tanggal efektif ({{ config('app.timezone') }}). Tahap yang sudah berjalan atau selesai tetap memakai target sebelumnya.</p>
            <form method="POST" action="{{ route('settings.sla.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="effective_from">Tanggal mulai berlaku</label>
                        <input class="form-control" id="effective_from" name="effective_from" type="date"
                            value="{{ old('effective_from', $today->addDay()->toDateString()) }}" min="{{ $today->addDay()->toDateString() }}" required>
                    </div>
                    <div class="col-12">
                        <div class="row g-3">
                            @foreach ($stages as $stage => $label)
                                <div class="col-sm-6 col-lg-3">
                                    <label class="form-label" for="{{ $stage }}">{{ $label }}</label>
                                    <div class="input-group">
                                        <input class="form-control" id="{{ $stage }}" name="{{ $stage }}" type="number" min="1" max="365" step="1"
                                            value="{{ old($stage, $activeTargets[$stage]) }}" required>
                                        <span class="input-group-text">hari kerja</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <p class="small text-muted mt-3 mb-2">Setiap jadwal berisi empat target. Jadwal yang disimpan tidak dapat ditimpa.</p>
                <button class="btn btn-primary" type="submit">Simpan Jadwal</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat Target SLA</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Mulai berlaku</th>
                            @foreach ($stages as $label)
                                <th scope="col">{{ $label }}</th>
                            @endforeach
                            <th scope="col">Dijadwalkan oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($policies as $policy)
                            <tr>
                                <td class="text-nowrap">
                                    {{ \Carbon\CarbonImmutable::parse($policy->effective_from)->format('d/m/Y') }}
                                    @if ($policy->effective_from > $today->toDateString())
                                        <span class="badge text-bg-info">Terjadwal</span>
                                    @endif
                                </td>
                                @foreach ($stages as $stage => $label)
                                    <td>{{ $policy->{$stage} }} hari kerja</td>
                                @endforeach
                                <td>{{ $policy->created_by }}</td>
                            </tr>
                        @endforeach
                        @if (! $policies->hasMorePages())
                            <tr>
                                <td>Sebelum jadwal pertama (target awal)</td>
                                @foreach ($initialTargets as $days)
                                    <td>{{ $days }} hari kerja</td>
                                @endforeach
                                <td>Sistem</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $policies->links() }}</div>
        </div>
    </div>
@endsection
