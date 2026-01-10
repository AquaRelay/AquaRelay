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

namespace aquarelay\network\raklib;

use aquarelay\network\raklib\ipc\PthreadsChannelReader;
use aquarelay\network\raklib\ipc\PthreadsChannelWriter;
use aquarelay\ProxyServer;
use aquarelay\utils\MainLogger;
use pmmp\thread\Thread as NativeThread;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\server\ipc\RakLibToUserThreadMessageReceiver;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use raklib\server\ServerEventListener;

class RakLibInterface implements ServerEventListener {

	const MCPE_RAKNET_PACKET_ID = "\xfe";
	const RAKNET_PROTOCOL_VERSION = 11;

	private RakLibServerThread $thread;
	private RakLibToUserThreadMessageReceiver $eventReceiver;
	private UserToRakLibThreadMessageSender $interface;

	/** @var callable(int, string, int, int): void */
	private $onConnect;
	/** @var callable(int, string): void */
	private $onPacket;
	/** @var callable(int, string): void */
	private $onDisconnect;
	/** @var int */
	private int $rakServerId;

	public function getRaklibInterface() : UserToRakLibThreadMessageSender
	{
		return $this->interface;
	}

	public function __construct(string $mainPath, MainLogger $logger, string $address, int $port, int $maxMtu) {
		$this->rakServerId = mt_rand(0, 1000000);
		$this->thread = new RakLibServerThread($mainPath, $logger, $address, $port, $maxMtu, self::RAKNET_PROTOCOL_VERSION, $this->rakServerId);

		$this->eventReceiver = new RakLibToUserThreadMessageReceiver(
			new PthreadsChannelReader($this->thread->getReadBuffer())
		);
		$this->interface = new UserToRakLibThreadMessageSender(
			new PthreadsChannelWriter($this->thread->getWriteBuffer())
		);
	}

	public function setHandlers(callable $onConnect, callable $onPacket, callable $onDisconnect): void {
		$this->onConnect = $onConnect;
		$this->onPacket = $onPacket;
		$this->onDisconnect = $onDisconnect;
	}

	public function start(): void {
		$this->thread->start(NativeThread::INHERIT_NONE);
	}

	public function process(): void {
		while($this->eventReceiver->handle($this));
	}

	public function sendPacket(int $sessionId, string $payload, bool $immediate = true, ?int $receiptId = null): void {
		if(isset($this->sessions[$sessionId])){
			$pk = new EncapsulatedPacket();
			$pk->buffer = self::MCPE_RAKNET_PACKET_ID . $payload;
			$pk->reliability = PacketReliability::RELIABLE_ORDERED;
			$pk->orderChannel = 0;
			$pk->identifierACK = $receiptId;

			$this->interface->sendEncapsulated($sessionId, $pk, $immediate);
		}
	}

	public function closeSession(int $sessionId): void {
		$this->interface->closeSession($sessionId);
	}

	public function shutdown(): void {
		$this->thread->stop();
		$this->thread->join();
	}

	public function onClientConnect(int $sessionId, string $address, int $port, int $clientID): void {
		($this->onConnect)($sessionId, $address, $port, $clientID);
	}

	public function onPacketReceive(int $sessionId, string $packet): void {
		($this->onPacket)($sessionId, $packet);
	}

	public function onClientDisconnect(int $sessionId, int $reason): void {
		($this->onDisconnect)($sessionId, "Reason: $reason");
	}

	public function close(int $sessionId) : void{
		if(isset($this->sessions[$sessionId])){
			unset($this->sessions[$sessionId]);
			$this->interface->closeSession($sessionId);
		}
	}

	public function setPacketLimit(int $limit) : void{
		$this->interface->setPacketsPerTickLimit($limit);
	}

	public function setPortCheck(bool $check) : void{
		$this->interface->setPortCheck($check);
	}

	public function setName(string $name, string $subMotd) : void
	{
		$config = ProxyServer::getInstance()->getConfig();
		$this->interface->setName(implode(";",
				[
					"MCPE",
					rtrim(addcslashes($name, ";"), '\\'),
					ProtocolInfo::CURRENT_PROTOCOL,
					ProtocolInfo::MINECRAFT_VERSION_NETWORK,
					0, // TODO
					$config->getGameSettings()->getMaxPlayers(),
					$this->rakServerId,
					$subMotd,
					"Survival" // This shouldn't matter since we're a proxy
				]) . ";"
		);
	}

	public function onPingMeasure(int $sessionId, int $pingMS): void {
		if (isset($this->sessions[$sessionId])){
			$this->sessions[$sessionId]->setPing($pingMS);
		}
	}

	public function onPacketAck(int $sessionId, int $identifierACK): void {}
	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff): void {}
	public function onRawPacketReceive(string $address, int $port, string $payload): void {}
}