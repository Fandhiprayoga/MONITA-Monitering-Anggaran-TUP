@php
    $resultBadges = [
        'tepat_waktu' => ['Tepat waktu', 'text-bg-success'],
        'terlambat' => ['Melewati SLA', 'text-bg-danger'],
        'data_missing' => ['Tidak dapat dihitung', 'text-bg-secondary'],
    ];
@endphp
<div class="text-nowrap">{{ isset($measurement['duration_days']) ? $measurement['duration_days'].' hari kerja' : '—' }}</div>
@if ($measurement)
    @php([$label, $class] = $resultBadges[$measurement['result']])
    <span class="badge text-wrap {{ $class }}">{{ $label }}</span>
    @if ($measurement['problem'])
        <small class="d-block text-muted">{{ $measurement['problem'] }}</small>
    @endif
@endif
@if ($active)
    <small class="d-block {{ $active['status'] === 'terlambat' ? 'text-danger' : 'text-muted' }}">
        @switch($active['status'])
            @case('terlambat') Masih berjalan — lewat tenggat @break
            @case('data_missing') Masih berjalan — waktu mulai tidak tersedia @break
            @default Masih berjalan
        @endswitch
    </small>
@endif
