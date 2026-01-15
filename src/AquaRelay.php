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

namespace aquarelay;

use aquarelay\utils\Colors;
use Throwable;
use function extension_loaded;
use function version_compare;

require dirname(__DIR__) . '/vendor/autoload.php';

if (Colors::supportsColors()){
	@sapi_windows_vt100_support(STDOUT, true);
}

function error(string $message) : void
{
	echo Colors::RED . "Error: $message" . Colors::RESET . "\n";
}

function checkDependencies(): void
{
	if (version_compare("8.1.0", PHP_VERSION) > 0) {
		error("PHP 8.1.0 or greater is required");
		exit(1);
	}

	$required = [
		"yaml",
		"sockets"
	];

	foreach ($required as $depend) {
		if (!extension_loaded($depend)) {
			error("$depend extension is not installed.");
			exit(1);
		}
	}
}

function setEntries() : void
{
	ini_set("display_errors", "1");
	ini_set("display_startup_errors", "1");
	ini_set("default_charset", "UTF-8");
	ini_set("allow_url_fopen", "1");
}

function start() : void
{
	checkDependencies();
	setEntries();
	error_reporting(E_ALL);
	date_default_timezone_set("UTC");

	$cwd = realpath(getcwd());
	$dataPath = $cwd . DIRECTORY_SEPARATOR;

	try {
		new ProxyServer(
			$dataPath,
			RESOURCE_PATH
		);
	} catch(Throwable $e) {
		error($e->getMessage());
		exit(1);
	}
}

start();