<?php
if (version_compare(PHP_VERSION, '8.2.0') == -1)
{
    die ('The minimum version required for PHP is 8.2.0');
}

// Load environment variables from .env if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile))
{
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line)
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// define the autoloader
require_once 'lib/adianti/core/AdiantiCoreLoader.php';
spl_autoload_register(array('Adianti\Core\AdiantiCoreLoader', 'autoload'));
Adianti\Core\AdiantiCoreLoader::loadClassMap();

// vendor autoloader
$loader = require 'vendor/autoload.php';
$loader->register();

// apply app configurations
AdiantiApplicationConfig::start();

// define constants
define('PATH', dirname(__FILE__));

setlocale(LC_ALL, 'C');

// Initialize multi-tenant context
try {
    $tenantManager = TenantManager::getInstance();
    if ($tenantManager->isMultiTenantEnabled()) {
        $tenantManager->initializeTenantContext();
    }
} catch (Exception $e) {
    // Log tenant initialization error but don't break the application
    error_log('Tenant initialization error: ' . $e->getMessage());
}
