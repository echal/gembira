<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== Testing Create Quote Flow ===\n\n";

try {
    // Get services
    $em = $container->get('doctrine.orm.entity_manager');
    $userXpService = $container->get('App\Service\UserXpService');

    // Get test user (ID 2 dari log)
    $userRepo = $em->getRepository('App\Entity\Pegawai');
    $user = $userRepo->find(2);

    if (!$user) {
        die("User ID 2 not found!\n");
    }

    echo "User found: " . $user->getNama() . "\n";
    echo "Current XP: " . $user->getTotalXp() . "\n";
    echo "Current Level: " . $user->getCurrentLevel() . "\n\n";

    // Test awardXpForActivity
    echo "Testing awardXpForActivity...\n";
    $result = $userXpService->awardXpForActivity($user, 'create_quote', 999);

    echo "✓ Success!\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
