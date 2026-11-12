<header class="pc-header">
    <div class="header-wrapper">
        <div class="me-auto pc-mob-drp d-flex align-items-center">
            <ul class="list-unstyled d-flex mb-0">

                {{-- 1. TOMBOL DESKTOP (Hanya muncul di layar Medium ke atas) --}}
                <li class="pc-h-item d-none d-md-inline-flex">
                    <a href="#" class="pc-head-link head-link-secondary ms-0" id="sidebar-hide">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>

                {{-- 2. TOMBOL MOBILE (Hanya muncul di layar Kecil/HP) --}}
                <li class="pc-h-item d-inline-flex d-md-none">
                    <a href="#" class="pc-head-link head-link-secondary ms-0" id="mobile-collapse">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>

            </ul>

            <!-- Greeting -->
            @php
                date_default_timezone_set('Asia/Jakarta');
                $hour = date('H');
                $icon = '';
                $greeting = '';

                if ($hour >= 4 && $hour < 11) {
                    $icon = 'class ti ti-sunrise';
                    $greeting = 'Selamat Pagi';
                } elseif ($hour >= 11 && $hour < 16) {
                    $icon = 'ti ti-sun';
                    $greeting = 'Selamat Siang';
                } elseif ($hour >= 16 && $hour < 21) {
                    $icon = 'ti ti-sunset';
                    $greeting = 'Selamat Sore';
                } else {
                    $icon = 'ti ti-moon-stars';
                    $greeting = 'Selamat Malam';
                }
            @endphp

            <span class="ms-2 h4 mb-0 d-none d-md-inline-flex align-items-center">
                <i class="{{ $icon }} me-2"></i> {{ $greeting }}, {{ session('user_data.nama_pp') }}
            </span>
        </div>

        <!-- Kanan: user profile + logout -->
        <div class="ms-auto">
            <ul class="list-unstyled mb-0 d-flex align-items-center">
                <!-- User Profile -->
                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link head-link-primary dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown"
                        href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <span class="d-none d-sm-inline">{{ session('user_data.username') }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                        <div class="dropdown-header">
                            <h4 class="user-welcome-heading">Selamat Datang, {{ session('user_data.nama_pp') }}!</h4>
                            <p class="text-muted">
                                {{ session('user_data.role') === 'admin' ? 'Administrator' : 'Pengguna Unit' }}
                            </p>
                            <hr />
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item head-link-primary">
                                    <i class="ti ti-logout me-2"></i>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</header>