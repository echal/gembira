<?php

namespace App\EventSubscriber;

use App\Service\MaintenanceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundle;
use Twig\Environment;

class MaintenanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MaintenanceService $maintenanceService,
        private SecurityBundle $security,
        private Environment $twig
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip if not main request
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $pathInfo = $request->getPathInfo();

        // Always allow access to static assets and profiler
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $pathInfo) ||
            str_starts_with($pathInfo, '/_') ||
            str_starts_with($pathInfo, '/css') ||
            str_starts_with($pathInfo, '/js') ||
            str_starts_with($pathInfo, '/images')) {
            return;
        }

        // Check if maintenance mode is enabled - if not, allow all access
        if (!$this->maintenanceService->isMaintenanceModeEnabled()) {
            return;
        }

        // Allow access for already logged-in admins to all admin routes
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Always allow access to login related routes and pages during maintenance
        // Route logout telah diperbaiki untuk menggunakan app_logout_secure yang konsisten
        $alwaysAllowedRoutes = [
            'app_login',
            'app_logout',
            'app_logout_secure',
            'app_logout_fallback',
            'app_admin_maintenance_toggle',
            'app_admin_maintenance_message',
        ];

        // Allow by route name
        if ($route && in_array($route, $alwaysAllowedRoutes)) {
            return;
        }

        // Always allow login paths by URL path (more explicit check)
        if ($pathInfo === '/login' || 
            $pathInfo === '/logout' ||
            str_starts_with($pathInfo, '/login/') ||
            str_starts_with($pathInfo, '/logout/')) {
            return;
        }

        // Allow Symfony internal routes (profiler, etc.)
        if ($route && str_starts_with($route, '_')) {
            return;
        }

        // If we reach here, maintenance mode is active and user is not admin
        // Show maintenance page for all other requests
        $maintenanceMessage = $this->maintenanceService->getMaintenanceMessage();
        
        $response = new Response(
            $this->twig->render('maintenance.html.twig', [
                'maintenance_message' => $maintenanceMessage
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE
        );

        $event->setResponse($response);
    }
}