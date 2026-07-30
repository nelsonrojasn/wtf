<?php

class HomeHandler {
	public function handle($request)
	{
		return view("home/views/index");
	}
}