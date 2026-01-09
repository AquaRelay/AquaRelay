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
use aquarelay\utils\JWTUtils;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use raklib\client\ClientSocket;
use raklib\generic\SocketException;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\DataDecodeException;
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
			$socket = $session->getSocket();

			try {
				$packet = $socket->readPacket();
				if ($packet !== null) {
					// Backend -> Proxy -> Client (RakLib)
					$this->server->interface->sendPacket($sessionId, $packet);
					$session->touch();
				}
			} catch (SocketException $e) {
				$this->server->getLogger()->warning("Backend connection lost for session $sessionId: " . $e->getMessage());
				$this->closeSessionInternal($sessionId);
				continue;
			}

			if($session->expired($this->config->getNetworkSettings()->getSessionTimeout())){
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

	private function handlePacket(int $sessionId, string $payload): void{
		if(!isset($this->sessions[$sessionId])){
			return;
		}

		$session = $this->sessions[$sessionId];
		$session->touch();

		if($payload === "" || $payload[0] !== "\xfe"){
			return;
		}

		$payload = substr($payload, 1);

		try{
			$stream = new ByteBufferReader($payload);

			foreach(PacketBatch::decodeRaw($stream) as $buffer){
				$packet = PacketPool::getInstance()->getPacket($buffer);
				if($packet === null){
					throw new \RuntimeException("Unknown packet");
				}

				$reader = new ByteBufferReader($buffer);
				$packet->decode($reader);

				$this->server->getLogger()->debug("Packet: {$packet->getName()} with PID: {$packet->pid()}");

				if($packet instanceof LoginPacket){
					$username = JWTUtils::getInstance()->getUsernameFromJwt($packet->clientDataJwt);

					$session->setUsername($username);

					$this->server->getLogger()->info("Proxy login: $username ({$session->getAddress()})");
				}

				$session->getSocket()->writePacket("\xfe" . $payload);
			}

		}catch(PacketDecodeException|DataDecodeException|\Throwable){
			$this->closeSessionInternal($sessionId);
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