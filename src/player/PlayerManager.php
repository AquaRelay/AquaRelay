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

namespace aquarelay\player;

use aquarelay\utils\LoginData;

class PlayerManager {
	/** @var Player[] */
	private array $players = [];

	public function createPlayer($session, LoginData $data): Player {
		$player = new Player($session, $data);
		$this->players[spl_object_hash($session)] = $player;
		return $player;
	}

	public function getPlayerBySession($session): ?Player {
		return $this->players[spl_object_hash($session)] ?? null;
	}

	public function removePlayer($session): void {
		unset($this->players[spl_object_hash($session)]);
	}
}