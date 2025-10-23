<?php

namespace App\Security;

use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Service\MaintenanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private MaintenanceService $maintenanceService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();

        // TRACKING LOGIN TERAKHIR
        // Simpan waktu dan IP address login untuk kedua tipe user (Admin dan Pegawai)
        $this->updateLastLoginInfo($user, $request);

        // Check maintenance mode - only allow admin login during maintenance
        if ($this->maintenanceService->isMaintenanceModeEnabled()) {
            if (!$user instanceof Admin) {
                // Redirect non-admin users back to login with maintenance message
                $loginUrl = $this->urlGenerator->generate('app_login');
                $request->getSession()->getFlashBag()->add('maintenance_error',
                    'Sistem sedang dalam pemeliharaan. Hanya admin yang dapat mengakses sistem saat ini.'
                );
                return new RedirectResponse($loginUrl);
            }
        }

        // PENTING: Hapus target_path dari session untuk memastikan redirect yang benar
        // Ini mencegah user diarahkan ke halaman yang salah (contoh: pegawai ke admin panel)
        // terutama setelah ganti password atau logout
        $session = $request->getSession();
        if ($session->has('_security.main.target_path')) {
            $session->remove('_security.main.target_path');
        }

        // Redirect berdasarkan tipe ENTITY
        //
        // Struktur Data:
        // 1. Table 'admin' → Hanya untuk Admin & Super Admin (role='admin' atau 'super_admin')
        // 2. Table 'pegawai' → Untuk semua pegawai
        //
        // Login Redirect:
        // - Admin (entity Admin) → /admin/dashboard (Panel Kontrol Admin)
        // - Pegawai (entity Pegawai) → /absensi (Halaman Absensi)
        //
        // Note: Admin dengan role='pegawai' TIDAK SEHARUSNYA ada (data error)
        //       Jika ada, tetap diarahkan ke /absensi untuk backward compatibility

        if ($user instanceof Admin) {
            // Cek field role untuk backward compatibility
            if ($user->getRole() === 'pegawai') {
                // BACKWARD COMPATIBILITY: Admin dengan role='pegawai' (data error)
                // Redirect ke absensi, tapi data ini seharusnya tidak ada
                $targetUrl = $this->urlGenerator->generate('app_absensi_dashboard');
            } else {
                // Admin dengan role 'admin' atau 'super_admin' → panel admin
                $targetUrl = $this->urlGenerator->generate('app_admin_dashboard');
            }
        } elseif ($user instanceof Pegawai) {
            // Pegawai → halaman absensi
            $targetUrl = $this->urlGenerator->generate('app_absensi_dashboard');
        } else {
            // Fallback jika tipe user tidak diketahui
            $targetUrl = $this->urlGenerator->generate('app_absensi_dashboard');
        }

        return new RedirectResponse($targetUrl);
    }

    /**
     * Update informasi login terakhir untuk user (Admin atau Pegawai)
     * Menyimpan waktu login dan IP address ke database
     */
    private function updateLastLoginInfo($user, Request $request): void
    {
        try {
            $currentTime = new \DateTime();
            $clientIp = $this->getClientIpAddress($request);

            // Update berdasarkan tipe user
            if ($user instanceof Admin) {
                $user->setLastLoginAt($currentTime);
                $user->setLastLoginIp($clientIp);
            } elseif ($user instanceof Pegawai) {
                $user->setLastLoginAt($currentTime);
                $user->setLastLoginIp($clientIp);
            }

            // Simpan perubahan ke database
            $this->entityManager->flush();
            
        } catch (\Exception $e) {
            // Log error tapi jangan gagalkan login
            // Sistem tetap berjalan meskipun tracking login gagal
            error_log('Error updating login info: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan alamat IP client dengan mempertimbangkan proxy dan load balancer
     * Menangani berbagai header yang mungkin digunakan untuk IP forwarding
     */
    private function getClientIpAddress(Request $request): string
    {
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',     // Load balancer/proxy
            'HTTP_X_REAL_IP',          // Nginx proxy
            'HTTP_CLIENT_IP',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
            'REMOTE_ADDR'              // Standard
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]); // Ambil IP pertama jika ada multiple
                
                // Validasi IP dan pastikan bukan IP private/reserved
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback ke REMOTE_ADDR atau IP default
        return $request->getClientIp() ?? '127.0.0.1';
    }
}