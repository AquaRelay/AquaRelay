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
		private readonly MiscSettings $miscSettings,
		private readonly GameSettings $gameSettings
	){}

	public static function load(string $file) : self{
		$data = Yaml::parseFile($file);
		
		$networkSettings = $data["network-settings"];
		$miscSettings = $data["misc-settings"];
		$gameSettings = $data["game-settings"];

		return new self(
			new NetworkSettings(
				$networkSettings["bind"]["address"],
				(int) $networkSettings["bind"]["port"],
				$networkSettings["backend"]["address"],
				(int) $networkSettings["backend"]["port"],
				(int) $networkSettings["session-timeout"],
				(int) $networkSettings["max-mtu"]
			),
			new MiscSettings(
				(bool) $miscSettings["debug-mode"],
				$miscSettings["log-name"]
			),
			new GameSettings(
				(int) $gameSettings["max-players"],
				$gameSettings["motd"],
				$gameSettings["sub-motd"]
			),
		);
	}

	public function getNetworkSettings() : NetworkSettings {
		return $this->networkSettings;
	}

	public function getMiscSettings() : MiscSettings {
		return $this->miscSettings;
	}

	public function getGameSettings() : GameSettings {
		return $this->gameSettings;
	}
}
