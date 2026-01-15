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
use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\ProxyLoop;
use aquarelay\network\raklib\RakLibInterface;
use aquarelay\player\PlayerManager;
use aquarelay\utils\Colors;
use aquarelay\utils\MainLogger;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class ProxyServer {

	public const NAME = "AquaRelay";
	public const VERSION = "1.0.0-alpha1"; // Semver
	public const IS_DEVELOPMENT = true;
	public RakLibInterface $interface;
	private MainLogger $logger;
	private ProxyConfig $config;
	private static ?self $instance = null;
	private PlayerManager $playerManager;

	/**
	 * Returns a server instance, can be nullable
	 * @return ProxyServer|null
	 */
	public static function getInstance() : ?ProxyServer
	{
		return self::$instance;
	}

	/**
	 * Returns the configuration of proxy
	 * @return ProxyConfig
	 */
	public function getConfig() : ProxyConfig
	{
		return $this->config;
	}

	public function getLogger() : MainLogger
	{
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

	public function getMotd() : string
	{
		return $this->getConfig()->getGameSettings()->getMotd();
	}

	public function getSubMotd() : string
	{
		return $this->getConfig()->getGameSettings()->getSubMotd();
	}

	public function isDebug() : bool
	{
		return $this->getConfig()->getMiscSettings()->isDebugMode();
	}

	public function getMaxPlayers() : int
	{
		return $this->getConfig()->getGameSettings()->getMaxPlayers();
	}

	/**
	 * Returns the latest supported version of Minecraft
	 * @return string
	 */
	public function getMinecraftVersion() : string
	{
		return ProtocolInfo::MINECRAFT_VERSION_NETWORK;
	}

	public function getName() : string
	{
		return self::NAME;
	}

	public function getVersion() : string
	{
		return self::VERSION;
	}

	public function getDataPath() : string
	{
		return $this->dataPath;
	}

	public function getResourcePath() : string
	{
		return $this->resourcePath;
	}

	public function getPlayerManager() : PlayerManager
	{
		return $this->playerManager;
	}

	public function __construct(
		private string $dataPath,
		private string $resourcePath
	){
		if (self::$instance !== null) {
			throw new \LogicException("Server instance is already initialized");
		}

		self::$instance = $this;
		$startTime = microtime(true);
		$configFile = $this->dataPath . "config.yml";

		if (!file_exists($configFile)) {
			$source = $this->resourcePath . "config.yml";

			if (!file_exists($source)) {
				throw new \RuntimeException("Default configuration file missing in resources folder");
			}

			$data = file_get_contents($source);
			if ($data === false) {
				throw new \RuntimeException("Failed to read default config.yml from resources");
			}

			if (file_put_contents($configFile, $data) === false) {
				throw new \RuntimeException("Failed to create config.yml. Please check permissions.");
			}
		}
		$this->config = ProxyConfig::load($configFile);
		$this->logger = new MainLogger("Main Thread", $this->getConfig()->getMiscSettings()->getLogName(), $this->isDebug());

		if (self::IS_DEVELOPMENT){
			$this->logger->warning("You are using development build. Be careful, your progress may be lost in future.");
		}

		$this->logger->info("Loading server configuration");

		$this->logger->info("Starting " . $this->getName() . " version " . $this->getVersion());
		$this->logger->info("This server is running Minecraft: Bedrock Edition " . Colors::AQUA . "v" . $this->getMinecraftVersion());

		$this->playerManager = new PlayerManager();

		ZlibCompressor::setInstance(new ZlibCompressor(ZlibCompressor::DEFAULT_LEVEL, ZlibCompressor::DEFAULT_THRESHOLD, ZlibCompressor::DEFAULT_MAX_DECOMPRESSION_SIZE));
		$this->logger->debug("ZLib compressor initialized");

		$this->logger->info("Initializing RakLib Interface...");
		$this->interface = new RakLibInterface($this->dataPath, $this->logger, $this->getAddress(), $this->getPort(), $this->getConfig()->getNetworkSettings()->getMaxMtu());
		$this->interface->setName($this->getMotd(), $this->getSubMotd());

		$this->logger->info("Listening on {$this->getAddress()}:{$this->getPort()}");

		$this->interface->start();

		$this->logger->info("Proxy started! (" . round(microtime(true) - $startTime, 3) ."s)");

		$loop = new ProxyLoop($this);
		$loop->run();
	}

}
