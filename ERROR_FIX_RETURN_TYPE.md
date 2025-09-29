# PERBAIKAN ERROR: Return Type DateTime di RankingService

## 🚨 Error yang Ditemukan

```
App\Service\RankingService::getWaktuAbsenPertama(): Return value must be of type ?DateTime, string returned
```

## 🔍 Analisis Masalah

Method signature mengharapkan return type `?\DateTime`, tetapi query DQL dengan fungsi `MIN()` dan `MAX()` mengembalikan `string`.

### **Sebelum (ERROR)**:
```php
private function getWaktuAbsenPertama(Pegawai $pegawai, \DateTime $mulai, \DateTime $selesai): ?\DateTime
{
    $query = $this->entityManager->createQuery('
        SELECT MIN(a.waktuAbsensi) as waktu_pertama
        FROM App\Entity\Absensi a
        WHERE ...
    ');

    $result = $query->getOneOrNullResult();
    return $result ? $result['waktu_pertama'] : null; // ❌ Returns string, not DateTime
}
```

## ✅ Solusi yang Diimplementasikan

Menggunakan QueryBuilder dengan `orderBy` dan `setMaxResults(1)` untuk mendapatkan objek DateTime asli:

### **Sesudah (FIXED)**:
```php
private function getWaktuAbsenPertama(Pegawai $pegawai, \DateTime $mulai, \DateTime $selesai): ?\DateTime
{
    $absensi = $this->entityManager->createQueryBuilder('a')
        ->select('a.waktuAbsensi')
        ->from('App\Entity\Absensi', 'a')
        ->where('a.pegawai = :pegawai')
        ->andWhere('a.tanggal BETWEEN :mulai AND :selesai')
        ->andWhere('a.status IN (:statusValid)')
        ->andWhere('a.waktuAbsensi IS NOT NULL')
        ->setParameter('pegawai', $pegawai)
        ->setParameter('mulai', $mulai->format('Y-m-d'))
        ->setParameter('selesai', $selesai->format('Y-m-d'))
        ->setParameter('statusValid', ['hadir', 'terlambat'])
        ->orderBy('a.waktuAbsensi', 'ASC')  // ✅ Untuk waktu pertama
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();

    return $absensi ? $absensi['waktuAbsensi'] : null; // ✅ Returns DateTime object
}
```

## 🔧 Method yang Diperbaiki

1. **`getWaktuAbsenPertama()`** - Menggunakan `ORDER BY ASC` + `setMaxResults(1)`
2. **`getWaktuAbsenTerakhir()`** - Menggunakan `ORDER BY DESC` + `setMaxResults(1)`

## 🎯 Keuntungan Solusi

1. **Type Safety**: Return type sesuai dengan method signature (`?\DateTime`)
2. **Performance**: Lebih efisien karena hanya mengambil 1 record
3. **Reliability**: Tidak bergantung pada fungsi agregat DQL yang mengembalikan string
4. **Null Safety**: Menangani case ketika tidak ada data absensi

## ✅ Status

**ERROR RETURN TYPE SUDAH DIPERBAIKI**

Sekarang method `getWaktuAbsenPertama()` dan `getWaktuAbsenTerakhir()` akan mengembalikan objek `DateTime` yang sesuai dengan type hint, bukan string.

## 🚀 Testing

Sistem sekarang dapat berjalan tanpa error return type saat:
- Menghitung ranking pegawai
- Menggunakan tie-breaking berdasarkan waktu absen pertama
- Menampilkan statistik detail pegawai