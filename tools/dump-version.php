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

require dirname(__DIR__) . '/vendor/autoload.php';

use aquarelay\ProxyServer;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

$options = [
	"version" => ProxyServer::VERSION,
	"is_dev" => defined(ProxyServer::class . '::IS_DEVELOPMENT')
		? (ProxyServer::IS_DEVELOPMENT ? "true" : "false")
		: "false",
	"mcpe_version" => ProtocolInfo::MINECRAFT_VERSION_NETWORK
];

if(!isset($argv[1]) || !isset($options[$argv[1]])){
	fwrite(STDERR, "Usage: php dump-version.php <" . implode("|", array_keys($options)) . ">\n");
	exit(1);
}

echo $options[$argv[1]];
