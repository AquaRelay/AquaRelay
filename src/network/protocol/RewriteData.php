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

namespace aquarelay\network\protocol;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\GameRule;

class RewriteData
{

	public int $entityId = 0;

	public int $originalEntityId = 0;

	public int $dimension = DimensionIds::OVERWORLD;

	public ?Vector3 $spawnPosition = null;

	public float $pitch = 0.0;

	public float $yaw = 0.0;

	/**
	 * @var GameRule[]
	 * @phpstan-var array<string, GameRule>
	 */
	public array $gameRules = [];

	public ?TransferCallback $transferCallback = null;

	public bool $immobileFlag = false;

	public bool $postTransferSpawnInitialized = false;
}
