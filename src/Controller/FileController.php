<?php

namespace App\Controller;

use App\Entity\Pegawai;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/file')]
#[IsGranted('ROLE_USER')]
class FileController extends AbstractController
{
    #[Route('/selfie/{filename}', name: 'app_file_selfie')]
    public function selfie(string $filename): Response
    {
        // Hanya user yang login yang bisa akses foto selfie
        if (!$this->getUser() instanceof Pegawai) {
            throw $this->createAccessDeniedException('Akses ditolak');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/absensi/' . $filename;
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File tidak ditemukan');
        }

        // Validasi nama file untuk keamanan - support format dari kedua controller
        if (!preg_match('/^(selfie_[a-z0-9]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}|absensi_\d+_\d+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.jpg$/', $filename)) {
            throw $this->createAccessDeniedException('Format nama file tidak valid');
        }

        $response = new BinaryFileResponse($filePath);
        
        // Set headers untuk caching dan security
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        
        return $response;
    }
}