<?php

namespace App\Repository;

use App\Entity\SystemConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemConfiguration>
 */
class SystemConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemConfiguration::class);
    }

    /**
     * Get configuration value by key
     */
    public function getConfigValue(string $key, ?string $defaultValue = null): ?string
    {
        $config = $this->findOneBy(['configKey' => $key]);
        return $config ? $config->getConfigValue() : $defaultValue;
    }

    /**
     * Get boolean configuration value by key
     */
    public function getBooleanConfigValue(string $key, bool $defaultValue = false): bool
    {
        $config = $this->findOneBy(['configKey' => $key]);
        return $config ? $config->getBooleanValue() : $defaultValue;
    }

    /**
     * Set configuration value
     */
    public function setConfigValue(string $key, ?string $value, ?string $description = null): SystemConfiguration
    {
        $config = $this->findOneBy(['configKey' => $key]);
        
        if (!$config) {
            $config = new SystemConfiguration();
            $config->setConfigKey($key);
        }

        $config->setConfigValue($value);
        $config->setUpdatedAt(new \DateTime());
        
        if ($description !== null) {
            $config->setDescription($description);
        }

        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();

        return $config;
    }

    /**
     * Set boolean configuration value
     */
    public function setBooleanConfigValue(string $key, bool $value, ?string $description = null): SystemConfiguration
    {
        return $this->setConfigValue($key, $value ? '1' : '0', $description);
    }

    /**
     * Check if maintenance mode is enabled
     */
    public function isMaintenanceModeEnabled(): bool
    {
        return $this->getBooleanConfigValue('maintenance_mode', false);
    }

    /**
     * Get maintenance mode message
     */
    public function getMaintenanceMessage(): string
    {
        return $this->getConfigValue('maintenance_message', 'Sistem sedang dalam pemeliharaan. Mohon coba beberapa saat lagi.');
    }
}