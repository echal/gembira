<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828034218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate data from jadwal table to jadwal_absensi table';
    }

    public function up(Schema $schema): void
    {
        // Migrate existing jadwal data to jadwal_absensi
        $this->addSql("
            INSERT INTO jadwal_absensi (
                jenis_absensi, 
                hari_diizinkan, 
                jam_mulai, 
                jam_selesai, 
                qr_code, 
                is_aktif, 
                keterangan, 
                created_at,
                updated_at
            )
            SELECT 
                CASE 
                    WHEN LOWER(nama_jadwal) LIKE '%apel%' THEN 'apel_pagi'
                    WHEN LOWER(nama_jadwal) LIKE '%ibadah%' THEN 'ibadah_pagi'
                    WHEN LOWER(nama_jadwal) LIKE '%upacara%' THEN 'upacara_nasional'
                    ELSE 'jadwal_kerja'
                END as jenis_absensi,
                CASE 
                    WHEN hari_kerja = '1,2,3,4,5' THEN JSON_ARRAY('1','2','3','4','5')
                    WHEN hari_kerja = '1' THEN JSON_ARRAY('1')
                    ELSE JSON_ARRAY('1','2','3','4','5')
                END as hari_diizinkan,
                jam_masuk,
                jam_keluar, 
                qr_code,
                CASE WHEN status = 'aktif' THEN 1 ELSE 0 END as is_aktif,
                keterangan,
                created_at,
                updated_at
            FROM jadwal 
            WHERE status = 'aktif'
        ");
    }

    public function down(Schema $schema): void
    {
        // Remove migrated data (optional - be careful!)
        $this->addSql("DELETE FROM jadwal_absensi WHERE jenis_absensi IN ('apel_pagi', 'ibadah_pagi', 'upacara_nasional', 'jadwal_kerja')");
    }
}
