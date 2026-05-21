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

use aquarelay\command\default\ProxyListCommand;
use aquarelay\command\default\ProxyPluginsCommand;
use aquarelay\command\default\ProxyStopCommand;
use aquarelay\command\default\ProxyTransferCommand;
use aquarelay\command\sender\CommandSender;
use function array_shift;
use function explode;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

class SimpleCommandMap implements CommandMap {

	/** @var Command[] */
	private array $commands = [];

	public function __construct()
	{
		$this->registerDefaults();
	}

	public function registerDefaults() : void
	{
		$this->register(new ProxyListCommand());
		$this->register(new ProxyPluginsCommand());
		$this->register(new ProxyStopCommand());
		$this->register(new ProxyTransferCommand());
	}

	public function register(Command $command) : bool {
		if (isset($this->commands[$command->getName()])) return false;

		$this->commands[strtolower($command->getName())] = $command;

		foreach ($command->getAliases() as $alias) {
			$this->commands[strtolower($alias)] = $command;
		}

		return true;
	}

	public function getCommand(string $name) : ?Command
	{
		return $this->commands[strtolower($name)] ?? null;
	}

	public function dispatch(CommandSender $sender, string $line) : bool
	{
		$args = explode(" ", trim($line));
		$label = array_shift($args);

		if ($label === null) {
			return false;
		}

		if (str_starts_with($label, "/")) {
			$label = substr($label, 1);
		}

		$label = strtolower($label);
		if (empty($label)) return false;

		if (!isset($this->commands[$label])) {
			$sender->sendMessage("§cUnknown command: $label. Use /help for a list of available commands.");
			return false;
		}

		$command = $this->commands[$label];

		if (!$command->testPermission($sender)) {
			$sender->sendMessage("§cYou don't have permission to use this command.");
			return false;
		}

		$command->execute($sender, $label, $args);
		return true;
	}

	public function getCommands() : array {
		return $this->commands;
	}
}
