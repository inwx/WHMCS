<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

// PSR-4 Autoloader for INWX\Markups namespace
spl_autoload_register(function ($class) {
    $prefix = 'INWX\\Markups\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load helpers for backward compatibility
require_once __DIR__ . '/helpers.php';

// Load hooks
require_once __DIR__ . '/hooks.php';

use INWX\Markups\Controllers\MarkupsController;
use INWX\Markups\Helpers\SchemaHelper;

/**
 * Module configuration.
 */
function inwx_markups_config(): array
{
    return [
        'name' => 'INWX Manager',
        'description' => 'Verwaltung von INWX-Tools: TLD Markups, SSL, Modul-Updates, Installation-Infos, Whois Proxy und mehr.',
        'language' => 'english',
        'version' => '1.3.0',
        'author' => 'INWX',
        'fields' => [],
    ];
}

/**
 * Activation: ensure override table exists with all columns.
 */
function inwx_markups_activate(): array
{
    try {
        SchemaHelper::ensureSchema();

        return [
            'status' => 'success',
            'description' => 'INWX Markups wurde aktiviert. Tabelle wurde erstellt/migriert.',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Fehler beim Erstellen/Migrieren der Tabelle: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivation.
 */
function inwx_markups_deactivate(): array
{
    // Tabelle bewusst NICHT löschen, um Daten zu behalten.
    return [
        'status' => 'success',
        'description' => 'INWX Markups wurde deaktiviert.',
    ];
}

/**
 * Ensure override table exists and has all required columns.
 * Backward-compatible function for hooks and other code.
 */
function inwx_markups_ensureSchema(): void
{
    SchemaHelper::ensureSchema();
}

/**
 * Admin Area Sidebar Output.
 */
function inwx_markups_sidebar(array $vars): string
{
    $controller = new MarkupsController();

    return $controller->sidebar($vars);
}

/**
 * Admin output.
 */
function inwx_markups_output(array $vars): void
{
    $controller = new MarkupsController();
    $controller->output($vars);
}
