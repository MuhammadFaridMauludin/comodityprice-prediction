@props(['route', 'label' => 'Scraping', 'terakhir' => null])

<div class="d-flex align-items-center gap-3">
    @if ($terakhir)
        <span class="text-secondary small">
            Data terakhir: {{ \Carbon\Carbon::parse($terakhir)->translatedFormat('d M Y') }}
        </span>
    @endif

    <form action="{{ $route }}" method="POST" class="form-scrape" data-label="{{ $label }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">{{ $label }}</button>
    </form>
</div>

@once
    @push('myscript')
        <script>
            document.addEventListener('submit', function(e) {
                const form = e.target.closest('.form-scrape');
                if (!form) return;

                // Hentikan submit, minta konfirmasi dulu
                e.preventDefault();

                const label = form.dataset.label || 'scraping';

                Swal.fire({
                    title: 'Yakin ingin ' + label.toLowerCase() + '?',
                    text: 'Proses ini akan mengambil data terbaru dari sumber dan bisa memakan waktu beberapa saat.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, jalankan',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then(function(r) {
                    if (r.isConfirmed) {
                        const btn = form.querySelector('button[type="submit"]');
                        if (btn) btn.disabled = true; // cegah klik ganda
                        form.submit(); // form.submit() tidak memicu event submit lagi
                    }
                });
            });
        </script>
    @endpush
@endonce
