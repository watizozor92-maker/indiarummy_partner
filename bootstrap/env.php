<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile)) {
    (new Dotenv())
        ->usePutenv()
        ->loadEnv($envFile, 'APP_ENV', 'dev', ['test'], true);
}
