@php
    $resultClasses = [
        'tepat_waktu' => 'text-bg-success',
        'terlambat' => 'text-bg-danger',
        'data_missing' => 'text-bg-secondary',
    ];
    $duration = \App\Services\PencairanSlaReportPresentation::duration($measurement);
@endphp
<div class="text-nowrap">{{ $duration !== null ? $duration.' hari kerja' : '—' }}</div>
@if ($measurement)
    <span class="badge text-wrap {{ $resultClasses[$measurement['result']] }}">{{ \App\Services\PencairanSlaReportPresentation::resultLabel($measurement['result']) }}</span>
    @if (isset($measurement['target_days']))
        <small class="d-block text-muted text-nowrap">Target: {{ $measurement['target_days'] }} hari kerja</small>
    @endif
    @if ($measurement['problem'])
        <small class="d-block text-muted">{{ $measurement['problem'] }}</small>
    @endif
@endif
@if ($active)
    <small class="d-block {{ $active['status'] === 'terlambat' ? 'text-danger' : 'text-muted' }}">
        {{ \App\Services\PencairanSlaReportPresentation::activeLabel($active['status']) }}
    </small>
    @if (isset($active['target_days']))
        <small class="d-block text-muted text-nowrap">Target aktif: {{ $active['target_days'] }} hari kerja</small>
    @endif
@endif
