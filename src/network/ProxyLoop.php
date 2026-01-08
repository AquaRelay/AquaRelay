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

use aquarelay\ProxyServer;
use aquarelay\config\ProxyConfig;
use aquarelay\session\ClientSession;
use raklib\client\ClientSocket;
use raklib\generic\SocketException;
use raklib\utils\InternetAddress;

class ProxyLoop {

	/** @var ClientSession[] */
	private array $sessions = [];

	public function __construct(
		private ProxyServer $server,
		private ProxyConfig $config
	){
		$this->server->interface->setHandlers(
			$this->handleConnect(...),
			$this->handlePacket(...),
			$this->handleDisconnect(...)
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

		foreach($this->sessions as $sessionId => $session){
			$socket = $session->socket();

			try {
				$packet = $socket->readPacket();
				if ($packet !== null) {
					// Backend -> Proxy -> Client (RakLib)
					$this->server->interface->sendPacket($sessionId, $packet);
					$session->touch();
				}
			} catch (SocketException $e) {
				$this->server->getLogger()->warn("Backend connection lost for session $sessionId: " . $e->getMessage());
				$this->closeSessionInternal($sessionId);
				continue;
			}

			if($session->expired($this->config->getGameSettings()->getSessionTimeout())){
				$this->server->getLogger()->info("Session timed out: $sessionId");
				$this->server->interface->closeSession($sessionId);
				unset($this->sessions[$sessionId]);
			}
		}
	}

	private function handleConnect(int $sessionId, string $ip, int $port): void {
		$this->server->getLogger()->info("Client connected: $ip:$port (ID: $sessionId)");

		try {
			$address = $this->config->getNetworkSettings()->getBackendAddress();
			$port = $this->config->getNetworkSettings()->getBackendPort();

			$backendSocket = new ClientSocket(new InternetAddress($address, $port, 4));
			$backendSocket->setBlocking(false);

			$this->sessions[$sessionId] = new ClientSession(
				new InternetAddress($ip, $port, 4),
				$backendSocket
			);
		} catch (SocketException $e) {
			$this->server->getLogger()->error("Could not connect to backend: " . $e->getMessage());
			$this->server->interface->closeSession($sessionId);
		}
	}

	private function handlePacket(int $sessionId, string $buffer): void {
		if(isset($this->sessions[$sessionId])){
			$this->sessions[$sessionId]->touch();

			try {
				// Client (RakLib) -> Proxy -> Backend
				$socket = $this->sessions[$sessionId]->socket();
				$socket->writePacket($buffer);
			} catch (SocketException) {
				$this->closeSessionInternal($sessionId);
			}
		}
	}

	private function handleDisconnect(int $sessionId, string $reason): void {
		if(isset($this->sessions[$sessionId])){
			$this->server->getLogger()->info("Client disconnected: $reason");
			unset($this->sessions[$sessionId]);
		}
	}

	private function closeSessionInternal(int $sessionId) : void {
		if(isset($this->sessions[$sessionId])){
			$this->server->interface->closeSession($sessionId);
			unset($this->sessions[$sessionId]);
		}
	}
}