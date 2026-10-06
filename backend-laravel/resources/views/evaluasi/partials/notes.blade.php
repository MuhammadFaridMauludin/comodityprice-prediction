{{-- Keterangan metrik. Teks statis, tampil paling bawah halaman. --}}
<section class="card notes">
    <h2>Keterangan</h2>

    <dl>
        <div>
            <dt>MAE (Mean Absolute Error)</dt>
            <dd>Rata-rata selisih mutlak antara harga prediksi dan harga aktual, dalam satuan rupiah. Semakin kecil
                semakin baik.</dd>
        </div>
        <div>
            <dt>RMSE (Root Mean Squared Error)</dt>
            <dd>Akar dari rata-rata kuadrat selisih prediksi dan aktual, juga dalam rupiah. Lebih peka terhadap
                kesalahan besar daripada MAE. Semakin kecil semakin baik.</dd>
        </div>
        <div>
            <dt>MAPE (Mean Absolute Percentage Error)</dt>
            <dd>Rata-rata kesalahan dalam persen terhadap harga aktual, sehingga bisa dibandingkan antar komoditas yang
                harganya berbeda jauh. Umumnya, MAPE di bawah 10% dianggap sangat akurat.</dd>
        </div>
        <div>
            <dt>R² (Koefisien Determinasi)</dt>
            <dd>Seberapa besar variasi harga yang bisa dijelaskan model, berkisar 0 sampai 1. Semakin mendekati 1
                semakin baik.</dd>
        </div>
    </dl>

    <h3>Cara model terbaik dipilih</h3>
    <ul>
        <li>Model dengan RMSE paling rendah dipilih sebagai model terbaik.</li>
        <li>MAE, MAPE, dan R² dipakai sebagai pembanding pendukung.</li>
        <li>Model terbaik bisa berbeda untuk tiap komoditas, jadi pilih tab komoditas di atas untuk melihat hasilnya.
        </li>
    </ul>

    <h3>Catatan</h3>
    <ul>
        <li>Baris dengan latar biru muda pada tabel adalah model terbaik.</li>
        <li>Panjang batang pada diagram membandingkan nilai RMSE antar model. Batang yang lebih pendek menandakan
            kesalahan yang lebih kecil.</li>
    </ul>
</section>
