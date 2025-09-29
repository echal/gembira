<?php

namespace App\Service;

/**
 * Service untuk export laporan ke PDF menggunakan HTML
 * Dapat diintegrasikan dengan dompdf/mpdf ketika library tersedia
 */
class PDFExportService
{
    public function generatePDFLaporanBulanan(array $laporanData): string
    {
        $header = $laporanData['header'];
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Bulanan Absensi</title>
    <style>
        @page {
            margin: 2cm 1.5cm;
            size: A4 landscape;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11px; 
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            margin: 5px 0;
            font-size: 16px;
            font-weight: bold;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            font-weight: bold;
        }
        
        .header h3 {
            margin: 5px 0;
            font-size: 13px;
            font-weight: bold;
        }
        
        .info { 
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .info div { 
            margin: 3px 0; 
            font-size: 12px;
        }
        
        .info strong {
            color: #2c3e50;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
            font-size: 10px;
        }
        
        th, td { 
            border: 1px solid #333; 
            padding: 6px 4px; 
            text-align: left; 
            vertical-align: middle;
        }
        
        th { 
            background-color: #e3f2fd; 
            font-weight: bold; 
            text-align: center;
            font-size: 10px;
        }
        
        .text-center { 
            text-align: center; 
        }
        
        .text-right { 
            text-align: right; 
        }
        
        .hijau { 
            color: #4CAF50; 
            font-weight: bold; 
        }
        
        .kuning { 
            color: #FF9800; 
            font-weight: bold; 
        }
        
        .merah { 
            color: #F44336; 
            font-weight: bold; 
        }
        
        .no-wrap {
            white-space: nowrap;
        }
        
        .small-text {
            font-size: 9px;
        }
        
        /* Kolom width untuk landscape */
        .col-absen { width: 8%; }
        .col-nama { width: 20%; }
        .col-nip { width: 15%; }
        .col-unit { width: 18%; }
        .col-foto { width: 6%; }
        .col-qr { width: 6%; }
        .col-gps { width: 6%; }
        .col-total { width: 8%; }
        .col-persen { width: 10%; }
        
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 11px;
        }
        
        .signature {
            margin-top: 50px;
            text-align: right;
        }
        
        .signature-line {
            margin-top: 80px;
            border-top: 1px solid #333;
            width: 200px;
            text-align: center;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>KANTOR WILAYAH KEMENTERIAN AGAMA</h1>
        <h2>PROVINSI SULAWESI BARAT</h2>
        <h3>TAHUN ' . $header['tahun'] . '</h3>
    </div>
    
    <div class="info">
        <div class="info-grid">
            <div><strong>Bulan:</strong> ' . $header['bulan'] . '</div>
            <div><strong>Kategori Absensi:</strong> ' . $header['kategori_absensi'] . '</div>
            <div><strong>Unit Kerja:</strong> ' . $header['unit_kerja'] . '</div>
            <div><strong>Jumlah Hari Kerja:</strong> ' . $header['jumlah_hari_kerja'] . '</div>
            <div><strong>Pegawai Hadir:</strong> ' . $header['pegawai_hadir'] . '</div>
            <div><strong>Pegawai Tidak Absen:</strong> ' . $header['pegawai_tidak_absen'] . '</div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="col-absen">Jumlah<br>Hari Absen</th>
                <th class="col-nama">Nama Pegawai</th>
                <th class="col-nip">NIP</th>
                <th class="col-unit">Unit Kerja</th>
                <th class="col-foto">Foto</th>
                <th class="col-qr">QR-code</th>
                <th class="col-gps">GPS</th>
                <th class="col-total">Total<br>Kehadiran</th>
                <th class="col-persen">% Kehadiran</th>
            </tr>
        </thead>
        <tbody>';
        
    foreach ($laporanData['data'] as $row) {
        $html .= '
            <tr>
                <td class="text-center no-wrap">' . $row['jumlah_hari_absen'] . '</td>
                <td>' . htmlspecialchars($row['nama']) . '</td>
                <td class="text-center no-wrap small-text">' . $row['nip'] . '</td>
                <td class="small-text">' . htmlspecialchars($row['unit_kerja']) . '</td>
                <td class="text-center small-text">' . $row['foto'] . '</td>
                <td class="text-center small-text">' . $row['qr_code'] . '</td>
                <td class="text-center small-text">' . $row['gps'] . '</td>
                <td class="text-center">' . $row['total_kehadiran'] . '</td>
                <td class="text-center ' . $row['warna_persentase'] . '">' . $row['persentase_kehadiran'] . '%</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>
    
    <div class="footer">
        <div>Dicetak pada: ' . date('d/m/Y H:i:s') . '</div>
    </div>
    
    <div class="signature">
        <div>Mengetahui,</div>
        <div style="margin-top: 10px;"><strong>Kepala Kantor Wilayah</strong></div>
        <div style="margin-top: 10px;"><strong>Kementerian Agama Prov. Sulawesi Barat</strong></div>
        
        <div class="signature-line">
            <div style="margin-top: 10px;">(__________________________)</div>
            <div style="margin-top: 5px; font-size: 10px;">NIP. </div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate PDF menggunakan dompdf jika tersedia
     * Fallback ke HTML jika library tidak tersedia
     */
    public function generatePDFWithDompdf(array $laporanData): string
    {
        $html = $this->generatePDFLaporanBulanan($laporanData);
        
        // Cek apakah dompdf tersedia
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf([
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            return $dompdf->output();
        }
        
        // Fallback ke HTML
        return $html;
    }
}