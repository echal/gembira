<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration untuk menambahkan tabel sistem ranking dinamis
 *
 * Tabel yang ditambahkan:
 * 1. absensi_durasi - Menyimpan durasi absensi harian
 * 2. ranking_harian - Menyimpan ranking pegawai setiap hari
 * 3. ranking_bulanan - Menyimpan ranking pegawai setiap bulan
 */
final class Version20250120000000_AddRankingTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menambahkan tabel untuk sistem ranking dinamis: absensi_durasi, ranking_harian, ranking_bulanan';
    }

    public function up(Schema $schema): void
    {
        // Tabel absensi_durasi
        $this->addSql('
            CREATE TABLE absensi_durasi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pegawai_id INT NOT NULL,
                tanggal DATE NOT NULL,
                jam_masuk TIME NOT NULL,
                durasi_menit INT NOT NULL COMMENT "Durasi dalam menit (positif = terlambat, negatif = lebih awal)",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pegawai_tanggal (pegawai_id, tanggal),
                INDEX idx_tanggal (tanggal),
                CONSTRAINT fk_absensi_durasi_pegawai
                    FOREIGN KEY (pegawai_id)
                    REFERENCES pegawai(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT="Menyimpan durasi absensi harian setiap pegawai"
        ');

        // Tabel ranking_harian
        $this->addSql('
            CREATE TABLE ranking_harian (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pegawai_id INT NOT NULL,
                tanggal DATE NOT NULL,
                total_durasi INT NOT NULL COMMENT "Total durasi dalam menit untuk hari ini",
                peringkat INT NOT NULL COMMENT "Peringkat pegawai (1 = terbaik)",
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pegawai_tanggal (pegawai_id, tanggal),
                INDEX idx_tanggal_peringkat (tanggal, peringkat),
                UNIQUE KEY unique_pegawai_tanggal (pegawai_id, tanggal),
                CONSTRAINT fk_ranking_harian_pegawai
                    FOREIGN KEY (pegawai_id)
                    REFERENCES pegawai(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT="Menyimpan ranking pegawai setiap hari"
        ');

        // Tabel ranking_bulanan
        $this->addSql('
            CREATE TABLE ranking_bulanan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pegawai_id INT NOT NULL,
                periode VARCHAR(7) NOT NULL COMMENT "Format: YYYY-MM (contoh: 2025-01)",
                total_durasi INT NOT NULL COMMENT "Total durasi akumulasi selama sebulan (dalam menit)",
                rata_rata_durasi FLOAT DEFAULT NULL COMMENT "Rata-rata durasi per hari (dalam menit)",
                peringkat INT NOT NULL COMMENT "Peringkat pegawai (1 = terbaik)",
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pegawai_periode (pegawai_id, periode),
                INDEX idx_periode_peringkat (periode, peringkat),
                UNIQUE KEY unique_pegawai_periode (pegawai_id, periode),
                CONSTRAINT fk_ranking_bulanan_pegawai
                    FOREIGN KEY (pegawai_id)
                    REFERENCES pegawai(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT="Menyimpan ranking pegawai setiap bulan"
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop tabel dalam urutan terbalik (untuk menghindari foreign key constraint error)
        $this->addSql('DROP TABLE IF EXISTS ranking_bulanan');
        $this->addSql('DROP TABLE IF EXISTS ranking_harian');
        $this->addSql('DROP TABLE IF EXISTS absensi_durasi');
    }
}
