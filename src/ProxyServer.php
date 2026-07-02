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

namespace aquarelay;

use aquarelay\command\sender\ConsoleCommandSender;
use aquarelay\command\SimpleCommandMap;
use aquarelay\config\ProxyConfig;
use aquarelay\event\default\server\ServerStartEvent;
use aquarelay\event\default\server\ServerStopEvent;
use aquarelay\lang\Language;
use aquarelay\lang\TranslationFactory;
use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\NetworkSession;
use aquarelay\network\NetworkSessionManager;
use aquarelay\network\raklib\RakLibInterface;
use aquarelay\network\raklib\RakLibPacketSender;
use aquarelay\permission\PermissionManager;
use aquarelay\player\Player;
use aquarelay\player\PlayerManager;
use aquarelay\plugin\loader\PharPluginLoader;
use aquarelay\plugin\PluginLoader;
use aquarelay\plugin\PluginManager;
use aquarelay\resourcepack\ResourcePackManager;
use aquarelay\server\ServerManager;
use aquarelay\task\TaskScheduler;
use aquarelay\utils\Colors;
use aquarelay\utils\ConsoleReaderThread;
use aquarelay\utils\MainLogger;
use aquarelay\utils\SignalHandler;
use aquarelay\utils\Utils;
use pmmp\thread\Thread;
use pmmp\thread\ThreadSafeArray;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\snooze\SleeperHandler;
use raklib\generic\DisconnectReason;
use function count;
use function error_get_last;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function is_dir;
use function microtime;
use function mkdir;
use function ord;
use function register_shutdown_function;
use function round;
use function set_exception_handler;
use function strtolower;
use function substr;
use const DIRECTORY_SEPARATOR;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_USER_ERROR;
use const STDERR;

class ProxyServer
{
	public const NAME = 'AquaRelay';
	public const VERSION = '1.0.2'; // Semver
	public const IS_DEVELOPMENT = false;
	public RakLibInterface $interface;
	private MainLogger $logger;
	private ProxyConfig $config;
	private Language $language;
	private static ?self $instance = null;
	private PlayerManager $playerManager;
	private PluginManager $pluginManager;
	private TaskScheduler $taskScheduler;
	private ServerManager $serverManager;
	private SimpleCommandMap $commandMap;
	private PermissionManager $permissionManager;
	private ResourcePackManager $resourcePackManager;
	private bool $crashed = false;

	private ConsoleCommandSender $consoleSender;

	private ThreadSafeArray $consoleQueue;

	private float $startProcessTime;

	private SleeperHandler $sleeper;

	public function __construct(
		private string $dataPath,
		private string $resourcePath
	) {
		if (self::$instance !== null) {
			throw new \LogicException('Server instance is already initialized');
		}

		$this->consoleSender = new ConsoleCommandSender($this);

		$this->consoleQueue = new ThreadSafeArray();

		$consoleThread = new ConsoleReaderThread($this->consoleQueue);
		$consoleThread->start(Thread::INHERIT_ALL);

		self::$instance = $this;
		$this->startProcessTime = microtime(true);
		$configFile = $this->dataPath . 'config.yml';

		if (!file_exists($configFile)) {
			$source = $this->resourcePath . 'config.yml';

			if (!file_exists($source)) {
				throw new \RuntimeException('Default configuration file missing in resources folder');
			}

			$data = file_get_contents($source);
			if ($data === false) {
				throw new \RuntimeException('Failed to read default config.yml from resources');
			}

			if (file_put_contents($configFile, $data) === false) {
				throw new \RuntimeException('Failed to create config.yml. Please check permissions.');
			}
		}
		$this->config = ProxyConfig::load($configFile, $this->resourcePath);
		$this->logger = new MainLogger('Main Thread', $this->getConfig()->getMiscSettings()->getLogName(), $this->isDebug());

		$selectedLang = $this->getConfig()->getMiscSettings()->getSelectedLanguage();
		$this->language = new Language($selectedLang);
		$this->logger->info(TranslationFactory::languageSelected($this->language->getFullName(), $this->language->getLang()));

		if (self::IS_DEVELOPMENT) {
			$this->logger->warning('You are using development build. Be careful, your progress may be lost in future.');
		}

		$this->logger->info('Loading server configuration');

		$this->logger->info('Starting ' . $this->getName() . ' version ' . $this->getVersion());
		$this->logger->info('This server is running Minecraft: Bedrock Edition ' . Colors::AQUA . 'v' . $this->getMinecraftVersion());

		$this->permissionManager = new PermissionManager($this->getConfig());
		$this->commandMap = new SimpleCommandMap();
		$this->playerManager = new PlayerManager();
		$this->taskScheduler = new TaskScheduler();
		$this->resourcePackManager = new ResourcePackManager(
			$this,
			$this->getConfig()->getResourcePackSettings(),
			$this->dataPath
		);

		set_exception_handler(fn(\Throwable $e) => $this->crash($e));
		register_shutdown_function(function () : void {
			$error = error_get_last();
			if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) !== 0) {
				$this->crash(new \Error("{$error['message']} in {$error['file']}:{$error['line']}"));
			}
		});

		register_shutdown_function([$this, 'shutdown']);

		new SignalHandler(fn() => $this->shutdown());

		$threshold = $this->getConfig()->getNetworkSettings()->getBatchThreshold();
		$compressionThreshold = $threshold >= 0 ? $threshold : null;

		$compressionLevel = $this->getConfig()->getNetworkSettings()->getCompressionLevel();
		if ($compressionLevel < 1 || $compressionLevel > 9) {
			throw new \RuntimeException('Compression level must be between 1 and 9');
		}

		ZlibCompressor::setInstance(new ZlibCompressor($compressionLevel, $compressionThreshold, ZlibCompressor::DEFAULT_MAX_DECOMPRESSION_SIZE));
		$this->logger->debug('ZLib compressor initialized');

		$this->logger->info('Initializing RakLib Interface...');
		$this->interface = new RakLibInterface($this->dataPath, $this->logger, $this->getAddress(), $this->getPort(), $this->getConfig()->getNetworkSettings()->getMaxMtu());
		$this->interface->setName($this);

		$this->sleeper = new SleeperHandler();
		$this->interface->setHandlers(
			$this->handleConnect(...),
			$this->handlePacket(...),
			$this->handleDisconnect(...),
			$this->handlePing(...)
		);

		$pluginsPath = $this->dataPath . 'plugins' . DIRECTORY_SEPARATOR;
		if (!is_dir($pluginsPath)) {
			@mkdir($pluginsPath, 0o755, true);
		}

		$pluginLoader = new PluginLoader($this, $pluginsPath);
		$this->pluginManager = new PluginManager($this, $pluginLoader);
		$this->pluginManager->registerLoader(new PharPluginLoader($this, $pluginsPath));
		$this->pluginManager->loadPlugins();

		$this->logger->info("Listening on {$this->getAddress()}:{$this->getPort()}");

		$this->interface->start();

		$this->serverManager = new ServerManager($this->getConfig()->getServerSettings());

		$this->logger->info('Proxy started! (' . round(microtime(true) - $this->startProcessTime, 3) . 's)');

		$event = new ServerStartEvent($this->startProcessTime);
		$event->call();

		$this->start();
	}

	/**
	 * Returns a server instance, can be nullable.
	 */
	public static function getInstance() : ?ProxyServer
	{
		return self::$instance;
	}

	/**
	 * Returns the configuration of proxy.
	 */
	public function getConfig() : ProxyConfig
	{
		return $this->config;
	}

	public function getLanguage() : Language
	{
		return $this->language;
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
	 * Returns all online proxied players.
	 */
	public function getOnlinePlayers() : array
	{
		return $this->playerManager->all();
	}

	/**
	 * Returns count of online proxied players.
	 */
	public function getOnlinePlayerCount() : int
	{
		return count($this->getOnlinePlayers());
	}

	/**
	 * Returns the latest supported version of Minecraft.
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

	public function getPluginManager() : PluginManager
	{
		return $this->pluginManager;
	}

	public function getScheduler() : TaskScheduler
	{
		return $this->taskScheduler;
	}

	public function getServerManager() : ServerManager {
		return $this->serverManager;
	}

	/**
	 * Returns the command map.
	 */
	public function getCommandMap() : SimpleCommandMap
	{
		return $this->commandMap;
	}

	/**
	 * Returns the permission manager.
	 */
	public function getPermissionManager() : PermissionManager
	{
		return $this->permissionManager;
	}

	/**
	 * Returns the resource pack manager.
	 */
	public function getResourcePackManager() : ResourcePackManager
	{
		return $this->resourcePackManager;
	}

	/**
	 * Returns the tick sleeper handler.
	 */
	public function getTickSleeper() : SleeperHandler
	{
		return $this->sleeper;
	}

	public function handleConsoleInput() : void
	{
		while (($line = $this->consoleQueue->shift()) !== null) {
			$this->getCommandMap()->dispatch($this->consoleSender, $line);
		}
	}

	public function getPlayerByName(string $name) : ?Player
	{
		foreach($this->getOnlinePlayers() as $player) {
			if (strtolower($player->getName()) === strtolower($name)) {
				return $player;
			}
		}

		return null;
	}

	public function broadcastMessage(string $message) : void
	{
		foreach ($this->getOnlinePlayers() as $player) {
			/** @var Player $player */
			$player->sendMessage($message);
		}
	}

	public function shutdown() : void
	{
		$shutdownStart = microtime(true);

		foreach ($this->getOnlinePlayers() as $player) {
			/** @var Player $player */
			$player->disconnect(TranslationFactory::translate('proxy.shutdown'));
		}

		$event = new ServerStopEvent($shutdownStart);
		$event->call();

		foreach ($this->pluginManager->getPlugins() as $plugin) {
			if ($plugin->isEnabled()) {
				try {
					$this->pluginManager->disablePlugin($plugin);
				} catch (\Throwable $e) {
					$this->logger->error('Failed to disable plugin ' . $plugin->getName() . ': ' . $e->getMessage());
					$this->logger->logException($e);
				}
			}
		}

		$this->taskScheduler->cancelAllTasks();
		$this->taskScheduler->shutdown();

		$this->interface->shutdown();

		$duration = round(microtime(true) - $shutdownStart, 3);
		$this->logger->info("Shutdown completed in {$duration}s.");
		$this->logger->shutdown();
		@Utils::kill(Utils::pid());
	}

	public function start() : void
	{
		$nextTick = microtime(true);

		try {
			while (true) {
				$now = microtime(true);

				$this->interface->tick();

				if ($now >= $nextTick) {
					$this->tick();
					$nextTick += 0.05; // Tick interval
				}

				$this->sleeper->sleepUntil($nextTick);
			}
		} catch (\Throwable $e) {
			$this->crash($e);
		}
	}

	public function crash(\Throwable $e) : void
	{
		if ($this->crashed) {
			return;
		}
		$this->crashed = true;

		@fwrite(STDERR, 'The proxy has crashed: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");

		try {
			$this->logger->emergency('The proxy has crashed due to an unhandled error!');
			$this->logger->logException($e);
			$this->logger->emergency('Forcing shutdown to avoid a frozen process.');
			$this->logger->shutdown();
		} catch (\Throwable) {
			//Already wrote to STDERR above; nothing more we can safely do.
		}

		@Utils::kill(Utils::pid());
	}

	private function tick() : void
	{
		$this->getScheduler()->processAll();
		$this->handleConsoleInput();

		foreach (NetworkSessionManager::getInstance()->getSessions() as $session) {
			$player = $session->getPlayer();
			$player?->getDownstream()?->tick();
		}
	}

	private function handleConnect(int $sessionId, string $ip, int $port) : void
	{
		$session = new NetworkSession(
			$this,
			NetworkSessionManager::getInstance(),
			PacketPool::getInstance(),
			new RakLibPacketSender($sessionId, $this->interface),
			$ip,
			$port,
			$sessionId
		);

		$session->getLogger()->info("Session opened");
	}

	private function handlePacket(int $sessionId, string $payload) : void
	{
		if (ord($payload[0]) !== RakLibInterface::MCPE_RAKNET_PACKET_ID_BYTE) return;

		$session = NetworkSessionManager::getInstance()->getSessionById($sessionId);
		$session?->handleEncodedPacket(substr($payload, 1));
	}

	private function handleDisconnect(int $sessionId, int $reason) : void
	{
		$session = NetworkSessionManager::getInstance()->getSessionById($sessionId);
		$reason = match ($reason){
			DisconnectReason::CLIENT_DISCONNECT => "Client disconnected",
			DisconnectReason::PEER_TIMEOUT => "Session timed out",
			DisconnectReason::CLIENT_RECONNECT => "New session established on same IP and port"
		};
		$session?->onDisconnect($reason);
	}

	private function handlePing(int $sessionId, int $ping) : void
	{
		$session = NetworkSessionManager::getInstance()->getSessionById($sessionId);
		$session?->setPing($ping);
	}
}
