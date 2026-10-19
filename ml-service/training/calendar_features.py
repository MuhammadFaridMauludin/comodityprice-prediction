
import pandas as pd
from hijridate import Gregorian, Hijri


def calendar_features(dates):
    """
    Membentuk fitur kalender:
    - is_ramadan: 1 selama bulan Ramadan
    - pre_eid: 1 selama 30 hari sebelum Idulfitri
    - is_adha: 1 pada tanggal Iduladha
    - is_nataru: 1 pada 25 Desember sampai 1 Januari
    """

    dates = pd.DatetimeIndex(pd.to_datetime(dates))
    result = []

    for date in dates:
        date = date.normalize()

        # Konversi Gregorian ke Hijriah
        hijri = Gregorian(
            date.year,
            date.month,
            date.day,
        ).to_hijri()

        # Ramadan adalah bulan Hijriah ke-9
        is_ramadan = int(hijri.month == 9)

        # Cari tanggal Idulfitri yang akan datang.
        # Periksa tahun Hijriah saat ini dan tahun berikutnya.
        eid_candidates = []

        for hijri_year in [hijri.year, hijri.year + 1]:
            eid_hijri = Hijri(hijri_year, 10, 1)
            eid_gregorian = eid_hijri.to_gregorian()

            eid_date = pd.Timestamp(
                eid_gregorian.year,
                eid_gregorian.month,
                eid_gregorian.day,
            )

            if eid_date > date:
                eid_candidates.append(eid_date)

        if eid_candidates:
            next_eid = min(eid_candidates)
            days_to_eid = (next_eid - date).days
            pre_eid = int(1 <= days_to_eid <= 30)
        else:
            pre_eid = 0

        # Iduladha: 10 Zulhijah pada tahun Hijriah berjalan
        adha_hijri = Hijri(hijri.year, 12, 10)
        adha_gregorian = adha_hijri.to_gregorian()

        adha_date = pd.Timestamp(
            adha_gregorian.year,
            adha_gregorian.month,
            adha_gregorian.day,
        )

        is_adha = int(date == adha_date)

        # Nataru: 25 Desember sampai 1 Januari
        is_nataru = int(
            (date.month == 12 and date.day >= 25)
            or (date.month == 1 and date.day <= 1)
        )

        result.append({
            "is_ramadan": is_ramadan,
            "pre_eid": pre_eid,
            "is_adha": is_adha,
            "is_nataru": is_nataru,
        })

    return pd.DataFrame(
        result,
        index=dates,
        columns=[
            "is_ramadan",
            "pre_eid",
            "is_adha",
            "is_nataru",
        ],
    )
