<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Login</title>
    <link href="{{ asset('tabler/dist/css/tabler.min.css?1692870487') }}" rel="stylesheet" />
    <link href="{{ asset('tabler/dist/css/tabler-flags.min.css?1692870487') }}" rel="stylesheet" />
    <link href="{{ asset('tabler/dist/css/tabler-payments.min.css?1692870487') }}" rel="stylesheet" />
    <link href="{{ asset('tabler/dist/css/tabler-vendors.min.css?1692870487') }}" rel="stylesheet" />
    <link href="{{ asset('tabler/dist/css/demo.min.css?1692870487') }}" rel="stylesheet" />
    <style>
        @import url('https://rsms.me/inter/inter.css');

        :root {
            --tblr-font-sans-serif: 'Inter Var', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif;
        }

        body {
            font-feature-settings: "cv03", "cv04", "cv11";
        }
    </style>
</head>

<body class="d-flex flex-column">
    <script src="{{ asset('tabler/dist/js/demo-theme.min.js?1692870487') }}"></script>
    <div class="page page-center">
        <div class="container container-normal py-4">
            <div class="row align-items-center g-4">
                <div class="col-lg">
                    <div class="container-tight">
                        <div class="text-center mb-4">
                            <a href="{{ route('login') }}" class="navbar-brand navbar-brand-autodark">
                                <img src="{{ asset('tabler/static/logo.svg') }}" height="36" alt="">
                            </a>
                        </div>

                        <div class="card card-md">
                            <div class="card-body">
                                <h2 class="h2 text-center mb-4">Admin??? Silahkan Login</h2>

                                @if (session('warning'))
                                    <div class="alert alert-warning">{{ session('warning') }}</div>
                                @endif

                                <form action="{{ route('login.store') }}" method="POST" autocomplete="off" novalidate>
                                    @csrf

                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" value="{{ old('email') }}"
                                            class="form-control @error('email') is-invalid @enderror"
                                            placeholder="your@email.com" autocomplete="off" autofocus>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Password</label>
                                        <div class="input-group input-group-flat">
                                            <input type="password" name="password" id="password"
                                                class="form-control @error('password') is-invalid @enderror"
                                                placeholder="Password" autocomplete="off">
                                            <span class="input-group-text">
                                                <a href="#" class="link-secondary" id="toggle-password"
                                                    title="Tampilkan password">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon"
                                                        width="24" height="24" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke="currentColor" fill="none"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                                    </svg>
                                                </a>
                                            </span>
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-check">
                                            <input type="checkbox" name="remember" class="form-check-input" />
                                            <span class="form-check-label">Ingat saya di perangkat ini</span>
                                        </label>
                                    </div>

                                    <div class="form-footer">
                                        <button type="submit" class="btn btn-primary w-100">Masuk</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg d-none d-lg-block">
                    <img src="{{ asset('tabler/static/illustrations/logo.png') }}" height="300"
                        class="d-block mx-auto" alt="">
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('tabler/dist/js/tabler.min.js?1692870487') }}" defer></script>
    <script src="{{ asset('tabler/dist/js/demo.min.js?1692870487') }}" defer></script>
    <script>
        document.getElementById('toggle-password').addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    </script>
</body>

</html>
