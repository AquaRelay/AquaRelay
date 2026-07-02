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
use aquarelay\ProxyServer;

class ProxyStopCommand extends Command
{
	public function getBuilder() : CommandBuilder
	{
		return new CommandBuilder(
			"proxystop",
			TranslationFactory::translate('command.stop.description'),
			"/proxystop",
			["ps"],
			DefaultPermissionNames::COMMAND_PROXYSTOP
		);
	}

	public function execute(CommandSender $sender, string $label, array $args) : bool
	{
		ProxyServer::getInstance()->shutdown();
		return true;
	}
}
