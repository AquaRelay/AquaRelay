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

namespace aquarelay\network;

use aquarelay\network\raklib\RakLibPacketSender;
use aquarelay\ProxyServer;
use pocketmine\network\mcpe\protocol\PacketPool;

class ProxyLoop {

	/** @var NetworkSession[] */
	private array $sessions = [];

	public function __construct(
		private ProxyServer $server
	){
		$this->server->interface->setHandlers(
			$this->handleConnect(...),
			$this->handlePacket(...),
			$this->handleDisconnect(...),
			$this->handlePing(...)
		);
	}

	public function run() : void{
		while(true){
			$this->tick();
			usleep(1000);
		}
	}

	private function tick() : void{
		$this->server->interface->process();
	}

	private function handleConnect(int $sessionId, string $ip, int $port): void {
		$this->server->getLogger()->info("Client connected: $ip:$port (ID: $sessionId)");

		$session = new NetworkSession(
			$this->server,
			NetworkSessionManager::getInstance(),
			PacketPool::getInstance(),
			new RakLibPacketSender($sessionId, $this->server->interface)
		);

		$this->sessions[$sessionId] = $session;
	}

	private function handlePacket(int $sessionId, string $payload): void {
		$firstByte = ord($payload[0]);

		if($firstByte !== 0xfe){
			$this->server->getLogger()->warning("Unexpected RakNet packet ID: 0x" . dechex($firstByte));
			return;
		}

		if(!isset($this->sessions[$sessionId])){
			return;
		}

		$this->sessions[$sessionId]->handleEncodedPacket(substr($payload, 1));
	}

	private function handleDisconnect(int $sessionId, string $reason): void {
		$this->server->getLogger()->info("Client disconnected: $reason");
		NetworkSessionManager::getInstance()->remove($this->sessions[$sessionId]);
		unset($this->sessions[$sessionId]);
	}

	private function handlePing(int $sessionId, int $ping) : void
	{
		if (isset($this->sessions[$sessionId])){
			$this->sessions[$sessionId]->setPing($ping);
		}
	}
}
