<!doctype html>
<html lang="id">

<head>
    <title>@yield('title', 'Dashboard | MONITA')</title>
    <meta charset="utf-8" />

    {{-- Viewport standar yang paling direkomendasikan untuk mencegah zooming dan memastikan responsivitas --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="MONITA - Monitoring Anggaran Telkom University Purwokerto" />
    <meta name="keywords" content="Dashboard, MONITA, Anggaran, Telkom University Purwokerto" />
    <meta name="author" content="MONITA Dev Team" />

    <link rel="icon" href="{{ asset('images/icon.png') }}" type="image/x-icon" />

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap"
        id="main-font-link" />

    <link rel="stylesheet" href="{{ asset('berry/assets/fonts/phosphor/duotone/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('berry/assets/fonts/tabler-icons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('berry/assets/fonts/feather.css') }}" />
    <link rel="stylesheet" href="{{ asset('berry/assets/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('berry/assets/fonts/material.css') }}" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="stylesheet" href="{{ asset('berry/assets/css/style.css') }}" id="main-style-link" />
    <link rel="stylesheet" href="{{ asset('berry/assets/css/style-preset.css') }}" />

    <style>
        /* CSS GLOBAL (Perbaikan Modal dan Anti-Overflow) */
        .modal.fade .modal-dialog {
            transform: translateY(-20px);
            transition: all 0.25s ease-out;
        }

        .modal.show .modal-dialog {
            transform: translateY(0);
        }

        .swal2-popup {
            animation: fadeInZoom 0.25s ease-in-out;
        }

        @keyframes fadeInZoom {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* TAMENG UTAMA: Mencegah scroll global yang menyebabkan zooming/konten kependekan */
        body {
            overflow-x: hidden !important;
        }

        .pc-header .dropdown-user-profile .user-welcome-heading {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        /* Pastikan elemen layout utama mengambil lebar 100% yang tersedia */
        .pc-content {
            box-sizing: border-box;
            width: 100%;
            /* Hapus semua margin-left/padding-left kustom di sini agar core script Berry yang mengontrol */
        }

        .card .amount-text {
            font-weight: 600;
            white-space: nowrap;
            display: block;
            font-size: clamp(1rem, 1.5rem, 2rem);
            overflow-x: auto;
            max-width: 100%;
        }

        .amount-wrapper {
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            scrollbar-width: thin;
            /* Firefox */
            scrollbar-color: rgba(255, 255, 255, 0.4) transparent;
            /* Firefox */
        }

        /* Chrome, Edge, Safari */
        .amount-wrapper::-webkit-scrollbar {
            height: 4px;
            /* Scrollbar lebih tipis */
        }

        .amount-wrapper::-webkit-scrollbar-track {
            background: transparent;
            /* Menyatu dengan card */
        }

        .amount-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.4);
            /* Warna putih transparan */
            border-radius: 10px;
            /* Biar smooth */
            transition: background 0.3s ease;
        }

        .amount-wrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.7);
            /* Lebih terang saat hover */
        }
    </style>
</head>

<body>
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <script src="{{ asset('js/monita/loading.js') }}"></script>

    @include('layouts.sidebar')

    @include('layouts.header')

    <div class="pc-container">
        <div class="pc-content">
            @yield('content')
        </div>
    </div>

    @include('layouts.footer')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    <script src="{{ asset('berry/assets/js/plugins/simplebar.min.js') }}"></script>
    <script src="{{ asset('berry/assets/js/icon/custom-font.js') }}"></script>
    <script src="{{ asset('berry/assets/js/plugins/feather.min.js') }}"></script>

    <script src="{{ asset('berry/assets/js/theme.js') }}"></script>
    <script src="{{ asset('berry/assets/js/script.js') }}"></script>

    @stack('scripts')

    {{-- Script Inisialisasi --}}
    <script>
        // Memastikan inisialisasi layout default theme Berry
        if (typeof layout_change === 'function') layout_change('light');
        if (typeof font_change === 'function') font_change('Roboto');
        if (typeof change_box_container === 'function') change_box_container('false'); // Mode Fluid
        if (typeof layout_caption_change === 'function') layout_caption_change('true');
        if (typeof layout_rtl_change === 'function') layout_rtl_change('false');
        if (typeof preset_change === 'function') preset_change('preset-1');

        feather.replace();
    </script>
</body>

</html>