<?php

//*Locale*
setlocale(LC_ALL, 'es_CL');

//*Timezone*
ini_set('date.timezone', 'America/Santiago');

//*error reporting*
error_reporting(E_ALL);
ini_set('display_errors', 'On');

define('ROOT_PATH', dirname(__DIR__) . '/');
const CORE_PATH = ROOT_PATH . 'core/';

require_once ROOT_PATH . 'config/settings.php';
require_once CORE_PATH . 'bootstrap.php';

// Registrar el inicio del worker para calcular el uptime en system/health
$worker_start_time = microtime(true);
$routes = require_once ROOT_PATH . 'config/routes.php';

function handle_http_request($routes, $worker_start_time) {
    $start_time = microtime(true);

    ob_start();

    // Inicializar Contenedor DI y cargar dependencias
    $container = new Container();
    $dependenciesResolver = require ROOT_PATH . 'config/dependencies.php';
    if (is_callable($dependenciesResolver)) {
        $dependenciesResolver($container);
    }

    $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';

    $headers = get_request_headers();

    $request = [
        'method'            => $method,
        'path'              => $uri_path,
        'headers'           => $headers,
        'query'             => $_GET,
        'body'              => get_body_content($headers),
        'params'            => [],
        'start_time'        => $start_time,
        'worker_start_time' => $worker_start_time
    ];

    if (!isset($routes[$uri_path])) {
        http_response_code(404);
        echo '404 Not Found';
    } else {
        $route = $routes[$uri_path];

        if (!empty($route['filters'])) {
		    foreach ($route['filters'] as $filter_name) {
		        $filter_file = ROOT_PATH . "shared/filters/{$filter_name}.php";
		        if (file_exists($filter_file)) {
		            require_once $filter_file;
		            // Si el filtro devuelve false, un Response o redirige, cortamos la ejecución
		            $result = $filter_name($request);
		            if ($result instanceof Response) {
		                $result->send();
		                return;
		            }
		            if ($result === false) {
		                return;
		            }
		        }
		    }
		}
        
        // Carga y ejecución del handler
        require_once ROOT_PATH . 'modules/' . $route['module'] . '/' . $route['starter'] . '.php';
        $handler = $container->get($route['starter']);
        $response = $handler->handle($request);

        if ($response instanceof Response) {
            $response->send();
        } elseif (is_string($response)) {
            echo $response;
        }
    }

    // Cabecera de telemetría (se calcula para 200 y 404 por igual)
    $elapsed = (microtime(true) - $start_time) * 1000;
    header("X-Response-Time: " . number_format($elapsed, 3) . "ms");

    // Volcamos el buffer de salida acumulado al cliente
    if (ob_get_level() > 0) {
        ob_end_flush();
    }

    // Limpieza de estado para el siguiente request del worker
    unset($request, $route, $handler, $headers);
    $_GET     = [];
    $_POST    = [];
    $_FILES   = [];
    $_COOKIE  = [];
    $_REQUEST = [];
}

$is_frankenphp_worker = isset($_SERVER['FRANKENPHP_WORKER']) || function_exists('frankenphp_handle_request');

if ($is_frankenphp_worker) {
    // Modo Worker: Bucle continuo controlado por FrankenPHP.
    // Procesamos hasta un número máximo de peticiones antes de reiniciar para evitar fugas de memoria.
    $max_requests = (int)($_SERVER['MAX_REQUESTS'] ?? 10000);

    for ($nb_requests = 0; $nb_requests < $max_requests; ++$nb_requests) {
        // frankenphp_handle_request detiene el bucle y espera a que llegue una nueva petición HTTP.
        // Cuando llega, ejecuta la función callback y luego continúa el bucle.
        $running = frankenphp_handle_request(function () use (&$routes, $worker_start_time) {
            handle_http_request($routes, $worker_start_time);
        });

        // Si el servidor FrankenPHP indica que se debe apagar el worker, rompemos el bucle
        if (!$running) {
            break;
        }
    }
} else {
    // Modo tradicional: PHP-FPM o el servidor de desarrollo integrado de PHP (ej. php -S localhost:8000)
    // Procesamos la petición una única vez y el script finaliza su ciclo de vida.
    handle_http_request($routes, $worker_start_time);
}