<?php
// Clear PHP Opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ Opcache cleared!\n";
} else {
    echo "✗ Opcache not enabled\n";
}

// Clear APCu if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✓ APCu cache cleared!\n";
} else {
    echo "✗ APCu not available\n";
}

echo "\nDone! Refresh your browser and try again.\n";
