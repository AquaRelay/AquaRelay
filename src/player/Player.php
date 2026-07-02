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

namespace aquarelay\player;

use aquarelay\command\sender\CommandSender;
use aquarelay\form\Form;
use aquarelay\form\FormValidationException;
use aquarelay\lang\TranslationFactory;
use aquarelay\network\handler\downstream\AbstractDownstreamPacketHandler;
use aquarelay\network\handler\downstream\DownstreamResourcePackHandler;
use aquarelay\network\handler\downstream\SwitchDownstreamResourcePackHandler;
use aquarelay\network\NetworkSession;
use aquarelay\network\protocol\RewriteData;
use aquarelay\network\raklib\client\BackendRakClient;
use aquarelay\permission\PermissionHolder;
use aquarelay\ProxyServer;
use aquarelay\server\BackendServer;
use aquarelay\server\ServerException;
use aquarelay\utils\LoginData;
use aquarelay\utils\Utils;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ClientCacheStatusPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function get_class;

class Player implements CommandSender, PermissionHolder
{
	public ?int $backendRuntimeId = null;
	protected UuidInterface $uuid;
	protected string $xuid = '';
	private ?BackendRakClient $downstreamConnection = null;
	private ?BackendServer $backendServer = null;
	private ?AbstractDownstreamPacketHandler $handler = null;
	protected int $formIdCounter = 0;
	private ?ClientCacheStatusPacket $cacheStatusPacket = null;
	/** @var Form[] */
	protected array $forms = [];

	private RewriteData $rewriteData;

	/** @var int[] */
	private array $entities = [];
	/** @var int[] */
	private array $bossbars = [];
	/** @var string[] */
	private array $playerList = [];
	/** @var string[] */
	private array $scoreboards = [];

	public function __construct(
		private readonly ProxyServer $proxyServer,
		private readonly NetworkSession $upstreamSession,
		private readonly LoginData $loginData,
		private readonly LoginPacket $loginPacket
	)
	{
		$this->xuid = $loginData->xuid;
		$this->uuid = $loginData->uuid;
		$this->rewriteData = new RewriteData();

		$this->setHandler(new DownstreamResourcePackHandler($this, $this->proxyServer->getLogger()));
	}

	public function getLoginPacket() : LoginPacket
	{
		return clone $this->loginPacket;
	}

	public function sendDataPacket(ClientboundPacket $packet) : void
	{
		$this->upstreamSession->sendDataPacket($packet);
	}

	public function sendToBackend(DataPacket $packet) : void
	{
		if ($this->downstreamConnection === null) {
			$this->upstreamSession->getLogger()->debug('Cannot send packet to backend: downstream connection is null');
			return;
		}
		$this->downstreamConnection->sendGamePacket($packet);
	}

	public function getNetworkSession() : NetworkSession
	{
		return $this->upstreamSession;
	}

	public function getLoginData() : LoginData
	{
		return $this->loginData;
	}

	public function getName() : string
	{
		return $this->loginData->username;
	}

	public function setDownstream(BackendRakClient $client) : void
	{
		$this->downstreamConnection = $client;
	}

	public function getDownstream() : ?BackendRakClient
	{
		return $this->downstreamConnection;
	}

	public function getUuid() : UuidInterface
	{
		return $this->uuid;
	}

	public function getXuid() : string
	{
		return $this->xuid;
	}

	public function sendCacheStatus() : void
	{
		if ($this->downstreamConnection === null) {
			return;
		}

		if ($this->cacheStatusPacket !== null) {
			$this->sendToBackend($this->cacheStatusPacket);
		}
	}

	public function setCacheStatusPacket(ClientCacheStatusPacket $packet) : void
	{
		$this->cacheStatusPacket = $packet;
	}

	public function setHandler(AbstractDownstreamPacketHandler $handler) : void
	{
		$this->handler = $handler;
	}

	public function handleBackendPacket(DataPacket $packet) : void
	{
		if ($this->handler !== null) {
			if (!$packet->handle($this->handler)) {
				$this->sendDataPacket($packet);
			}
		} else {
			$this->sendDataPacket($packet);
		}
	}

	public function sendMessage(string $message) : void
	{
		$this->upstreamSession->onMessage($message);
	}

	public function sendPopup(string $message) : void
	{
		$this->upstreamSession->onPopup($message);
	}

	public function sendTip(string $message) : void
	{
		$this->upstreamSession->onTip($message);
	}

	public function sendJukeboxPopup(string $message) : void
	{
		$this->upstreamSession->onJukeboxPopup($message);
	}

	public function sendTitle(string $title, string $subtitle = '', int $fadeIn = 0, int $stay = 0, int $fadeOut = 0) : void
	{
		if ($fadeIn >= 0 && $stay >= 0 && $fadeOut >= 0) {
			$this->upstreamSession->onTitleDuration($fadeIn, $stay, $fadeOut);
		}

		if ($subtitle !== '') {
			$this->upstreamSession->onSubTitle($subtitle);
		}

		$this->upstreamSession->onTitle($title);
	}

	public function sendToastNotification(string $title, string $body) : void
	{
		$this->upstreamSession->onToastNotification($title, $body);
	}

	public function sendActionBar(string $actionBar) : void
	{
		$this->upstreamSession->onActionBar($actionBar);
	}

	/**
	 * Sends a Form to the player, or queue to send it if a form is already open.
	 *
	 * @throws \JsonException
	 */
	public function sendForm(Form $form) : void{
		$id = $this->formIdCounter++;
		$this->getNetworkSession()->onFormSent($id, $form);
		$this->forms[$id] = $form;
	}

	public function onFormSubmit(int $formId, mixed $responseData) : bool{
		if(!isset($this->forms[$formId])){
			$this->getServer()->getLogger()->debug("Got unexpected response for form $formId");
			return false;
		}

		try{
			$this->forms[$formId]->handleResponse($this, $responseData);
		}catch(FormValidationException $e){
			$this->getServer()->getLogger()->critical("Failed to validate form " . get_class($this->forms[$formId]) . ": " . $e->getMessage());
			$this->getServer()->getLogger()->logException($e);
		}finally{
			unset($this->forms[$formId]);
		}

		return true;
	}

	/**
	 * @internal
	 * Returns whether the server is waiting for a response for a form with the given ID.
	 */
	public function hasPendingForm(int $formId) : bool{
		return isset($this->forms[$formId]);
	}

	/**
	 * Closes the current viewing form and forms in queue.
	 */
	public function closeAllForms() : void{
		$this->getNetworkSession()->onCloseAllForms();
	}

	public function disconnect(?string $reason = null) : void
	{
		$this->upstreamSession->disconnect($reason ?? TranslationFactory::translate('player.disconnect.default'));
	}

	public function transferToBackend(BackendServer $server) : void
	{
		if ($this->backendServer?->getName() === $server->getName()) {
			return;
		}

		$this->backendServer = $server;

		if ($this->rewriteData->entityId !== 0) { //
			$this->setHandler(new SwitchDownstreamResourcePackHandler($this, $this->proxyServer->getLogger()));
		} else {
			$this->setHandler(new DownstreamResourcePackHandler($this, $this->proxyServer->getLogger()));
		}

		$this->upstreamSession->connectBackendTo($server->getAddress(), $server->getPort());
		$this->getNetworkSession()->getLogger()->info("Transferring to server: {$server->getName()}");
	}

	public function tryFallbackOrDisconnect() : void
	{
		$serverManager = $this->getServer()->getServerManager();
		$reason = TranslationFactory::translate("proxy.backend.read_error", [Uuid::uuid4()->toString()]);

		try {
			$fallback = $serverManager->select();
		} catch (ServerException) {
			$this->getNetworkSession()->getLogger()->warning("Backend '{$this->getBackendServer()?->getName()}' down, disconnecting");
			$this->disconnect($reason);
			return;
		}

		if (!$fallback->isOnline()) {
			$this->getNetworkSession()->getLogger()->warning("Backend '{$this->getBackendServer()?->getName()}' down, disconnecting");
			$this->disconnect($reason);
			return;
		}

		if ($this->getBackendServer()?->getName() === $fallback->getName()) {
			$this->getNetworkSession()->getLogger()->warning("Backend '{$this->getBackendServer()?->getName()}' down, disconnecting");
			$this->disconnect($reason);
			return;
		}

		$this->transfer($fallback);
	}

	public function transfer(BackendServer $server) : void
	{
		$this->sendDataPacket(TransferPacket::create(
			$server->getAddress(),
			$server->getPort(),
			false
		));
	}

	public function getBackendRuntimeId() : ?int
	{
		return $this->backendRuntimeId;
	}

	public function setBackendRuntimeId(?int $id) : void
	{
		$this->backendRuntimeId = $id;
	}

	public function hasPermission(string $permission) : bool
	{
		return $this->getServer()->getPermissionManager()->hasPermission($this->getName(), $permission);
	}

	public function getServer() : ProxyServer
	{
		return $this->proxyServer;
	}

	/**
	 * Returns the protocol id of player.
	 */
	public function getProtocol() : int
	{
		return $this->upstreamSession->getProtocolId();
	}

	/**
	 * Returns the Minecraft version of player.
	 */
	public function getMinecraftVersion() : string
	{
		return Utils::protocolIdToVersion($this->getProtocol()) ?? "unknown";
	}

	/**
	 * Returns the backend server information.
	 */
	public function getBackendServer() : ?BackendServer
	{
		return $this->backendServer;
	}

	/**
	 * Returns the latency of player.
	 */
	public function getPing() : int
	{
		return $this->getNetworkSession()->getPing();
	}

	public function getRewriteData() : RewriteData
	{
		return $this->rewriteData;
	}

	public function addEntity(int $id) : void
	{
		$this->entities[$id] = $id;
	}

	public function removeEntity(int $id) : void
	{
		unset($this->entities[$id]);
	}

	public function clearEntities() : void
	{
		$this->entities = [];
	}

	public function getEntities() : array
	{
		return $this->entities;
	}

	public function addBossbar(int $id) : void
	{
		$this->bossbars[$id] = $id;
	}

	public function removeBossbar(int $id) : void
	{
		unset($this->bossbars[$id]);
	}

	public function clearBossbars() : void
	{
		$this->bossbars = [];
	}

	public function getBossbars() : array
	{
		return $this->bossbars;
	}

	public function addPlayerToList(string $uuid) : void
	{
		$this->playerList[$uuid] = $uuid;
	}

	public function removePlayerFromList(string $uuid) : void
	{
		unset($this->playerList[$uuid]);
	}

	public function clearPlayerList() : void
	{
		$this->playerList = [];
	}

	public function getPlayerList() : array
	{
		return $this->playerList;
	}

	public function addObjective(string $id) : void
	{
		$this->scoreboards[$id] = $id;
	}

	public function removeObjective(string $id) : void
	{
		unset($this->scoreboards[$id]);
	}

	public function clearScoreboards() : void
	{
		$this->scoreboards = [];
	}

	public function getScoreboards() : array
	{
		return $this->scoreboards;
	}
}
