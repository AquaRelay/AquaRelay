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

	public function getAddress() : string
	{
		return $this->getConfig()->getNetworkSettings()->getBindAddress();
	}

	public function getPort() : int
	{
		return $this->getConfig()->getNetworkSettings()->getBindPort();
	}

	public function getMotd() : string {
		return $this->getConfig()->getGameSettings()->getMotd();
	}

	public function getSubMotd() : string {
		return $this->getConfig()->getGameSettings()->getSubMotd();
	}

	public function isDebug() : bool
	{
		return $this->getConfig()->getGameSettings()->isDebugMode();
	}

	public function __construct(ProxyConfig $config){
		$startTime = microtime(true);
		self::$instance = $this;
		$this->config = $config;

		$this->logger = new MainLogger("Main Thread", "proxy.log", $this->isDebug());
		$this->logger->info("Starting proxy server...");

		$this->logger->info("Initializing RakLib Interface...");
		$this->interface = new RakLibInterface($this->logger, $this->getAddress(), $this->getPort());
		$this->interface->setName($this->getMotd(), $this->getSubMotd());

		$this->logger->info("Listening on {$this->getAddress()}:{$this->getPort()}");

		$this->interface->start();

		$this->logger->info("Proxy started! (" . round(microtime(true) - $startTime, 3) ."s)");

		$loop = new ProxyLoop($this, $this->config);
		$loop->run();
	}

}