<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Service\MaintenanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, MaintenanceService $maintenanceService): Response
    {
        // Jika user sudah login, redirect ke dashboard yang sesuai
        if ($this->getUser()) {
            $user = $this->getUser();
            if ($user instanceof Admin) {
                return $this->redirectToRoute('app_admin_dashboard');
            } elseif ($user instanceof Pegawai) {
                return $this->redirectToRoute('app_dashboard');
            }
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'maintenance_mode_enabled' => $maintenanceService->isMaintenanceModeEnabled(),
            'maintenance_message' => $maintenanceService->getMaintenanceMessage()
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Controller ini tidak akan dipanggil karena Symfony akan handle logout
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Route fallback untuk logout jika CSRF gagal
     * Menangani kasus dimana CSRF token expired atau invalid
     */
    #[Route('/logout-fallback', name: 'app_logout_fallback', methods: ['GET', 'POST'])]
    public function logoutFallback(Request $request): Response
    {
        // Paksa logout dengan clear semua session
        $session = $request->getSession();
        $session->invalidate();

        // Clear semua cookie yang berkaitan dengan authentication
        $response = $this->redirectToRoute('app_login');
        $response->headers->clearCookie('PHPSESSID', '/', null);
        $response->headers->clearCookie('remember_me', '/', null);

        $this->addFlash('success', 'Anda berhasil logout dari sistem.');

        return $response;
    }

    /**
     * Logout dengan validasi CSRF manual
     * Route ini menangani logout dengan pengecekan CSRF yang lebih fleksibel
     */
    #[Route('/logout-secure', name: 'app_logout_secure', methods: ['POST'])]
    public function logoutSecure(Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        try {
            // Cek CSRF token
            $submittedToken = $request->request->get('_csrf_token');

            if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('logout', $submittedToken))) {
                // CSRF invalid, tapi tetap logout untuk keamanan
                $this->addFlash('warning', 'Session expired. Anda telah logout untuk keamanan.');
            } else {
                $this->addFlash('success', 'Logout berhasil.');
            }

        } catch (\Exception $e) {
            // Error apapun, tetap logout
            $this->addFlash('info', 'Logout paksa untuk keamanan.');
        }

        // Paksa logout
        $session = $request->getSession();
        $session->invalidate();

        // Clear cookies
        $response = $this->redirectToRoute('app_login');
        $response->headers->clearCookie('PHPSESSID', '/', null);
        $response->headers->clearCookie('remember_me', '/', null);

        return $response;
    }
}