<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Dasbor') · Predikta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/predikta.css') }}">
    @stack('styles')
</head>
<body>
    <div class="app">
        <x-sidebar />

        <div class="main">
            <x-topbar-mobile />
            <main class="content">
                @yield('content')
            </main>
        </div>

        <x-bottom-nav />
    </div>

    @include('partials.script')
    @stack('scripts')
</body>
</html>
