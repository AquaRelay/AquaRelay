<?php

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