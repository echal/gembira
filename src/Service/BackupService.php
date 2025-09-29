<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class BackupService
{
    private Connection $connection;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private string $backupDirectory;

    public function __construct(
        Connection $connection,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->params = $params;
        $this->logger = $logger;
        
        // Setup backup directory
        $this->backupDirectory = $this->params->get('kernel.project_dir') . '/var/backup';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0755, true);
        }
    }

    /**
     * Create database backup
     */
    public function createBackup(): array
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "gembira_backup_{$timestamp}.sql";
            $filepath = $this->backupDirectory . '/' . $filename;
            
            // Get database parameters
            $dbParams = $this->connection->getParams();
            $dbName = $dbParams['dbname'] ?? '';
            $dbUser = $dbParams['user'] ?? '';
            $dbPass = $dbParams['password'] ?? '';
            $dbHost = $dbParams['host'] ?? 'localhost';
            $dbPort = $dbParams['port'] ?? 3306;
            
            // Build mysqldump command
            $command = sprintf(
                'mysqldump --host=%s --port=%d --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
                escapeshellarg($dbHost),
                $dbPort,
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
            
            // Execute backup command
            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new \Exception('Backup failed: ' . implode("\n", $output));
            }
            
            // Verify backup file was created and has content
            if (!file_exists($filepath) || filesize($filepath) === 0) {
                throw new \Exception('Backup file was not created or is empty');
            }
            
            // Log successful backup
            $this->logger->info('Database backup created successfully', [
                'filename' => $filename,
                'size' => filesize($filepath)
            ]);
            
            // Clean old backups (keep last 10)
            $this->cleanOldBackups(10);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'timestamp' => $timestamp
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Database backup failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of available backups
     */
    public function getBackupList(): array
    {
        $backups = [];
        
        if (!is_dir($this->backupDirectory)) {
            return $backups;
        }
        
        $files = glob($this->backupDirectory . '/gembira_backup_*.sql');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'filepath' => $file,
                'size' => filesize($file),
                'created_at' => filemtime($file),
                'formatted_size' => $this->formatBytes(filesize($file)),
                'formatted_date' => date('d/m/Y H:i', filemtime($file))
            ];
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });
        
        return $backups;
    }

    /**
     * Get latest backup info
     */
    public function getLatestBackup(): ?array
    {
        $backups = $this->getBackupList();
        return !empty($backups) ? $backups[0] : null;
    }

    /**
     * Download backup file
     */
    public function downloadBackup(string $filename): ?string
    {
        $filepath = $this->backupDirectory . '/' . $filename;
        
        if (!file_exists($filepath) || !str_starts_with($filename, 'gembira_backup_')) {
            return null;
        }
        
        return $filepath;
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(string $filename): bool
    {
        $filepath = $this->backupDirectory . '/' . $filename;
        
        if (!file_exists($filepath) || !str_starts_with($filename, 'gembira_backup_')) {
            return false;
        }
        
        return unlink($filepath);
    }

    /**
     * Clean old backup files
     */
    private function cleanOldBackups(int $keepCount = 10): void
    {
        $backups = $this->getBackupList();
        
        if (count($backups) <= $keepCount) {
            return;
        }
        
        $toDelete = array_slice($backups, $keepCount);
        
        foreach ($toDelete as $backup) {
            if (file_exists($backup['filepath'])) {
                unlink($backup['filepath']);
                $this->logger->info('Old backup deleted', [
                    'filename' => $backup['filename']
                ]);
            }
        }
    }

    /**
     * Format file size in human readable format
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0 B';
        }
        
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

    /**
     * Check if mysqldump is available
     */
    public function checkMysqldumpAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('mysqldump --version 2>&1', $output, $returnVar);
        
        return $returnVar === 0;
    }

    /**
     * Get backup directory info
     */
    public function getBackupDirectoryInfo(): array
    {
        $totalSize = 0;
        $fileCount = 0;
        
        if (is_dir($this->backupDirectory)) {
            $files = glob($this->backupDirectory . '/gembira_backup_*.sql');
            $fileCount = count($files);
            
            foreach ($files as $file) {
                $totalSize += filesize($file);
            }
        }
        
        return [
            'directory' => $this->backupDirectory,
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'formatted_size' => $this->formatBytes($totalSize),
            'writable' => is_writable($this->backupDirectory)
        ];
    }
}