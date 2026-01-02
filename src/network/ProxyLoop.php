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
use raklib\protocol\MessageIdentifiers;
use raklib\utils\InternetAddress;

class ProxyLoop {

	/** @var ClientSession[][] */
	private array $clients = [];

	private ?string $cachedPong = null;

	public function __construct(
		private ProxyServer $server,
		private ProxyConfig $config
	){}

	public function run() : void{
		while(true){
			$this->tick();
			usleep(1000);
		}
	}

	private function tick() : void{
		$read = [
			$this->server->listener->getSocket(),
			$this->server->unconnectedBackend->getSocket()
		];

		$map = [];

		foreach($this->clients as $ports){
			foreach($ports as $client){
				$sock = $client->socket()->getSocket();
				$read[] = $sock;
				$map[(int)$sock] = $client;
			}
		}

		if(@socket_select($read, $w, $e, 0, 200000) <= 0){
			return;
		}

		foreach($read as $sock){
			if($sock === $this->server->listener->getSocket()){
				$this->handleClient();
			}elseif($sock === $this->server->unconnectedBackend->getSocket()){
				$this->handleBackendPing();
			}elseif(isset($map[(int)$sock])){
				$this->relayBackendToClient($map[(int)$sock]);
			}
		}

		$this->cleanup();
	}

	private function handleBackendPing() : void{
		$buf = $this->server->unconnectedBackend->readPacket();
		if($buf !== null && ord($buf[0]) === MessageIdentifiers::ID_UNCONNECTED_PONG){
			$this->cachedPong = $buf;
		}
	}

	private function handleClient() : void{
		$buf = $this->server->listener->readPacket($ip, $port);
		if($buf === null){
			return;
		}

		$pid = ord($buf[0]);

		if($pid === MessageIdentifiers::ID_UNCONNECTED_PING){
			$this->server->unconnectedBackend->writePacket($buf);
			if($this->cachedPong !== null){
				$this->server->listener->writePacket($this->cachedPong, $ip, $port);
			}
			return;
		}

		if(isset($this->clients[$ip][$port])){
			$client = $this->clients[$ip][$port];
			$client->touch();
			$client->socket()->writePacket($buf);
			return;
		}

		if($pid === MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_1){
			$backend = new ClientSocket(
				new InternetAddress(
					$this->config->backendAddress,
					$this->config->backendPort,
					4
				)
			);
			$backend->setBlocking(false);

			$this->clients[$ip][$port] = new ClientSession(
				new InternetAddress($ip, $port, 4),
				$backend
			);

			$backend->writePacket($buf);
		}
	}

	private function relayBackendToClient(ClientSession $client) : void{
		$buf = $client->socket()->readPacket();
		if($buf !== null){
			$addr = $client->address();
			$this->server->listener->writePacket(
				$buf,
				$addr->getIp(),
				$addr->getPort()
			);
		}
	}

	private function cleanup() : void{
		foreach($this->clients as $ip => $ports){
			foreach($ports as $port => $client){
				if($client->expired($this->config->sessionTimeout)){
					socket_close($client->socket()->getSocket());
					unset($this->clients[$ip][$port]);
				}
			}
		}
	}
}