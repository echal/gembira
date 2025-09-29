<?php

namespace App\Service;

use App\Entity\Admin;
use App\Entity\SystemConfiguration;
use App\Repository\SystemConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;

class MaintenanceService
{
    public function __construct(
        private SystemConfigurationRepository $configRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Check if maintenance mode is enabled
     */
    public function isMaintenanceModeEnabled(): bool
    {
        return $this->configRepository->isMaintenanceModeEnabled();
    }

    /**
     * Get maintenance mode message
     */
    public function getMaintenanceMessage(): string
    {
        return $this->configRepository->getMaintenanceMessage();
    }

    /**
     * Enable maintenance mode
     */
    public function enableMaintenanceMode(string $message = null, Admin $admin = null): void
    {
        $config = $this->configRepository->setConfigValue(
            'maintenance_mode',
            '1',
            'Mode pemeliharaan sistem'
        );

        if ($admin) {
            $config->setUpdatedBy($admin);
        }

        if ($message) {
            $messageConfig = $this->configRepository->setConfigValue(
                'maintenance_message',
                $message,
                'Pesan mode pemeliharaan'
            );

            if ($admin) {
                $messageConfig->setUpdatedBy($admin);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Disable maintenance mode
     */
    public function disableMaintenanceMode(Admin $admin = null): void
    {
        $config = $this->configRepository->setConfigValue(
            'maintenance_mode',
            '0',
            'Mode pemeliharaan sistem'
        );

        if ($admin) {
            $config->setUpdatedBy($admin);
        }

        $this->entityManager->flush();
    }

    /**
     * Update maintenance message
     */
    public function updateMaintenanceMessage(string $message, Admin $admin = null): void
    {
        $config = $this->configRepository->setConfigValue(
            'maintenance_message',
            $message,
            'Pesan mode pemeliharaan'
        );

        if ($admin) {
            $config->setUpdatedBy($admin);
        }

        $this->entityManager->flush();
    }
}