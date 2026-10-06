<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Satu script untuk semua grafik garis (komponen line-chart).
document.querySelectorAll('canvas[data-line-chart]').forEach(function (canvas) {
    var labels   = JSON.parse(canvas.dataset.labels   || '[]');
    var datasets = JSON.parse(canvas.dataset.datasets || '[]');
    var zero     = canvas.dataset.zero === '1';
    var isMobile = window.matchMedia('(max-width: 1023px)').matches;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets.map(function (d) {
                return {
                    label: d.label || '',
                    data: d.values,
                    borderColor: d.color || '#1a5cff',
                    borderWidth: 3,
                    borderDash: d.dashed ? [6, 6] : [],
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    tension: 0.35,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: function (item) { return item.parsed.y !== null; },
                    callbacks: {
                        label: function (ctx) {
                            var prefix = ctx.chart.data.datasets.length > 1 ? ctx.dataset.label + ': ' : '';
                            return prefix + 'Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false },
                     ticks: { maxTicksLimit: isMobile ? 5 : 8, maxRotation: 0, color: '#5b6b82' } },
                y: {
                    beginAtZero: zero,
                    grace: '10%',
                    grid: { color: 'rgba(91,107,130,.18)' },
                    border: { display: false },
                    ticks: {
                        color: '#5b6b82',
                        callback: function (v) {
                            return (v / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + 'rb';
                        },
                    },
                },
            },
        },
    });
});
</script>
