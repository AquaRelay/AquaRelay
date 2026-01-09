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

namespace aquarelay\config;

final class MiscSettings {

	public function __construct(
		private bool $debugMode,
		private string $logName
	){}

	public function isDebugMode() : bool{
		return $this->debugMode;
	}

	public function setDebugMode(bool $debugMode) : void{
		$this->debugMode = $debugMode;
	}

	public function getLogName() : string {
		return $this->logName;
	}

	public function setLogName(string $logName) : void
	{
		$this->logName = $logName;
	}
}
