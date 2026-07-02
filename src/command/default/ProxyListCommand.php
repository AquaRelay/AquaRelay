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

namespace aquarelay\command\default;

use aquarelay\command\builder\CommandBuilder;
use aquarelay\command\Command;
use aquarelay\command\sender\CommandSender;
use aquarelay\lang\TranslationFactory;
use aquarelay\permission\DefaultPermissionNames;
use aquarelay\server\BackendServer;
use function array_map;
use function count;
use function implode;

class ProxyListCommand extends Command
{
	public function getBuilder() : CommandBuilder
	{
		return new CommandBuilder(
			"proxylist",
			TranslationFactory::translate('command.list.description'),
			"/proxylist",
			["plist"],
			DefaultPermissionNames::COMMAND_PROXYLIST
		);
	}

	public function execute(CommandSender $sender, string $label, array $args) : bool
	{
		$server = $sender->getServer();
		$onlinePlayers = $server->getOnlinePlayers();
		$servers = array_map(
			static fn(BackendServer $server) => $server->getName(),
			$server->getServerManager()->getAll()
		);

		/** @var array<string, string[]> $byServer */
		$byServer = [];

		foreach ($servers as $backend) {
			$byServer[$backend] = [];
		}

		foreach ($server->getOnlinePlayers() as $player) {
			$backend = $player->getBackendServer()?->getName();
			$byServer[$backend][] = $player->getName();
		}

		foreach ($byServer as $serverName => $players) {
			$count = count($players);

			$names = $count > 0 ? implode(', ', $players) : '';
			$sender->sendMessage(TranslationFactory::translate('command.list.entry', [$serverName, $names]));

		}
		$sender->sendMessage(TranslationFactory::translate('command.list.online', [count($onlinePlayers)]));
		return true;
	}
}
