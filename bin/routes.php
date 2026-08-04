<?php
// bin/routes.php
// Define path constants so config files load correctly
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', ROOT_PATH . 'core/');
}

$routes_file = ROOT_PATH . 'config/routes.php';
if (!file_exists($routes_file)) {
    echo "\033[31mError: No se encontró el archivo de rutas en config/routes.php\033[0m\n";
    exit(1);
}

$routes = require $routes_file;

if (empty($routes)) {
    echo "\033[33mNo hay rutas registradas en el sistema.\033[0m\n";
    exit(0);
}

// Imprimir título
echo "\n\033[1;36m=== RUTAS REGISTRADAS EN EL SISTEMA ===\033[0m\n\n";

// Calcular anchos de columnas para ajuste perfecto
$col_widths = [
    'path' => 12,
    'module' => 12,
    'starter' => 15,
    'filters' => 12,
    'description' => 20
];

foreach ($routes as $path => $route) {
    $col_widths['path'] = max($col_widths['path'], strlen($path));
    $col_widths['module'] = max($col_widths['module'], strlen($route['module'] ?? ''));
    $col_widths['starter'] = max($col_widths['starter'], strlen($route['starter'] ?? ''));
    $filters_str = '[' . implode(', ', $route['filters'] ?? []) . ']';
    $col_widths['filters'] = max($col_widths['filters'], strlen($filters_str));
    $col_widths['description'] = max($col_widths['description'], strlen($route['description'] ?? ''));
}

// Línea divisoria de la tabla
$separator = "+" . str_repeat("-", $col_widths['path'] + 2) .
             "+" . str_repeat("-", $col_widths['module'] + 2) .
             "+" . str_repeat("-", $col_widths['starter'] + 2) .
             "+" . str_repeat("-", $col_widths['filters'] + 2) .
             "+" . str_repeat("-", $col_widths['description'] + 2) . "+\n";

// Encabezados coloreados
echo $separator;
printf(
    "| \033[1;33m%-s\033[0m | \033[1;33m%-s\033[0m | \033[1;33m%-s\033[0m | \033[1;33m%-s\033[0m | \033[1;33m%-s\033[0m |\n",
    str_pad("Ruta (Path)", $col_widths['path']),
    str_pad("Módulo", $col_widths['module']),
    str_pad("Starter (Handler)", $col_widths['starter']),
    str_pad("Filtros", $col_widths['filters']),
    str_pad("Descripción", $col_widths['description'])
);
echo $separator;

// Dibujar cada fila
foreach ($routes as $path => $route) {
    $filters_str = '[' . implode(', ', $route['filters'] ?? []) . ']';
    printf(
        "| %-s | %-s | %-s | %-s | %-s |\n",
        str_pad($path, $col_widths['path']),
        str_pad($route['module'] ?? '', $col_widths['module']),
        str_pad($route['starter'] ?? '', $col_widths['starter']),
        str_pad($filters_str, $col_widths['filters']),
        str_pad($route['description'] ?? '', $col_widths['description'])
    );
}
echo $separator;
echo "\nTotal de rutas registradas: \033[1;32m" . count($routes) . "\033[0m\n\n";
