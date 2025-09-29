<?php

use App\Kernel;

// Load bootstrap untuk timezone dan konfigurasi lainnya
require_once dirname(__DIR__).'/config/bootstrap.php';

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
