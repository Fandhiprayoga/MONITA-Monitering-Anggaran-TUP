<nav class="pc-sidebar">

    <div class="navbar-wrapper">
        <!-- Logo -->
        <div class="m-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <a href="{{ route('dashboard') }}" class="b-brand text-primary d-block text-center">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Telkom University" class="img-fluid"
                    style="max-width: 35%; height: auto; display: block; margin: 0 auto;">
            </a>
        </div>

        <div class="navbar-content">
            <ul class="pc-navbar">
                <!-- Dashboard -->
                <li class="pc-item pc-caption">
                    <label>Dashboard</label>
                    <i class="ti ti-dashboard"></i>
                </li>
                <li class="pc-item">
                    <a href="{{ route('dashboard') }}"
                        class="pc-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>

                <!-- Detail Anggaran Unit -->
                <li class="pc-item pc-caption">
                    <label>Anggaran</label>
                    <i class="ti ti-building"></i>
                </li>

                <!-- Pengajuan Anggaran -->
                {{-- <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="bi bi-bank"></i></span>
                        <span class="pc-mtext">Pengajuan Anggaran</span>
                        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">

                        @if($userRole === 'admin')
                            <li class="pc-item">
                                <a class="pc-link {{ request()->routeIs('pengajuan.daftar') ? 'active' : '' }}"
                                    href="{{ route('pengajuan.daftar') }}">
                                    Daftar Ajuan
                                </a>
                            </li>
                        @endif

                        <li class="pc-item">
                            <a class="pc-link {{ request()->routeIs('pengajuan.buat') ? 'active' : '' }}"
                                href="{{ route('pengajuan.buat') }}">
                                Ajukan Anggaran
                            </a>
                        </li>

                        <li class="pc-item">
                            <a class="pc-link {{ request()->routeIs('pengajuan.riwayat') ? 'active' : '' }}"
                                href="{{ route('pengajuan.riwayat') }}">
                                Riwayat Pengajuan
                            </a>
                        </li>

                    </ul>
                </li> --}}

                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-building"></i></span>
                        <span class="pc-mtext">Detail Anggaran Unit</span>
                        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                    </a>

                    <ul class="pc-submenu" id="unit-list">
                        @if($userRole === 'admin')
                            <li class="pc-item p-2 text-end">
                                <input type="text" id="search-unit" class="form-control form-control-sm ms-5"
                                    placeholder="Cari unit..." style="width: 70%; font-size: 0.85rem; border-radius: 6px;">
                            </li>

                            <li class="pc-item">
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <ul class="list-unstyled mb-0" id="units-container">
                                        @foreach($allUnits as $kode => $nama)
                                            <li class="pc-item unit-item" data-kode="{{ $kode }}" data-nama="{{ $nama }}">
                                                <a class="pc-link" href="{{ route('unit.show', $kode) }}">
                                                    {{ $nama }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </li>
                        @else
                            <li class="pc-item">
                                @if(isset($allUnits[$userUnitKode]))
                                    <a class="pc-link" href="{{ route('unit.show', $userUnitKode) }}">
                                        <strong>{{ $userUnitKode }}</strong> - {{ $allUnits[$userUnitKode] }}
                                    </a>
                                @else
                                    <a class="pc-link" href="{{ route('unit.show', $userUnitKode) }}">
                                        {{ $userUnitNama }}
                                    </a>
                                @endif
                            </li>
                        @endif
                    </ul>
                </li>

                <!-- Summary Anggaran -->
                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-report-money"></i></span>
                        <span class="pc-mtext">Summary Anggaran</span>
                        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        @for ($i = 1; $i <= 4; $i++)
                            <li class="pc-item">
                                <a class="pc-link" href="{{ route('summary.triwulan', $i) }}">Triwulan {{ $i }}</a>
                            </li>
                        @endfor

                    </ul>
                </li>

                <!-- Laporan -->
                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-file-text"></i></span>
                        <span class="pc-mtext">Laporan</span>
                        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        @for ($i = 1; $i <= 4; $i++)
                            <li class="pc-item">
                                <a class="pc-link" href="{{ route('laporan.triwulan', $i) }}">
                                    Triwulan {{ $i }}
                                </a>
                            </li>
                        @endfor
                    </ul>
                </li>

                <!-- Pencairan Anggaran -->
                <li class="pc-item pc-caption">
                    <label>Pencairan Anggaran</label>
                    <i class="ti ti-wallet"></i>
                </li>

                <!-- Pengajuan -->
                <li class="pc-item">
                    <a class="pc-link {{ request()->routeIs('pencairan.pengajuan', 'pencairan.form') ? 'active' : '' }}"
                        href="{{ route('pencairan.pengajuan') }}">
                        <span class="pc-micon"><i class="ti ti-file-plus"></i></span>
                        <span class="pc-mtext">Pengajuan</span>
                    </a>
                </li>

                @if($userRole === 'admin')
                    <!-- Verifikasi Pengajuan -->
                    <li class="pc-item">
                        <a class="pc-link {{ request()->routeIs('pencairan.verifikasi') ? 'active' : '' }}"
                            href="{{ route('pencairan.verifikasi') }}">
                            <span class="pc-micon"><i class="ti ti-list-check"></i></span>
                            <span class="pc-mtext">Verifikasi Pengajuan</span>
                        </a>
                    </li>

                    <!-- Transaksi -->
                    <li class="pc-item pc-hasmenu">
                        <a href="#!" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-exchange"></i></span>
                            <span class="pc-mtext">Transaksi</span>
                            <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="pc-submenu">
                            <li class="pc-item">
                                <a class="pc-link {{ request()->routeIs('pencairan.transaksi') && request()->route('jenis') === 'impresfund' ? 'active' : '' }}"
                                    href="{{ route('pencairan.transaksi', 'impresfund') }}">
                                    Pengajuan Impresfund
                                </a>
                            </li>
                            <li class="pc-item">
                                <a class="pc-link {{ request()->routeIs('pencairan.transaksi') && request()->route('jenis') === 'beban' ? 'active' : '' }}"
                                    href="{{ route('pencairan.transaksi', 'beban') }}">
                                    Pengajuan Beban
                                </a>
                            </li>
                            <li class="pc-item">
                                <a class="pc-link {{ request()->routeIs('pencairan.transaksi') && request()->route('jenis') === 'honor' ? 'active' : '' }}"
                                    href="{{ route('pencairan.transaksi', 'honor') }}">
                                    Pengajuan Honor
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if($userRole === 'admin')
                    <li class="pc-item pc-caption">
                        <label>Settings</label>
                        <i class="ti ti-settings"></i>
                    </li>

                    <!-- management akun -->
                    <li class="pc-item">
                        <a class="pc-link {{ request()->routeIs('management') ? 'active' : '' }}"
                            href="{{ route('management') }}">
                            <span class="pc-micon"><i class="ti ti-user"></i></span>
                            <span class="pc-mtext">Management Akun</span>
                        </a>
                    </li>

                    <!-- pengaturan -->
                    <li class="pc-item">
                        <a class="pc-link {{ request()->routeIs('settings.index') ? 'active' : '' }}"
                            href="{{ route('settings.index') }}">
                            <span class="pc-micon"><i class="ti ti-settings"></i></span>
                            <span class="pc-mtext">Pengaturan</span>
                        </a>
                    </li>

                @endif


            </ul>


            <!-- Footer card -->
            <div class="w-100 text-center mt-3">
                <div class="badge theme-version badge rounded-pill bg-light text-dark f-12"></div>
            </div>
        </div>
    </div>
</nav>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search-unit');
            const unitItems = document.querySelectorAll('#unit-list .unit-item');

            if (searchInput) {
                searchInput.addEventListener('keyup', function () {
                    const keyword = this.value.toLowerCase();
                    unitItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        // tampilkan/hilangkan item sesuai keyword
                        item.style.display = text.includes(keyword) ? '' : 'none';
                    });
                });
            }
        });
    </script>
@endpush