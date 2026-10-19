
import mysql.connector

from config import DB


def aggregate_weather(id_komoditas, dari, sampai):
    """
    Mengagregasikan data cuaca mentah berdasarkan konfigurasi
    wilayah aktif untuk setiap komoditas.

    Hasil disimpan ke data_cuacaharian.
    Data dengan tanggal dan komoditas yang sama diperbarui,
    bukan dibuat sebagai baris duplikat.
    """

    conn = mysql.connector.connect(**DB)

    try:
        cursor = conn.cursor()

        # Pastikan komoditas memiliki konfigurasi wilayah aktif.
        cursor.execute(
            """
            SELECT COUNT(*)
            FROM konfigurasi_agregasi_cuaca
            WHERE id_komoditas = %s
              AND aktif = 1
            """,
            (id_komoditas,),
        )

        jumlah_wilayah = cursor.fetchone()[0]

        if jumlah_wilayah == 0:
            raise ValueError(
                f"Tidak ada wilayah aktif untuk komoditas "
                f"{id_komoditas}."
            )

        # Agregasi cuaca hanya menggunakan observasi yang
        # memiliki ketiga variabel lengkap.
        query = """
            INSERT INTO data_cuacaharian (
                id_komoditas,
                tanggal,
                suhu,
                curah_hujan,
                kelembapan,
                jumlah_titik
            )
            SELECT
                %s AS id_komoditas,
                dc.tanggal,
                AVG(dc.suhu) AS suhu,
                AVG(dc.curah_hujan) AS curah_hujan,
                AVG(dc.kelembapan) AS kelembapan,
                COUNT(DISTINCT dc.id_koordinat) AS jumlah_titik
            FROM data_cuaca dc
            INNER JOIN koordinat_cuaca kc
                ON kc.id_koordinat = dc.id_koordinat
            INNER JOIN konfigurasi_agregasi_cuaca ka
                ON ka.id_wilayah = kc.id_wilayah
            WHERE ka.id_komoditas = %s
              AND ka.aktif = 1
              AND dc.tanggal BETWEEN %s AND %s
              AND dc.suhu IS NOT NULL
              AND dc.curah_hujan IS NOT NULL
              AND dc.kelembapan IS NOT NULL
            GROUP BY dc.tanggal
            ON DUPLICATE KEY UPDATE
                suhu = VALUES(suhu),
                curah_hujan = VALUES(curah_hujan),
                kelembapan = VALUES(kelembapan),
                jumlah_titik = VALUES(jumlah_titik)
        """

        cursor.execute(
            query,
            (
                id_komoditas,
                id_komoditas,
                dari,
                sampai,
            ),
        )

        jumlah_perubahan = cursor.rowcount
        conn.commit()

        # Hitung hasil yang tersedia pada rentang tersebut.
        cursor.execute(
            """
            SELECT
                COUNT(*) AS total_hari,
                MIN(tanggal) AS tanggal_awal,
                MAX(tanggal) AS tanggal_akhir,
                MIN(jumlah_titik) AS titik_minimum,
                MAX(jumlah_titik) AS titik_maksimum
            FROM data_cuacaharian
            WHERE id_komoditas = %s
              AND tanggal BETWEEN %s AND %s
            """,
            (id_komoditas, dari, sampai),
        )

        hasil = cursor.fetchone()

        print("\n=== AGREGASI CUACA SELESAI ===")
        print(f"ID komoditas       : {id_komoditas}")
        print(f"Wilayah aktif      : {jumlah_wilayah}")
        print(f"Baris terpengaruh  : {jumlah_perubahan}")
        print(f"Total hari tersedia: {hasil[0]}")
        print(f"Tanggal awal       : {hasil[1]}")
        print(f"Tanggal akhir      : {hasil[2]}")
        print(f"Titik minimum      : {hasil[3]}")
        print(f"Titik maksimum     : {hasil[4]}")

        if hasil[0] == 0:
            raise ValueError(
                "Tidak ada hasil agregasi. Periksa data_cuaca, "
                "konfigurasi wilayah, dan rentang tanggal."
            )

        return {
            "id_komoditas": id_komoditas,
            "total_hari": hasil[0],
            "tanggal_awal": hasil[1],
            "tanggal_akhir": hasil[2],
            "titik_minimum": hasil[3],
            "titik_maksimum": hasil[4],
        }

    except Exception:
        conn.rollback()
        raise

    finally:
        conn.close()
