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
use aquarelay\utils\MainLogger;
use pmmp\thread\Thread as NativeThread;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\server\ipc\RakLibToUserThreadMessageReceiver;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use raklib\server\ServerEventListener;

class RakLibInterface implements ServerEventListener {

	private RakLibServerThread $thread;
	private RakLibToUserThreadMessageReceiver $eventReceiver;
	private UserToRakLibThreadMessageSender $interface;

	/** @var callable(int, string, int, int): void */
	private $onConnect;
	/** @var callable(int, string): void */
	private $onPacket;
	/** @var callable(int, string): void */
	private $onDisconnect;

	public function getRaklibInterface() : UserToRakLibThreadMessageSender
	{
		return $this->interface;
	}

	public function __construct(MainLogger $logger, string $address, int $port) {
		$this->thread = new RakLibServerThread($logger, $address, $port, 1492, 11);

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

	public function sendPacket(int $sessionId, string $payload): void {
		$pk = new EncapsulatedPacket();
		$pk->buffer = "\xfe" . $payload;
		$pk->reliability = PacketReliability::RELIABLE_ORDERED;
		$pk->orderChannel = 0;

		$this->interface->sendEncapsulated($sessionId, $pk, true);
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
		// RakLib FE header (GamePacket ID) usually handles itself, but we can check the data
		$payload = $packet;
		if($packet !== "" && $packet[0] === "\xfe"){
			$payload = substr($packet, 1);
		}
		($this->onPacket)($sessionId, $payload);
	}

	public function onClientDisconnect(int $sessionId, int $reason): void {
		($this->onDisconnect)($sessionId, "Reason: $reason");
	}

	public function onPacketAck(int $sessionId, int $identifierACK): void {}
	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff): void {}
	public function onPingMeasure(int $sessionId, int $pingMS): void {}
	public function onRawPacketReceive(string $address, int $port, string $payload): void {}
}