from datetime import date, datetime, timedelta


def catat_log(koneksi, proses, sumber, status, durasi, pesan=None):
    """Tulis satu baris ke tabel system_logs. Gagal menulis log tidak boleh
    membuat script utama ikut error."""
    sql = """
        INSERT INTO system_logs
            (process, source, status, duration, message, logged_at, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
    """
    try:
        if not koneksi.is_connected():
            koneksi.reconnect(attempts=2, delay=1)

        cursor = koneksi.cursor()
        try:
            cursor.execute(sql, (proses, sumber, status, round(durasi, 1), pesan))
            koneksi.commit()
        finally:
            cursor.close()
    except Exception as error:
        print(f"Gagal menulis log sistem: {error}")


def tentukan_rentang(koneksi, id_komoditas, id_wilayah=None, tgl_default_mulai="2024-01-01"):
    """
    Mengembalikan (tgl_mulai, tgl_akhir) berupa objek date.
    Mengembalikan (None, None) HANYA jika data sudah up-to-date.
    Jika terjadi error (koneksi/query), exception di-raise agar bisa dicatat
    sebagai 'gagal' oleh pemanggil.
    """
    tgl_kemarin = date.today() - timedelta(days=1)

    if not koneksi.is_connected():
        koneksi.reconnect(attempts=2, delay=1)

    cursor = koneksi.cursor()
    try:
        if id_wilayah:
            cursor.execute(
                "SELECT MAX(tanggal) FROM data_harga "
                "WHERE id_komoditas = %s AND id_wilayah = %s",
                (id_komoditas, id_wilayah),
            )
        else:
            cursor.execute(
                "SELECT MAX(tanggal) FROM data_harga WHERE id_komoditas = %s",
                (id_komoditas,),
            )
        row = cursor.fetchone()
    finally:
        cursor.close()

    tgl_terakhir_db = row[0] if row else None

    if tgl_terakhir_db:
        if isinstance(tgl_terakhir_db, str):
            tgl_terakhir_db = datetime.strptime(tgl_terakhir_db, "%Y-%m-%d").date()
        elif isinstance(tgl_terakhir_db, datetime):
            tgl_terakhir_db = tgl_terakhir_db.date()

        tgl_mulai = tgl_terakhir_db + timedelta(days=1)
    else:
        tgl_mulai = datetime.strptime(str(tgl_default_mulai), "%Y-%m-%d").date()

    if tgl_mulai > tgl_kemarin:
        print(f"Data komoditas {id_komoditas} sudah up-to-date (sampai kemarin).")
        return None, None

    return tgl_mulai, tgl_kemarin