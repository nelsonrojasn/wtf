<?php
// bin/guardian.php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}

echo "\n\033[1;35m🛡️  WTF GUARDIAN - Linter & Static Code Analysis\033[0m\n";
echo "==================================================\n\n";

$errors = [];
$php_files = get_php_files(ROOT_PATH);

// --- Phase 1: Syntax Linter ---
echo "\033[1;36m[Fase 1] Ejecutando análisis de sintaxis (Linter)...\033[0m\n";
$syntax_passed = 0;
$syntax_failed = 0;

foreach ($php_files as $file) {
    $relative_path = str_replace(ROOT_PATH, '', $file);
    $syntax_error = check_syntax($file);
    if ($syntax_error) {
        $syntax_failed++;
        $errors[] = [
            'type' => 'Sintaxis',
            'file' => $relative_path,
            'message' => trim($syntax_error)
        ];
        echo "  \033[31m✕ $relative_path\033[0m (Error de sintaxis)\n";
    } else {
        $syntax_passed++;
    }
}
echo "  Sintaxis: \033[32m$syntax_passed OK\033[0m, \033[31m$syntax_failed Errores\033[0m.\n\n";


// --- Phase 2: Handlers and Views Linter ---
echo "\033[1;36m[Fase 2] Revisando Handlers y Vistas (HTML directo e instanciaciones)...\033[0m\n";
$module_passed = 0;
$module_failed = 0;

$modules_dir = ROOT_PATH . 'modules';
$module_files = [];
if (is_dir($modules_dir)) {
    $module_files = get_php_files($modules_dir);
}

// Agregar templates y partials al escaneo de vistas
$shared_dir = ROOT_PATH . 'shared';
if (is_dir($shared_dir . '/templates')) {
    $module_files = array_merge($module_files, get_php_files($shared_dir . '/templates'));
}
if (is_dir($shared_dir . '/partials')) {
    $module_files = array_merge($module_files, get_php_files($shared_dir . '/partials'));
}

foreach ($module_files as $file) {
    $relative_path = str_replace(ROOT_PATH, '', $file);
    $is_view = str_contains($relative_path, '/views/') || 
               str_contains($relative_path, 'shared/templates/') || 
               str_contains($relative_path, 'shared/partials/');
    $content = file_get_contents($file);
    
    $file_issues = [];
    
    // 1. Los Handlers no pueden emitir HTML directo
    if (!$is_view) {
        $html_issues = check_html_in_handler($content);
        if (!empty($html_issues)) {
            $file_issues = array_merge($file_issues, $html_issues);
        }
    }
    
    // 2. Ni Handlers ni Vistas pueden instanciar clases con 'new' manualmente
    $instantiation_issues = check_manual_instantiation($content, $is_view);
    if (!empty($instantiation_issues)) {
        $file_issues = array_merge($file_issues, $instantiation_issues);
    }
    
    if (!empty($file_issues)) {
        $module_failed++;
        foreach ($file_issues as $issue) {
            $errors[] = [
                'type' => $is_view ? 'Instanciación en Vista' : 'Infracción de Handler',
                'file' => $relative_path,
                'message' => $issue
            ];
        }
        echo "  \033[31m✕ $relative_path\033[0m (Error de estructura o regla DI)\n";
    } else {
        $module_passed++;
    }
}
echo "  Módulos (Handlers y Vistas): \033[32m$module_passed OK\033[0m, \033[31m$module_failed Errores\033[0m.\n\n";


// --- Phase 3: Model CQRS enforcement ---
echo "\033[1;36m[Fase 3] Validando Patrón CQRS en Modelos...\033[0m\n";
$model_passed = 0;
$model_failed = 0;

$models_dir = ROOT_PATH . 'shared/models';
$model_files = [];
if (is_dir($models_dir)) {
    $model_files = get_php_files($models_dir);
}

if (empty($model_files)) {
    echo "  \033[33mℹ No se encontraron modelos en shared/models/ (Omitido)\033[0m\n\n";
} else {
    foreach ($model_files as $file) {
        $relative_path = str_replace(ROOT_PATH, '', $file);
        $content = file_get_contents($file);
        $filename = basename($file);
        
        $cqrs_issues = check_cqrs_pattern($filename, $content);
        
        if (!empty($cqrs_issues)) {
            $model_failed++;
            foreach ($cqrs_issues as $issue) {
                $errors[] = [
                    'type' => 'Infracción CQRS',
                    'file' => $relative_path,
                    'message' => $issue
                ];
            }
            echo "  \033[31m✕ $relative_path\033[0m (Infracción CQRS)\n";
        } else {
            $model_passed++;
        }
    }
    echo "  Modelos: \033[32m$model_passed OK\033[0m, \033[31m$model_failed Errores\033[0m.\n\n";
}


// --- Final Report ---
echo "==================================================\n";
if (empty($errors)) {
    echo "\033[1;32m🛡️  ¡GUARDIAN APROBADO! Todos los análisis pasaron con éxito.\033[0m\n\n";
    exit(0);
} else {
    echo "\033[1;31m🛡️  ¡GUARDIAN ALERTA! Se encontraron " . count($errors) . " errores:\033[0m\n\n";
    foreach ($errors as $i => $err) {
        echo sprintf("%d) [\033[33m%s\033[0m] \033[1m%s\033[0m\n   %s\n\n", $i + 1, $err['type'], $err['file'], $err['message']);
    }
    exit(1);
}


// --- Helper Functions ---

/**
 * Encuentra recursivamente todos los archivos PHP en un directorio
 */
function get_php_files(string $dir): array
{
    $files = [];
    if (!is_dir($dir)) return [];
    
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            // Ignorar directorios de control y temporales
            if (str_contains($path, '/.git/') || 
                str_contains($path, '/.gemini/') || 
                str_contains($path, '/scratch/') || 
                str_contains($path, '/bin/')) {
                continue;
            }
            $files[] = $path;
        }
    }
    return $files;
}

/**
 * Valida la sintaxis del archivo PHP usando `php -l`
 */
function check_syntax(string $file): ?string
{
    $cmd = "php -l " . escapeshellarg($file) . " 2>&1";
    exec($cmd, $output, $return_var);
    if ($return_var !== 0) {
        return implode("\n", $output);
    }
    return null;
}

/**
 * Analiza el contenido de un Handler para evitar la presencia de HTML directo
 */
function check_html_in_handler(string $content): array
{
    $issues = [];
    $tokens = token_get_all($content);
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            $type = $token[0];
            $value = $token[1];
            
            // 1. Verificar HTML en texto plano fuera de etiquetas <?php (T_INLINE_HTML)
            if ($type === T_INLINE_HTML) {
                if (trim($value) !== '' && preg_match('/<[a-zA-Z\/][^>]*>/', $value)) {
                    $issues[] = "Se detectó HTML plano fuera de las etiquetas PHP: '" . trim($value) . "'";
                }
            }
            
            // 2. Verificar HTML dentro de strings constantes (ej: echo "<div>")
            if ($type === T_CONSTANT_ENCAPSED_STRING) {
                if (preg_match('/<(html|div|span|p|h[1-6]|a|button|section|ul|li|form|input|label|header|footer|body|table|tr|td|th)\b[^>]*>/i', $value, $matches)) {
                    $issues[] = "Se detectó etiqueta HTML literal '" . $matches[0] . "' dentro de un string de código.";
                }
            }
        }
    }
    return $issues;
}

/**
 * Revisa el cumplimiento de CQRS en modelos
 */
function check_cqrs_pattern(string $filename, string $content): array
{
    $issues = [];
    $classname = basename($filename, '.php');
    
    $is_query = preg_match('/Query(Model)?s?$/i', $classname);
    $is_command = preg_match('/Command(Model)?s?$/i', $classname);
    
    if (!$is_query && !$is_command) {
        $issues[] = "El modelo '$classname' no cumple con la nomenclatura CQRS. Debe finalizar con 'Query' o 'Command'.";
        return $issues;
    }
    
    // Buscar operaciones de lectura y escritura usando tokens
    $tokens = token_get_all($content);
    $has_db_read = false;
    $has_db_write = false;
    $has_select_sql = false;
    $has_write_sql = false;
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            $type = $token[0];
            $value = $token[1];
            
            if ($type === T_STRING) {
                // Verificar llamadas a métodos de la clase Db
                if (strtolower($value) === 'findall' || strtolower($value) === 'findfirst' || strtolower($value) === 'getscalar') {
                    $has_db_read = true;
                }
                if (strtolower($value) === 'insert' || strtolower($value) === 'update' || strtolower($value) === 'delete') {
                    $has_db_write = true;
                }
            }
            
            if ($type === T_CONSTANT_ENCAPSED_STRING) {
                // Buscar palabras clave SQL en strings
                if (preg_match('/\bSELECT\b/i', $value)) {
                    $has_select_sql = true;
                }
                if (preg_match('/\b(INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM)\b/i', $value)) {
                    $has_write_sql = true;
                }
            }
        }
    }
    
    if ($is_query) {
        if ($has_db_write) {
            $issues[] = "Modelo de consulta (Query) realiza llamadas de escritura de base de datos (insert/update/delete).";
        }
        if ($has_write_sql) {
            $issues[] = "Modelo de consulta (Query) contiene sentencias SQL de escritura (INSERT/UPDATE/DELETE).";
        }
    }
    
    if ($is_command) {
        if ($has_db_read) {
            $issues[] = "Modelo de acción (Command) realiza llamadas de lectura de base de datos (findAll/findFirst/getScalar).";
        }
        if ($has_select_sql) {
            $issues[] = "Modelo de acción (Command) contiene sentencias SQL de lectura (SELECT).";
        }
    }
    
    return $issues;
}

/**
 * Revisa que no se utilicen instanciaciones manuales usando 'new'.
 * En las vistas ($strict_ban = true), está prohibido al 100%.
 */
function check_manual_instantiation(string $content, bool $strict_ban = false): array
{
    $issues = [];
    $tokens = token_get_all($content);
    
    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_NEW) {
            $className = '';
            for ($j = $i + 1; $j < count($tokens); $j++) {
                $nextToken = $tokens[$j];
                if (is_array($nextToken)) {
                    if ($nextToken[0] === T_STRING) {
                        $className = $nextToken[1];
                        break;
                    }
                    if ($nextToken[0] !== T_WHITESPACE) {
                        break;
                    }
                } else {
                    break;
                }
            }
            
            if ($strict_ban) {
                $issues[] = "Instanciación manual prohibida en Vistas ('new " . ($className ?: '') . "'). Las vistas sólo deben recibir y renderizar datos pasados por el controlador.";
                continue;
            }
            
            // Permitir excepciones, errores y objetos de utilidad nativos comunes en Handlers
            if (preg_match('/(Exception|Error)$/i', $className) || 
                in_array(strtolower($className), ['datetime', 'datetimeimmutable', 'stdclass'])) {
                continue;
            }
            
            $issues[] = "Instanciación manual detectada con 'new " . ($className ?: '') . "'. Las dependencias deben inyectarse a través del Contenedor DI.";
        }
    }
    return $issues;
}
