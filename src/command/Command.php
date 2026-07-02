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

namespace aquarelay\command;

use aquarelay\command\builder\CommandBuilder;
use aquarelay\command\sender\CommandSender;
use function is_array;

abstract class Command {

	private CommandBuilder $builder;

	final public function __construct() {
		$this->builder = $this->getBuilder();
	}

	abstract public function getBuilder() : CommandBuilder;

	abstract public function execute(
		CommandSender $sender,
		string $label,
		array $args
	) : bool;

	public function getName() : string {
		return $this->builder->getName();
	}

	public function getAliases() : array {
		return $this->builder->getAliases();
	}

	public function getPermission() : string|array|null {
		return $this->builder->getPermission();
	}

	public function testPermission(CommandSender $sender) : bool {
		if ($this->getPermission() === null || $this->getPermission() === "") return true;

		if (is_array($this->getPermission())){
			foreach ($this->getPermission() as $perm){
				if ($sender->hasPermission($perm)){
					return true;
				}
			}
			return false;
		}

		return $sender->hasPermission($this->getPermission());
	}
}
