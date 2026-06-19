<?php

/*
 *
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *               |_|                              |___/
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

use aquarelay\utils\InstanceTrait;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function unlink;

class ConfigUpdater
{
	use InstanceTrait;

	public const CONFIG_VERSION = 5;

	public function isUpToDate(int $configVersion) : bool
	{
		return $configVersion >= self::CONFIG_VERSION;
	}

	public function update(string $file, string $path) : void
	{
		$configFile = $path . 'config.yml';
		$data = file_get_contents($configFile);
		if (file_exists($file)) {
			unlink($file);
			file_put_contents($file, $data);
		}
	}
}
