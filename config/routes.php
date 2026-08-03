<?php
/**
 * Lista de rutas de la aplicación
 */
return [
	'/' => [
		'module' => 'home',
		'starter' => 'HomeHandler',
		'filters' => [],
		'description' => 'Home Page'
	],
	'/users' => [
		'module' => 'users',
		'starter' => 'UsersHandler',
		'filters' => [],
		'description' => 'Lista de Usuarios'
	],
];