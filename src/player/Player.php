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

namespace aquarelay\player;

use aquarelay\network\raklib\client\BackendRakClient;
use aquarelay\utils\LoginData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;

class Player
{

	public int $proxyRuntimeId;

	public ?int $backendRuntimeId = null;

	private $upstreamSession;
	private ?BackendRakClient $downstreamConnection = null;

	private LoginData $loginData;

	public function __construct($upstreamSession, LoginData $loginData) {
		$this->upstreamSession = $upstreamSession;
		$this->loginData = $loginData;

		$this->proxyRuntimeId = mt_rand(10000, 50000);
	}

	public function sendPacket(DataPacket $packet): void {
		$this->upstreamSession->sendPacket($packet);
	}

	public function sendToBackend(DataPacket $packet): void {
		$this->downstreamConnection?->sendGamePacket($packet);
	}

	public function getLoginData(): LoginData { return $this->loginData; }
	public function getName(): string { return $this->loginData->username; }

	public function setDownstream(BackendRakClient $client): void {
		$this->downstreamConnection = $client;
	}

	public function getDownstream(): ?BackendRakClient {
		return $this->downstreamConnection;
	}

	public function sendLoginToBackend(): void {
		if (is_null($this->downstreamConnection)) return;

		$pk = LoginPacket::create(
			$this->loginData->protocolVersion,
			json_encode($this->loginData->chainData),
			$this->loginData->clientData
		);

		$this->sendToBackend($pk);
	}

	public function handleBackendPacket(DataPacket $packet): void {
		if ($packet instanceof StartGamePacket) {
			$this->backendRuntimeId = $packet->runtimeEntityId;
			$packet->runtimeEntityId = $this->proxyRuntimeId;
		}

		$this->sendPacket($packet);
	}
}