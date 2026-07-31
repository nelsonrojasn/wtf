<?php
// bin/scaffold.php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}

if ($argc < 2) {
    echo "\033[31mError: Debes proporcionar la ruta al archivo CSV.\033[0m\n";
    echo "Uso: php bin/scaffold.php <archivo.csv>\n";
    exit(1);
}

$csv_path = $argv[1];
if (!file_exists($csv_path)) {
    echo "\033[31mError: No se encontró el archivo CSV en: $csv_path\033[0m\n";
    exit(1);
}

// Cargar rutas existentes del sistema
$routes_file = ROOT_PATH . 'config/routes.php';
$routes = file_exists($routes_file) ? require $routes_file : [];

if (($handle = fopen($csv_path, 'r')) !== false) {
    $headers = @fgetcsv($handle);
    
    // Esperamos las columnas: Path,Module,Starter,Filters,Description
    if (!$headers || count($headers) < 5) {
        echo "\033[31mError: El archivo CSV debe tener al menos las columnas Path, Module, Starter, Filters, Description.\033[0m\n";
        fclose($handle);
        exit(1);
    }
    
    echo "\033[1;36m=== Iniciando Proceso de Andamiaje (Scaffolding) ===\033[0m\n\n";
    
    while (($row = @fgetcsv($handle)) !== false) {
        if (count($row) < 5 || empty(trim($row[0]))) continue;
        
        $path = trim($row[0]);
        $module = trim($row[1]);
        $starter = trim($row[2]);
        $filtersRaw = trim($row[3]);
        $description = trim($row[4]);
        
        // Parsear filtros (por ejemplo: "[]", "[auth]", "['auth']", "auth")
        $filters = [];
        if (!empty($filtersRaw) && $filtersRaw !== '[]') {
            $cleaned = trim($filtersRaw, '[]');
            if (!empty($cleaned)) {
                $parts = explode(',', $cleaned);
                foreach ($parts as $part) {
                    $part = trim($part, " '\"");
                    if (!empty($part)) {
                        $filters[] = $part;
                    }
                }
            }
        }
        
        echo "Procesando ruta [\033[32m$path\033[0m] -> Módulo: \033[33m$module\033[0m, Starter: \033[33m$starter\033[0m...\n";
        
        // 1. Crear directorio del módulo y de vistas
        $module_dir = ROOT_PATH . 'modules/' . $module;
        $views_dir = $module_dir . '/views';
        
        if (!is_dir($module_dir)) {
            mkdir($module_dir, 0755, true);
            echo "  - Creado directorio de módulo: modules/$module\n";
        }
        if (!is_dir($views_dir)) {
            mkdir($views_dir, 0755, true);
            echo "  - Creado directorio de vistas: modules/$module/views\n";
        }
        
        // 2. Crear el Handler (Starter) si no existe
        $handler_path = $module_dir . '/' . $starter . '.php';
        if (!file_exists($handler_path)) {
            $handler_content = "<?php\n\n";
            $handler_content .= "class $starter {\n";
            $handler_content .= "    public function handle(array \$request)\n";
            $handler_content .= "    {\n";
            $handler_content .= "        // $description\n";
            $handler_content .= "        return view(\"$module/views/index\");\n";
            $handler_content .= "    }\n";
            $handler_content .= "}\n";
            
            file_put_contents($handler_path, $handler_content);
            echo "  - Creado Handler: modules/$module/$starter.php\n";
        } else {
            echo "  - El Handler ya existe: modules/$module/$starter.php (Omitido)\n";
        }
        
        // 3. Crear el views/index.php si no existe
        $view_path = $views_dir . '/index.php';
        if (!file_exists($view_path)) {
            $view_content = "<h1><?= h('" . addslashes($description) . "') ?></h1>\n";
            $view_content .= "<p>Esta es la vista autogenerada para la ruta: <code><?= h('" . addslashes($path) . "') ?></code></p>\n";
            
            file_put_contents($view_path, $view_content);
            echo "  - Creada vista: modules/$module/views/index.php\n";
        } else {
            echo "  - La vista ya existe: modules/$module/views/index.php (Omitido)\n";
        }
        
        // 4. Agregar o actualizar en las rutas del sistema
        $routes[$path] = [
            'module' => $module,
            'starter' => $starter,
            'filters' => $filters,
            'description' => $description
        ];
    }
    
    fclose($handle);
    
    // Escribir de vuelta a config/routes.php
    $routes_code = "<?php\n/**\n * Lista de rutas de la aplicación\n */\nreturn [\n";
    foreach ($routes as $p => $data) {
        $filters_arr = '[' . implode(', ', array_map(fn($f) => "'$f'", $data['filters'] ?? [])) . ']';
        $routes_code .= "    '" . addslashes($p) . "' => [\n";
        $routes_code .= "        'module' => '" . addslashes($data['module'] ?? '') . "',\n";
        $routes_code .= "        'starter' => '" . addslashes($data['starter'] ?? '') . "',\n";
        $routes_code .= "        'filters' => $filters_arr,\n";
        $routes_code .= "        'description' => '" . addslashes($data['description'] ?? '') . "'\n";
        $routes_code .= "    ],\n";
    }
    $routes_code .= "];\n";
    
    file_put_contents($routes_file, $routes_code);
    echo "\n\033[1;32m✓ Configuración de rutas actualizada con éxito en config/routes.php!\033[0m\n\n";
    
} else {
    echo "\033[31mError: No se pudo abrir el archivo CSV.\033[0m\n";
    exit(1);
}
