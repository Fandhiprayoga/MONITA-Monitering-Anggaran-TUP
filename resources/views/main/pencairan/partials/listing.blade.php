    @php
        $statusLabels = [
            'draft' => 'Draft', 'submitted' => 'Menunggu verifikasi', 'returned' => 'Perlu perbaikan',
            'verified' => 'Menunggu transaksi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan',
        ];
        $activeFilters = [];
        if (request()->filled('q')) $activeFilters['q'] = 'Pencarian: '.request('q');
        if (in_array($statusFilter, $allowedStatuses, true)) $activeFilters['status'] = 'Status: '.$statusLabels[$statusFilter];
        if ($mode !== 'transaksi' && in_array(request('jenis'), ['imprestfund', 'beban', 'honor'], true)) $activeFilters['jenis'] = ucfirst(request('jenis'));
        if (request()->filled('tahun')) $activeFilters['tahun'] = 'Tahun: '.request('tahun');
        if (in_array((string) request('tw'), ['1', '2', '3', '4'], true)) $activeFilters['tw'] = 'Triwulan '.request('tw');
        if (session('user_role') === 'admin' && request()->filled('pp')) $activeFilters['pp'] = 'Unit: '.request('pp');
        $advancedCount = count(array_intersect(array_keys($activeFilters), ['jenis', 'tahun', 'tw', 'pp']));
        $resetUrl = request()->url().'?'.($mode === 'pengajuan' ? 'tab='.($tab ?? 'aktif').'&' : '').'status=';
        $filterUnits = $allUnits;
        $selectedPp = strtoupper(trim((string) request('pp', '')));
        if ($selectedPp !== '' && !isset($filterUnits[$selectedPp])) $filterUnits[$selectedPp] = 'Unit '.$selectedPp;
    @endphp
    <style>
        .pencairan-filter-primary { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, 280px); gap: 16px; }
        .pencairan-filter-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding-top: 16px; }
        .pencairan-filters .form-label { font-weight: 500; margin-bottom: 6px; }
        .pencairan-filters .form-control, .pencairan-filters .form-select { width: 100%; min-width: 0; }
        .pencairan-tabs .nav-link { color: var(--bs-secondary-color); font-weight: 500; }
        .pencairan-tabs .nav-link:hover { color: var(--bs-body-color); }
        .pencairan-tabs .nav-link.active { color: var(--bs-primary); background-color: #fff; border-bottom-color: #fff; }
        .pencairan-filter-chip { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 20px; padding: 5px 10px; color: var(--bs-body-color); background: var(--bs-body-bg); text-decoration: none; font-size: 12px; }
        .pencairan-filter-chip span:first-child { overflow-wrap: anywhere; }
        .pencairan-filter-chip:hover { border-color: var(--bs-primary); color: var(--bs-primary); }
        @media (min-width: 1400px) { .pencairan-filter-fields { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .pencairan-filter-primary, .pencairan-filter-fields { grid-template-columns: minmax(0, 1fr); } }
    </style>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 pb-0">
            @if ($mode === 'pengajuan')
            <ul class="nav nav-tabs pencairan-tabs" role="tablist" aria-label="Pengajuan aktif/nonaktif">
                <li class="nav-item" role="presentation">
                    <a class="nav-link @if(($tab ?? 'aktif') === 'aktif') active @endif" href="{{ request()->fullUrlWithQuery(['tab' => 'aktif', 'page' => null, 'status' => null]) }}"><i class="ti ti-activity me-1" aria-hidden="true"></i>Aktif</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link @if(($tab ?? 'aktif') === 'nonaktif') active @endif" href="{{ request()->fullUrlWithQuery(['tab' => 'nonaktif', 'page' => null, 'status' => null]) }}"><i class="ti ti-circle-off me-1" aria-hidden="true"></i>Nonaktif</a>
                </li>
            </ul>
            @else
            <h5 class="mb-3">{{ $title }}</h5>
            @endif
            <div class="d-flex align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFilter">
                    <i class="ti ti-filter me-1" aria-hidden="true"></i>Filter
                    @if($advancedCount)<span class="badge text-bg-primary ms-1">{{ $advancedCount }}</span>@endif
                </button>
                @if ($mode === 'pengajuan' && (session('user_data.role', session('user_role')) === 'admin' || trim((string) session('user_data.kode_pp', '')) !== ''))
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPilihForm">
                    <i class="ti ti-plus me-1"></i> Tambah Pengajuan
                </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            @include('main.pencairan.partials.feedback')
            @if($activeFilters)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Filter aktif">
                <small class="text-muted">Filter aktif:</small>
                @foreach($activeFilters as $key => $label)
                    @php
                        $remainingFilters = request()->only(['q', 'status', 'jenis', 'tahun', 'tw', 'pp']);
                        if ($mode === 'pengajuan') { $remainingFilters['tab'] = $tab ?? 'aktif'; }
                        unset($remainingFilters[$key]);
                        if ($key === 'status') $remainingFilters['status'] = '';
                        $removeUrl = request()->url().($remainingFilters ? '?'.http_build_query($remainingFilters) : '');
                    @endphp
                    <a class="pencairan-filter-chip" href="{{ $removeUrl }}" aria-label="Hapus filter {{ $label }}">
                        <span>{{ $label }}</span><span aria-hidden="true">×</span>
                    </a>
                @endforeach
                <a href="{{ $resetUrl }}" class="small">Hapus semua</a>
            </div>
            @endif
            <form method="GET" action="{{ request()->url() }}" class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Jumlah data pengajuan">
                @foreach (request()->only(['tab', 'q', 'status', 'jenis', 'tahun', 'tw', 'pp']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="pengajuan-per-page" class="mb-0">Tampilkan</label>
                <select id="pengajuan-per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
                    @foreach (['10' => '10', '50' => '50', '100' => '100', 'all' => 'Semua data'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) $perPage === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span>data</span>
                <noscript><button type="submit" class="btn btn-sm btn-primary">Terapkan</button></noscript>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="bg-secondary text-center">
                        <tr>
                            <th class="text-white">No</th>
                            <th class="text-white">Jenis</th>
                            <th class="text-white">Uraian</th>
                            <th class="text-white">Nominal</th>
                            <th class="text-white">Status</th>
                            <th class="text-white">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                        <tr>
                            <td class="text-nowrap">{{ $item->nomor_pengajuan ?? 'Draft #'.$item->id }}<small class="d-block text-muted">{{ $item->kode_pp }}</small></td>
                            <td class="text-nowrap"><span class="badge {{ $item->jenisBadgeClass() }}">{{ $item->jenisLabel() }}</span></td>
                            <td style="min-width: 200px; max-width: 350px; white-space: normal; overflow-wrap: anywhere;">{{ Illuminate\Support\Str::limit($item->uraian_pengajuan, 80) }}</td>
                            <td class="text-end text-nowrap">Rp {{ number_format((float)$item->total_bruto, 2, ',', '.') }}</td>
                            <td><span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span><small class="d-block text-muted">{{ $item->updated_at->format('d/m/Y H:i') }}</small></td>
                            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('pencairan.show', ['pengajuan' => $item, 'menu' => $mode]) }}">Detail</a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2 d-block mx-auto opacity-50" aria-hidden="true">
                                    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                                    <path d="M14 3v5h5"/>
                                    <path d="M9 13h6"/>
                                    <path d="M9 17h4"/>
                                    <circle cx="16.5" cy="16.5" r="3.5"/>
                                    <path d="M19 19l2.5 2.5"/>
                                </svg>
                                Belum ada pengajuan untuk filter ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>

    <!-- Modal Filter -->
    <div class="modal fade" id="modalFilter" tabindex="-1" aria-labelledby="modalFilterLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="GET" action="{{ request()->url() }}" class="modal-content pencairan-filters" aria-label="Filter pengajuan">
                @if ($mode === 'pengajuan')
                <input type="hidden" name="tab" value="{{ $tab ?? 'aktif' }}">
                @endif
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterLabel">Filter pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="pencairan-filter-primary">
                        <div>
                            <label for="filter-q" class="form-label">Cari pengajuan</label>
                            <input id="filter-q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Cari nomor atau uraian pengajuan…" maxlength="100">
                        </div>
                        <div>
                            <label for="filter-status" class="form-label">Status pengajuan</label>
                            <select id="filter-status" name="status" class="form-select">
                                <option value="">Semua status</option>
                                @foreach ($allowedStatuses as $code)
                                    <option value="{{ $code }}" @selected($statusFilter === $code)>{{ $statusLabels[$code] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pencairan-filter-fields">
                        @if ($mode !== 'transaksi')
                        <div>
                            <label for="filter-jenis" class="form-label">Jenis pengajuan</label>
                            <select id="filter-jenis" name="jenis" class="form-select">
                                <option value="">Semua jenis</option>
                                @foreach (['imprestfund', 'beban', 'honor'] as $kind)
                                    <option value="{{ $kind }}" @selected(request('jenis') === $kind)>{{ ucfirst($kind) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div>
                            <label for="filter-tahun" class="form-label">Tahun anggaran</label>
                            <input id="filter-tahun" name="tahun" type="number" class="form-control" value="{{ request('tahun') }}" placeholder="Semua tahun" min="1000" max="9999" step="1">
                        </div>
                        <div>
                            <label for="filter-tw" class="form-label">Triwulan</label>
                            <select id="filter-tw" name="tw" class="form-select">
                                <option value="">Semua triwulan</option>
                                @foreach(range(1,4) as $tw)
                                    <option value="{{ $tw }}" @selected((string)request('tw') === (string)$tw)>Triwulan {{ $tw }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if(session('user_role') === 'admin')
                        <div>
                            <label for="filter-pp" class="form-label">Unit / PP</label>
                            <input id="filter-pp" name="pp" class="form-control" list="filter-unit-options" value="{{ $selectedPp }}" placeholder="Cari kode atau nama unit" maxlength="100">
                            <datalist id="filter-unit-options">
                                @foreach($filterUnits as $code => $name)
                                    <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
                                @endforeach
                            </datalist>
                            <small class="text-muted">Pilih saran unit atau masukkan kode PP.</small>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ $resetUrl }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1" aria-hidden="true"></i>Terapkan filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pilih Form -->
    <div class="modal fade" id="modalPilihForm" tabindex="-1" aria-labelledby="modalPilihFormLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPilihFormLabel">Pilih Formulir Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="{{ route('pencairan.form', 'imprestfund') }}"
                            class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="ti ti-wallet fs-4 text-primary me-3"></i>
                            <div>
                                <div class="fw-semibold">Form Imprestfund</div>
                                <small class="text-muted">Pengajuan pencairan Imprestfund</small>
                            </div>
                        </a>
                        <a href="{{ route('pencairan.form', 'beban') }}"
                            class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="ti ti-file-text fs-4 text-primary me-3"></i>
                            <div>
                                <div class="fw-semibold">Form Beban</div>
                                <small class="text-muted">Pengajuan pencairan Beban</small>
                            </div>
                        </a>
                        <a href="{{ route('pencairan.form', 'honor') }}"
                            class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="ti ti-report-money fs-4 text-primary me-3"></i>
                            <div>
                                <div class="fw-semibold">Form Honor</div>
                                <small class="text-muted">Pengajuan pencairan Honor</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
