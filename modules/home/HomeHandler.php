<?php

class HomeHandler
{
	public function handle($request)
	{
		// Calculamos cuánto tiempo lleva procesando la petición
		$time_micros = $_SERVER['REQUEST_TIME_FLOAT'] ? (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000000 : 0;
		return view("home/views/index", compact("time_micros"));
	}
}
