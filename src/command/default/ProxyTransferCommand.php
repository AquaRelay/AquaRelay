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
use aquarelay\player\Player;
use aquarelay\ProxyServer;

class ProxyTransferCommand extends Command
{
	public function getBuilder() : CommandBuilder
	{
		return new CommandBuilder(
			"transfer",
			TranslationFactory::translate('command.transfer.description'),
			"/transfer <server> [player]",
			["server"],
			[DefaultPermissionNames::COMMAND_TRANSFER_SELF, DefaultPermissionNames::COMMAND_TRANSFER_OTHER]
		);
	}

	public function execute(CommandSender $sender, string $label, array $args) : bool
	{
		if (!isset($args[0])) {
			$sender->sendMessage(TranslationFactory::translate('command.usage', [$this->getBuilder()->getUsage()]));
			return false;
		}

		$serverName = $args[0];
		$proxy = ProxyServer::getInstance();
		$backend = $proxy->getServerManager()->get($serverName);

		if ($backend === null) {
			$sender->sendMessage(TranslationFactory::translate('command.transfer.server_not_found', [$serverName]));
			return false;
		}

		if ($sender instanceof Player) {
			if (!isset($args[1])) {
				if (!$sender->hasPermission(DefaultPermissionNames::COMMAND_TRANSFER_SELF)) {
					$sender->sendMessage(TranslationFactory::translate('command.transfer.no_permission_self'));
					return false;
				}

				$sender->transferToBackend($backend);
				return true;
			}

			if (!$sender->hasPermission(DefaultPermissionNames::COMMAND_TRANSFER_OTHER)) {
				$sender->sendMessage(TranslationFactory::translate('command.transfer.no_permission_other'));
				return false;
			}

			$targetName = $args[1];
			$target = $proxy->getPlayerByName($targetName);

			if ($target === null) {
				$sender->sendMessage(TranslationFactory::translate('command.transfer.player_not_found', [$targetName]));
				return false;
			}

			$target->transferToBackend($backend);
			$sender->sendMessage(TranslationFactory::translate('command.transfer.success', [$targetName, $serverName]));
			return true;
		}

		if (!isset($args[1])) {
			$sender->sendMessage(TranslationFactory::translate('command.usage', [$this->getBuilder()->getUsage()]));
			return false;
		}

		$targetName = $args[1];
		$target = $proxy->getPlayerByName($targetName);

		if ($target === null) {
			$sender->sendMessage(TranslationFactory::translate('command.transfer.player_not_found', [$targetName]));
			return false;
		}

		$target->transferToBackend($backend);
		$sender->sendMessage(TranslationFactory::translate('command.transfer.success', [$targetName, $serverName]));
		return true;
	}
}
