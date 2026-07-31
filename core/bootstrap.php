<?php

// core/bootstrap.php
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/EncryptedCookie.php';

spl_autoload_register(
    function($className) {
        $file = $className . '.php';
        $findOn = [
    		CORE_PATH, 
           	ROOT_PATH . 'shared/filters/',
           	ROOT_PATH . 'shared/models/'];
        
        foreach($findOn as $f) {
        	if (file_exists($f. $file)) {
		        require_once $f. $file;
		    }
        }
    }
);


// Redirigir al navegador
function redirect_to(string $url): Response
{
    $base = defined('PUBLIC_PATH') ? PUBLIC_PATH : '/';
    return new Response('', 302, ['Location' => $base . ltrim($url, '/')]);
}

function get_body_content(array $headers): mixed
{
    $content_type = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
    
    if (str_contains($content_type, 'application/json')) {
        $content_length = (int)($headers['Content-Length'] ?? $headers['content-length'] ?? 0);
        
        if ($content_length > 512 * 1024) {
            json(['error' => 'Payload demasiado grande'], 413);
            return null;
        }

        $raw_body = file_get_contents('php://input') ?: '';
        return json_decode($raw_body, true) ?? [];
    }

    return $_POST;
}

/**
 * Utilidades HTTP y Manejo de Respuestas de Napkin.
 * 
 * Contiene funciones para procesar cabeceras, enviar respuestas JSON/HTML y renderizar vistas.
 */

/**
 * Obtiene todas las cabeceras HTTP de la petición actual.
 * Es compatible con cualquier servidor: Apache, Nginx (FPM), CLI o FrankenPHP.
 */
function get_request_headers(): array
{
    // Si el servidor ya tiene una función nativa para esto, la usamos.
    if (function_exists('getallheaders')) {
        $all_headers = getallheaders();
        if ($all_headers !== false) {
            return $all_headers;
        }
    }
    
    // Si no, las extraemos manualmente del array superglobal $_SERVER.
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        // Las cabeceras HTTP en $_SERVER suelen empezar por "HTTP_" (ej. HTTP_USER_AGENT)
        if (str_starts_with($name, 'HTTP_')) {
            // 1. Quitamos el prefijo "HTTP_" para obtener "USER_AGENT"
            $sin_http = substr($name, 5);
            // 2. Reemplazamos los guiones bajos por espacios para obtener "USER AGENT"
            $con_espacios = str_replace('_', ' ', $sin_http);
            // 3. Pasamos todo a minúsculas para normalizar
            $minusculas = strtolower($con_espacios);
            // 4. Ponemos en mayúscula la primera letra de cada palabra para obtener "User Agent"
            $palabras_capitalizadas = ucwords($minusculas);
            // 5. Cambiamos los espacios por guiones para el formato final: "User-Agent"
            $key = str_replace(' ', '-', $palabras_capitalizadas);
            
            $headers[$key] = $value;
        }
        // Caso especial: Content-Type y Content-Length no siempre llevan el prefijo HTTP_
        elseif ($name === 'CONTENT_TYPE') {
            $headers['Content-Type'] = $value;
        } elseif ($name === 'CONTENT_LENGTH') {
            $headers['Content-Length'] = $value;
        }
    }
    return $headers;
}

/**
 * Envía una respuesta al navegador en formato JSON.
 */
function json(mixed $data, int $status = 200, array $headers = []): Response
{
    $opciones = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    $content = json_encode($data, $opciones);
    $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json; charset=utf-8';
    return new Response($content, $status, $headers);
}

/**
 * Envía una respuesta HTML personalizada.
 */
function html(string $html, int $status = 200, array $headers = []): Response
{
    $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/html; charset=utf-8';
    return new Response($html, $status, $headers);
}

/**
 * Escapa caracteres especiales de HTML para prevenir ataques de Cross-Site Scripting (XSS).
 * Es un alias/wrapper muy corto y conveniente de la función htmlspecialchars().
 * 
 * Ejemplo en la vista: <?= h($nombre) ?>
 * 
 * @param mixed $value El valor a escapar (se convertirá a texto automáticamente)
 * @return string El texto escapado y seguro
 */
function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Renderiza una vista HTML cargando un archivo PHP de la carpeta 'views'.
 */
function view(string $name, array $data = [], ?string $template = null, int $status = 200, array $headers = []): Response
{
    $template = $template ?? ($data['template'] ?? null);
    extract($data, EXTR_SKIP);
    
    $view_file_path = ROOT_PATH . 'modules/' . $name . '.php';
    
    ob_start();
    require $view_file_path;
    $yield = ob_get_clean();
    
    if ($template) {
        $template_file_path = ROOT_PATH . 'shared/templates/' . $template . '.php';
        ob_start();
        if (file_exists($template_file_path)) {
            require $template_file_path;
        } else {
            echo $yield;
        }
        $content = ob_get_clean();
    } else {
        $content = $yield;
    }
    
    $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/html; charset=utf-8';
    return new Response($content, $status, $headers);
}

/**
 * Renderiza un archivo parcial (cabecera, pie de página o componente)
 * dentro de otro archivo de vista de forma aislada.
 * 
 * @param string $name Nombre del archivo parcial dentro de 'shared/partials/'
 * @param array $data Variables locales que se le pasan al parcial
 */
function partial(string $name, array $data = []): void
{
    // Convierte las claves de un array en variables individuales para el parcial,
    // aislando su alcance de la vista principal.
    extract($data, EXTR_SKIP);
    
    // Cargamos el archivo parcial usando dirname(__DIR__) porque este archivo está en "core/"
    $partial_file_path = ROOT_PATH . 'shared/partials/' . $name . '.php';
    require $partial_file_path;
}