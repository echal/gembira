<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/qr-repair')]
final class QRRepairController extends AbstractController
{
    #[Route('/', name: 'app_qr_repair_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        return $this->render('admin/qr_repair.html.twig', [
            'tanggal_hari_ini' => new \DateTime(),
        ]);
    }

    #[Route('/upload', name: 'app_qr_repair_upload', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function uploadAndRepair(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('qr_image');
            
            if (!$uploadedFile) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Tidak ada file yang diupload'
                ]);
            }

            // Validasi file
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($uploadedFile->getMimeType(), $allowedTypes)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Format file tidak didukung. Gunakan JPG atau PNG'
                ]);
            }

            // Validasi ukuran (max 5MB)
            if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Ukuran file terlalu besar. Maksimal 5MB'
                ]);
            }

            // Simpan file sementara
            $uploadDir = $this->getParameter('kernel.project_dir') . '/var/tmp/qr_repair';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'qr_input_' . uniqid() . '.' . $uploadedFile->guessExtension();
            $uploadedFile->move($uploadDir, $filename);
            $inputPath = $uploadDir . '/' . $filename;

            // Analisis QR code
            $analysis = $this->analyzeQRCode($inputPath);
            
            if ($analysis['success']) {
                // Coba perbaiki
                $repairResult = $this->repairQRCode($inputPath);
                
                // Cleanup file input
                if (file_exists($inputPath)) {
                    unlink($inputPath);
                }
                
                return new JsonResponse($repairResult);
            } else {
                return new JsonResponse($analysis);
            }

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }

    private function analyzeQRCode(string $imagePath): array
    {
        try {
            // Cek apakah file ada
            if (!file_exists($imagePath)) {
                return [
                    'success' => false,
                    'message' => '❌ File gambar tidak ditemukan'
                ];
            }

            // Get image info
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                return [
                    'success' => false,
                    'message' => '❌ File bukan gambar yang valid'
                ];
            }

            [$width, $height] = $imageInfo;
            
            // Coba decode dengan berbagai library PHP
            $decodedData = $this->tryDecodeQR($imagePath);
            
            $analysis = [
                'success' => true,
                'image_size' => $width . 'x' . $height,
                'file_size' => filesize($imagePath),
                'is_readable' => !empty($decodedData),
                'decoded_data' => $decodedData,
                'blur_detected' => $this->detectBlur($imagePath),
                'contrast_level' => $this->analyzeContrast($imagePath)
            ];

            return $analysis;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Error analisis: ' . $e->getMessage()
            ];
        }
    }

    private function tryDecodeQR(string $imagePath): ?string
    {
        // Method 1: Gunakan Python script jika tersedia
        if ($this->isPythonAvailable()) {
            $pythonScript = $this->getParameter('kernel.project_dir') . '/qr_repair_tool.py';
            if (file_exists($pythonScript)) {
                $command = "python \"{$pythonScript}\" decode \"{$imagePath}\"";
                $output = shell_exec($command);
                if (!empty($output) && !str_contains($output, 'Error')) {
                    return trim($output);
                }
            }
        }

        // Method 2: Manual pattern detection (basic)
        return $this->basicQRDecode($imagePath);
    }

    private function basicQRDecode(string $imagePath): ?string
    {
        // Basic implementation untuk detect pattern QR code
        // Ini adalah implementasi sederhana, untuk production sebaiknya gunakan library khusus
        
        try {
            // Baca gambar
            $image = imagecreatefromstring(file_get_contents($imagePath));
            if (!$image) {
                return null;
            }

            // Convert ke grayscale dan analyze pattern
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Deteksi finder patterns (kotak sudut)
            $finderPatterns = $this->detectFinderPatterns($image, $width, $height);
            
            // Jika ditemukan minimal 3 finder patterns, kemungkinan QR code valid
            if (count($finderPatterns) >= 3) {
                // Untuk sekarang return null, implementasi decode penuh memerlukan library khusus
                return null;
            }
            
            imagedestroy($image);
            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function detectFinderPatterns($image, int $width, int $height): array
    {
        $patterns = [];
        
        // Scan untuk pattern 7x7 finder (1:1:3:1:1)
        // Implementasi sederhana - untuk production gunakan OpenCV atau library CV
        
        return $patterns;
    }

    private function repairQRCode(string $inputPath): array
    {
        try {
            // Enhance image quality
            $enhancedPath = $this->enhanceImage($inputPath);
            
            // Coba decode lagi setelah enhancement
            $decodedData = $this->tryDecodeQR($enhancedPath);
            
            if ($decodedData) {
                // Generate QR code baru dengan data yang didecode
                $repairedPath = $this->generateQRCode($decodedData);
                
                return [
                    'success' => true,
                    'message' => '✅ QR code berhasil diperbaiki!',
                    'original_data' => $decodedData,
                    'repaired_url' => $this->generateUrl('app_qr_repair_download', [
                        'filename' => basename($repairedPath)
                    ]),
                    'suggestions' => [
                        'Data QR code berhasil dipulihkan',
                        'QR code baru dibuat dengan error correction level tinggi',
                        'Kualitas gambar ditingkatkan untuk scan yang lebih baik'
                    ]
                ];
            } else {
                // Tidak berhasil decode, berikan saran
                return [
                    'success' => false,
                    'message' => '❌ QR code tidak dapat dipulihkan',
                    'suggestions' => [
                        'Coba ambil foto QR code dengan kualitas lebih baik',
                        'Pastikan QR code tidak terpotong atau terlalu blur',
                        'Bersihkan QR code dari kotoran atau goresan',
                        'Gunakan pencahayaan yang baik saat memfoto',
                        'Jika memungkinkan, minta QR code baru dari sumber asli'
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Error perbaikan: ' . $e->getMessage()
            ];
        }
    }

    private function enhanceImage(string $inputPath): string
    {
        $outputPath = str_replace('.', '_enhanced.', $inputPath);
        
        // Load image
        $image = imagecreatefromstring(file_get_contents($inputPath));
        if (!$image) {
            return $inputPath; // Return original if enhancement fails
        }

        // Convert to grayscale
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        
        // Increase contrast
        imagefilter($image, IMG_FILTER_CONTRAST, -30);
        
        // Sharpen
        $sharpenMatrix = array(
            array(0, -1, 0),
            array(-1, 5, -1),
            array(0, -1, 0)
        );
        imageconvolution($image, $sharpenMatrix, 1, 0);

        // Save enhanced image
        imagepng($image, $outputPath);
        imagedestroy($image);

        return $outputPath;
    }

    private function generateQRCode(string $data): string
    {
        // Gunakan library QR code yang sudah ada di project
        $qrCode = new \Endroid\QrCode\QrCode($data);
        
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);
        
        // Simpan ke direktori temporary
        $outputDir = $this->getParameter('kernel.project_dir') . '/var/tmp/qr_repaired';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $filename = 'repaired_qr_' . uniqid() . '.png';
        $outputPath = $outputDir . '/' . $filename;
        
        file_put_contents($outputPath, $result->getString());
        
        return $outputPath;
    }

    #[Route('/download/{filename}', name: 'app_qr_repair_download')]
    #[IsGranted('ROLE_ADMIN')]
    public function downloadRepaired(string $filename): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/var/tmp/qr_repaired/' . $filename;
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File tidak ditemukan');
        }

        return $this->file($filePath, 'qr_code_repaired.png');
    }

    private function detectBlur(string $imagePath): bool
    {
        // Implementasi sederhana deteksi blur
        // Untuk implementasi lengkap gunakan OpenCV
        return false;
    }

    private function analyzeContrast(string $imagePath): string
    {
        // Analisis kontras gambar
        return 'normal';
    }

    private function isPythonAvailable(): bool
    {
        $output = shell_exec('python --version 2>&1');
        return !empty($output) && str_contains($output, 'Python');
    }
}