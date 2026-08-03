<?php

class HomeHandler implements HandlerInterface
{
	private FspEngine $fsp;

	public function __construct(FspEngine $fsp)
	{
		$this->fsp = $fsp;
	}

	public function handle(array $request): Response
	{
		// Fórmula de ejemplo en formato FSP
		$formula = "begin\n  val = + (param.a, param.b)\n  result = val\nend";
		
		// Compilar y ejecutar la fórmula
		$bytecode = $this->fsp->compile($formula);
		$out = $this->fsp->execute($bytecode, ['a' => 15, 'b' => 27]);

		// Calculamos cuánto tiempo lleva procesando la petición
		$time_sec = $_SERVER['REQUEST_TIME_FLOAT'] ? (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) : 0;
		return view("home/views/index", [
			"time_sec" => $time_sec,
			"fsp_result" => $out['val'] ?? null
		], "default");
	}
}
