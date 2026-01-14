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

namespace aquarelay\network\raklib\client;

use aquarelay\network\raklib\RakLibInterface;
use pocketmine\network\mcpe\protocol\DataPacket;
use raklib\client\ClientSocket;
use pmmp\encoding\ByteBufferWriter;
use raklib\protocol\ConnectionRequest;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\Packet;
use raklib\protocol\PacketSerializer;
use raklib\protocol\UnconnectedPing;
use raklib\utils\InternetAddress;

class BackendRakClient {

	private int $mtu = 1492;
	private ClientSocket $socket;
	private bool $connected = false;
	private int $clientId;

	public function __construct(
		private InternetAddress $address
	){
		$this->clientId = rand(1, PHP_INT_MAX);
		$this->socket = new ClientSocket($address);
		$this->socket->setBlocking(false);
	}

	public function connect() : void{
		$ping = new UnconnectedPing();
		$ping->sendPingTime = (int)(microtime(true) * 1000);
		$ping->clientId = $this->clientId;
		$this->send($ping);

		$open1 = new OpenConnectionRequest1();
		$open1->protocol = RakLibInterface::RAKNET_PROTOCOL_VERSION;
		$open1->mtuSize = $this->mtu;
		$this->send($open1);

		$open2 = new OpenConnectionRequest2();
		$open2->clientID = $this->clientId;
		$open2->mtuSize = $this->mtu;
		$open2->serverAddress = $this->address;
		$this->send($open2);

		$conn = new ConnectionRequest();
		$conn->clientID = $this->clientId;
		$conn->sendPingTime = $ping->sendPingTime;
		$conn->useSecurity = false;
		$this->send($conn);
	}
	public function sendRaw(string $buffer) : void{
		$this->socket->writePacket($buffer);
	}

	public function tick(callable $onPacket) : void{
		while(($buffer = $this->socket->readPacket()) !== null){
			if(!$this->connected){
				$this->connected = true;
			}
			$onPacket($buffer);
		}
	}

	private function send(Packet $packet) : void{
		$serializer = new PacketSerializer();
		$packet->encode($serializer);
		$this->socket->writePacket($serializer->getBuffer());
	}

	public function close() : void{
		$this->socket->close();
	}

	public function sendGamePacket(DataPacket $packet) : void {
		$writer = new ByteBufferWriter();
		$packet->encode($writer);
		$payload = RakLibInterface::MCPE_RAKNET_PACKET_ID . $writer->getData();
		$this->sendRaw($payload);
	}
}
