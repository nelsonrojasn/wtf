<?php

class HomeHandler implements HandlerInterface
{
	public function handle(array $request): Response
	{
		// Calculamos cuánto tiempo lleva procesando la petición
		$time_sec = $_SERVER['REQUEST_TIME_FLOAT'] ? (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) : 0;
		return view("home/views/index", ["time_sec" => $time_sec], "default");
	}
}
