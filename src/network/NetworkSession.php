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

use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\handler\LoginHandler;
use aquarelay\network\handler\PacketHandler;
use aquarelay\network\handler\PreLoginHandler;
use aquarelay\ProxyServer;
use pmmp\encoding\ByteBuffer;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pmmp\encoding\ByteBufferReader;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use raklib\generic\DisconnectReason;

class NetworkSession {
	/** @var string[] */
	private array $sendBuffer = [];
	private bool $enableCompression = false;
	private int $lastUsed;
	private ?string $username = null;
	private ?int $ping = null;
	private bool $connected = true;
	private bool $logged = false;
	private ?PacketHandler $handler;

	public function __construct(
		private ProxyServer $server,
		private NetworkSessionManager $manager,
		private PacketPool $packetPool,
		private PacketSender $sender,
		private string $ip,
		private int $port
	){
		$this->manager->add($this);
		$this->lastUsed = time();
		$this->setHandler(new PreLoginHandler($this, $this->server->getLogger()));
	}

	public function handleEncodedPacket(string $payload) : void {
		$compressionType = ord($payload[0]);
		$data = substr($payload, 1);

		if($compressionType === CompressionAlgorithm::ZLIB){
			try {
				$data = ZlibCompressor::getInstance()->decompress($data);
			} catch (\Exception $e) {
				$this->server->getLogger()->error("Decompressing error: " . $e->getMessage());
				return;
			}
		}

		if (ord($data[0]) === 0xc1) {
			$this->processSinglePacket($data);
		} else {
			try {
				$stream = new ByteBufferReader($data);
				foreach(PacketBatch::decodeRaw($stream) as $buffer){
					$this->processSinglePacket($buffer);
				}
			} catch (\Exception $e) {
				$this->server->getLogger()->error("Batch decode error: " . $e->getMessage());
			}
		}
	}

	private function processSinglePacket(string $buffer) : void {
		$packet = $this->packetPool->getPacket($buffer);
		if($packet !== null){
			$this->debug("Incoming packet: " . $packet->getName());
			$packet->decode(new ByteBufferReader($buffer));

			if($this->handler !== null){
				$packet->handle($this->handler);
			}
		} else {
			$this->debug("Unknown packet ID: 0x" . dechex(ord($buffer[0])));
		}
	}

	public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false) : void {
		$this->debug("Sending packet: " . $packet->getName());
		$writer = new ByteBufferWriter();

		$packet->encode($writer);
		$payload = $writer->getData();

		$this->addToSendBuffer($payload);

		if ($immediate) {
			$this->flushGamePacketQueue();
		}
	}

	public function flushGamePacketQueue() : void {
		if (count($this->sendBuffer) > 0) {
			$stream = new ByteBufferWriter();

			PacketBatch::encodeRaw($stream, $this->sendBuffer);
			$batchData = $stream->getData();
			$this->sendBuffer = [];

			if (!$this->enableCompression) {
				$finalPayload = "\x00" . $batchData;
			} else {
				$compressor = ZlibCompressor::getInstance();
				$finalPayload = "\x01" . $compressor->compress($batchData);
			}

			$this->sendEncoded($finalPayload);
		}
	}

	private function sendEncoded(string $payload) : void {
		$this->sender->sendRawPacket($payload);
	}

	public function enableCompression() : void {
		$this->enableCompression = true;
	}

	public function addToSendBuffer(string $buffer) : void {
		$this->sendBuffer[] = $buffer;
	}

	public function disconnect(string $reason) : void{
		$this->sendDataPacket(DisconnectPacket::create(DisconnectReason::CLIENT_DISCONNECT, $reason, ""));
	}

	public function onNetworkSettingsSuccess(): void {
		$this->setHandler(new LoginHandler($this, $this->server->getLogger()));
	}

	public function onClientLoginSuccess(LoginPacket $loginPacket): void {
		$this->debug("Transitioning to backend proxying...");
		// TODO: Backend transitioning
	}

	public function getPing() : int
	{
		return $this->ping;
	}

	public function tick(): void {
		$this->lastUsed = time();
	}
	public function getUsername() : string {
		return $this->username;
	}
	public function setUsername(string $name): void {
		$this->username = $name;
	}

	public function setPing(int $ping): void
	{
		$this->ping = $ping;
	}

	public function isConnected() : bool
	{
		return $this->connected;
	}

	public function isLogged() : bool
	{
		return $this->logged;
	}

	public function getHandler() : ?PacketHandler {
		return $this->handler;
	}

	public function setHandler(?PacketHandler $handler) : void {
		if($this->connected){
			$this->handler = $handler;
			$this->handler?->setUp();
		}
	}

	private function debug(string $message) : void
	{
		$this->server->getLogger()->debug("[NetworkSession - $this->ip:$this->port]: $message");
	}
}
