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

final class GameSettings {

	public function __construct(
		private int $maxPlayers,
		private string $motd,
		private string $subMotd
	){}

	public function getMaxPlayers() : int{
		return $this->maxPlayers;
	}

	public function setMaxPlayers(int $maxPlayers) : void{
		$this->maxPlayers = $maxPlayers;
	}

	public function getMotd() : string{
		return $this->motd;
	}

	public function setMotd(string $motd) : void{
		$this->motd = $motd;
	}

	public function getSubMotd() : string{
		return $this->subMotd;
	}

	public function setSubMotd(string $subMotd) : void{
		$this->subMotd = $subMotd;
	}
}
