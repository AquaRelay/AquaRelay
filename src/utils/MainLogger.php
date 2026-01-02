<?php

/*
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *             |_|                                |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AquaRelay Team
 * @link https://www.aquarelay.dev/
 *
 */

declare(strict_types=1);

namespace aquarelay\utils;

class MainLogger {

	private string $format =
		Colors::BLUE . "%s " .
		Colors::RESET . "[" . Colors::MATERIAL_GOLD . "%s" . Colors::RESET . "] " .
		"[" . "%s%s" . Colors::RESET . "]" .
		Colors::WHITE . " %s" .
		Colors::RESET;

	public function __construct(private string $name, private bool $debug = false){}

	private function log(string $level, string $color, string $message) : void{
		$time = date("H:i:s");

		echo sprintf(
				$this->format,
				$time,
				$this->name,
				$color,
				$level,
				$message
			) . PHP_EOL;
	}

	public function info(string $message) : void{
		$this->log("INFO", Colors::GREEN, $message);
	}

	public function warn(string $message) : void{
		$this->log("WARN", Colors::YELLOW, $message);
	}

	public function error(string $message) : void{
		$this->log("ERROR", Colors::RED, $message);
	}

	public function alert(string $message) : void{
		$this->log("ALERT", Colors::DARK_RED, $message);
	}

	public function debug(string $message) : void{
		if(!$this->debug){
			return;
		}
		$this->log("DEBUG", Colors::GRAY, $message);
	}
}
