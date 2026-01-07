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

use aquarelay\config\ProxyConfig;
use aquarelay\network\ProxyLoop;
use aquarelay\network\raklib\RakLibInterface;
use aquarelay\utils\MainLogger;

class ProxyServer {

	public RakLibInterface $interface;
	private MainLogger $logger;
	private ProxyConfig $config;
	private static ?self $instance = null;

	public static function getInstance() : ?ProxyServer{
		return self::$instance;
	}

	public function getConfig() : ProxyConfig {
		return $this->config;
	}

	public function getLogger() : MainLogger {
		return $this->logger;
	}

	public function __construct(ProxyConfig $config){
		$startTime = microtime(true);
		self::$instance = $this;
		$this->config = $config;

		$this->logger = new MainLogger("Main Thread", "proxy.log", $config->debugMode);
		$this->logger->info("Starting proxy server...");

		$this->logger->info("Initializing RakLib Interface...");
		$this->interface = new RakLibInterface($this->logger, $config->bindAddress, $config->bindPort);

		$this->logger->info("Listening on $config->bindAddress:$config->bindPort");

		$this->interface->start();

		$this->logger->info("Proxy started! (" . round(microtime(true) - $startTime, 3) ."s)");

		$loop = new ProxyLoop($this, $this->config);
		$loop->run();
	}

}