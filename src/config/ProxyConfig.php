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

use Symfony\Component\Yaml\Yaml;

final class ProxyConfig {

	public function __construct(
		private readonly NetworkSettings $networkSettings,
		private readonly GameSettings $gameSettings
	){}

	public static function load(string $file) : self{
		$data = Yaml::parseFile($file);

		$network = new NetworkSettings(
			$data["network"]["bind"]["address"],
			(int) $data["network"]["bind"]["port"],
			$data["network"]["backend"]["address"],
			(int) $data["network"]["backend"]["port"]
		);

		$game = new GameSettings(
			(int) $data["game-settings"]["session-timeout"],
			(bool) $data["game-settings"]["debug-mode"],
			(int) $data["game-settings"]["max-players"],
			$data["game-settings"]["motd"],
			$data["game-settings"]["sub-motd"]
		);

		return new self($network, $game);
	}

	public function getNetworkSettings() : NetworkSettings {
		return $this->networkSettings;
	}

	public function getGameSettings() : GameSettings {
		return $this->gameSettings;
	}
}
