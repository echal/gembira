<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Service untuk export laporan ke Excel menggunakan PhpSpreadsheet
 * Jika PhpSpreadsheet tidak tersedia, fallback ke CSV
 */
class ExcelExportService
{
    public function generateExcelLaporanBulanan(array $laporanData): string
    {
        // Cek apakah PhpSpreadsheet tersedia
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Fallback ke format CSV sederhana
            return $this->generateCSVLaporanBulanan($laporanData);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $header = $laporanData['header'];
        
        // Set title dan metadata
        $spreadsheet->getProperties()
            ->setCreator('Gembira System')
            ->setTitle('Laporan Bulanan Kehadiran')
            ->setDescription('Laporan Kehadiran Bulanan - ' . $header['bulan'] . ' ' . $header['tahun']);

        // Header Kanwil
        $sheet->setCellValue('A1', 'KANTOR WILAYAH KEMENTERIAN AGAMA');
        $sheet->setCellValue('A2', 'PROVINSI SULAWESI BARAT');
        $sheet->setCellValue('A3', 'TAHUN ' . $header['tahun']);
        
        // Style header
        $sheet->getStyle('A1:A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        // Merge cells untuk header
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        
        // Informasi laporan
        $row = 5;
        $sheet->setCellValue('A' . $row, 'Bulan:');
        $sheet->setCellValue('B' . $row, $header['bulan']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Kategori Absensi:');
        $sheet->setCellValue('B' . $row, $header['kategori_absensi']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Unit Kerja:');
        $sheet->setCellValue('B' . $row, $header['unit_kerja']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Jumlah Hari Kerja:');
        $sheet->setCellValue('B' . $row, $header['jumlah_hari_kerja']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Pegawai Hadir:');
        $sheet->setCellValue('B' . $row, $header['pegawai_hadir']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Pegawai Tidak Absen:');
        $sheet->setCellValue('B' . $row, $header['pegawai_tidak_absen']);
        $row += 2;

        // Header tabel
        $headerRow = $row;
        $headers = [
            'A' => 'Jumlah Hari Absen',
            'B' => 'Nama Pegawai', 
            'C' => 'NIP',
            'D' => 'Unit Kerja',
            'E' => 'Foto',
            'F' => 'QR-code',
            'G' => 'GPS',
            'H' => 'Total Kehadiran',
            'I' => '% Kehadiran'
        ];
        
        foreach ($headers as $col => $headerText) {
            $sheet->setCellValue($col . $headerRow, $headerText);
        }
        
        // Style header tabel
        $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E3F2FD']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        // Data rows
        $dataRow = $headerRow + 1;
        foreach ($laporanData['data'] as $data) {
            $sheet->setCellValue('A' . $dataRow, $data['jumlah_hari_absen']);
            $sheet->setCellValue('B' . $dataRow, $data['nama']);
            $sheet->setCellValue('C' . $dataRow, $data['nip']);
            $sheet->setCellValue('D' . $dataRow, $data['unit_kerja']);
            $sheet->setCellValue('E' . $dataRow, $data['foto']);
            $sheet->setCellValue('F' . $dataRow, $data['qr_code']);
            $sheet->setCellValue('G' . $dataRow, $data['gps']);
            $sheet->setCellValue('H' . $dataRow, $data['total_kehadiran']);
            $sheet->setCellValue('I' . $dataRow, $data['persentase_kehadiran'] . '%');
            
            // Warna persentase sesuai ketentuan
            $colorCode = match($data['warna_persentase']) {
                'hijau' => '4CAF50',
                'kuning' => 'FF9800', 
                'merah' => 'F44336',
                default => '000000'
            };
            
            $sheet->getStyle('I' . $dataRow)->applyFromArray([
                'font' => ['color' => ['rgb' => $colorCode], 'bold' => true]
            ]);
            
            // Border untuk semua cell
            $sheet->getStyle('A' . $dataRow . ':I' . $dataRow)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);
            
            $dataRow++;
        }
        
        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Generate file
        $writer = new Xlsx($spreadsheet);
        $filename = tempnam(sys_get_temp_dir(), 'laporan_bulanan_');
        $writer->save($filename);
        
        $content = file_get_contents($filename);
        unlink($filename);
        
        return $content;
    }
    
    /**
     * Fallback CSV export jika PhpSpreadsheet tidak tersedia
     */
    private function generateCSVLaporanBulanan(array $laporanData): string
    {
        $output = '';
        $header = $laporanData['header'];
        
        // Header
        $output .= "KANTOR WILAYAH KEMENTERIAN AGAMA\n";
        $output .= "PROVINSI SULAWESI BARAT\n";
        $output .= "TAHUN " . $header['tahun'] . "\n\n";
        
        // Info
        $output .= "Bulan," . $header['bulan'] . "\n";
        $output .= "Kategori Absensi," . $header['kategori_absensi'] . "\n";
        $output .= "Unit Kerja," . $header['unit_kerja'] . "\n";
        $output .= "Jumlah Hari Kerja," . $header['jumlah_hari_kerja'] . "\n";
        $output .= "Pegawai Hadir," . $header['pegawai_hadir'] . "\n";
        $output .= "Pegawai Tidak Absen," . $header['pegawai_tidak_absen'] . "\n\n";
        
        // Table header
        $output .= "Jumlah Hari Absen,Nama Pegawai,NIP,Unit Kerja,Foto,QR-code,GPS,Total Kehadiran,% Kehadiran\n";
        
        // Table data
        foreach ($laporanData['data'] as $row) {
            $output .= sprintf(
                "%d,\"%s\",%s,\"%s\",%s,%s,%s,%d,%.1f%%\n",
                $row['jumlah_hari_absen'],
                str_replace('"', '""', $row['nama']), // Escape quotes
                $row['nip'],
                str_replace('"', '""', $row['unit_kerja']),
                $row['foto'],
                $row['qr_code'],
                $row['gps'],
                $row['total_kehadiran'],
                $row['persentase_kehadiran']
            );
        }
        
        return $output;
    }
}