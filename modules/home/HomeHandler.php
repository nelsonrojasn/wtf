<?php

class HomeHandler
{
	public function handle($request)
	{
		// Calculamos cuánto tiempo lleva procesando la petición
		$time_sec = $_SERVER['REQUEST_TIME_FLOAT'] ? (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) : 0;
		return view("home/views/index", compact("time_sec"), "default");
	}
}
