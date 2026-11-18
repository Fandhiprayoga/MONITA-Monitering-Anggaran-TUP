<div class="row">
    @foreach ([
        ['total', 'Total Pengajuan', 'bg-secondary-dark', 'ti ti-list-check'],
        ['tepat_waktu', 'Tepat Waktu', 'bg-teal-900', 'ti ti-circle-check'],
        ['terlambat', 'Melewati SLA', 'bg-primary-dark', 'ti ti-alert-circle'],
        ['undetermined', 'Belum Dapat Disimpulkan', 'bg-yellow-900', 'ti ti-clock'],
    ] as [$key, $label, $class, $icon])
        <div class="col-6 col-xl-3">
            <div class="card {{ $class }} dashnum-card text-white overflow-hidden">
                <span class="round small"></span>
                <span class="round big"></span>
                <div class="card-body">
                    <div class="bg-dark bg-opacity-25 rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="{{ $icon }} text-white fs-3" aria-hidden="true"></i>
                    </div>
                    <div class="fs-2 fw-semibold mt-2">{{ $summary[$key] }}</div>
                    <p class="mb-0 opacity-75">{{ $label }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>
<p class="small text-muted">Tepat waktu: pengajuan selesai dan memenuhi kedua target. Melewati SLA: setidaknya satu hasil tahap terlambat.</p>
<h6>Kepatuhan SLA per Tahap</h6>
<div class="table-responsive">
    <table class="table table-bordered align-middle mb-0" aria-label="Ringkasan SLA per tahap">
        <thead>
            <tr>
                <th scope="col">Tahap</th>
                <th scope="col">Target hari kerja</th>
                <th scope="col">Tepat waktu</th>
                <th scope="col">Melewati SLA</th>
                <th scope="col">Masih berjalan</th>
                <th scope="col">Tidak dapat dihitung</th>
                <th scope="col">Kepatuhan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stages as $stage => $label)
                @php($metrics = $stageSummary[$stage])
                <tr>
                    <th scope="row">{{ $label }}</th>
                    <td>{{ $metrics['target_days'] }}</td>
                    <td>{{ $metrics['tepat_waktu'] }}</td>
                    <td>{{ $metrics['terlambat'] }}</td>
                    <td>
                        {{ $metrics['active'] }}
                        @if ($metrics['active_overdue'] > 0)
                            <small class="d-block text-danger">{{ $metrics['active_overdue'] }} lewat tenggat</small>
                        @endif
                    </td>
                    <td>{{ $metrics['data_missing'] }}</td>
                    <td>{{ $metrics['compliance'] !== null ? number_format($metrics['compliance'], 1, ',', '.').'%' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="small text-muted mt-3 mb-0">Proses aktif tidak masuk kepatuhan. Pengajuan yang di-submit ulang dapat memiliki hasil verifikasi sebelumnya sekaligus proses verifikasi yang masih berjalan.</p>
