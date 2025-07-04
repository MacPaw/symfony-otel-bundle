<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__, 2) . '/vendor/autoload_runtime.php';

return function (array $context): Kernel {
    $appEnv = $context['APP_ENV'];
    if (!is_string($appEnv)) {
        $appEnv = 'prod';
    }

    return new Kernel($appEnv, (bool)$context['APP_DEBUG']);
};
