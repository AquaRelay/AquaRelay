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
use pocketmine\network\mcpe\protocol\PacketPool;
use raklib\client\ClientSocket;
use raklib\utils\InternetAddress;

class NetworkSession {

    private int $lastUsed;
	private ?string $username = null;
	private ?int $ping = null;
	private bool $connected = true;
	private bool $logged = false;

    public function __construct(
		private ProxyServer $server,
		private NetworkSessionManager $manager,
		private PacketPool $packetPool,
		private PacketSender $sender,
		private InternetAddress $address,
		private ClientSocket $socket
	){
		$this->manager->add($this);
		$this->server->getLogger()->debug("New network session created");
        $this->lastUsed = time();
    }

	public function setUsername(string $username): void{
		$this->username = $username;
	}

	public function getUsername(): ?string{
		return $this->username;
	}

    public function getAddress() : InternetAddress{
        return $this->address;
    }

    public function getSocket() : ClientSocket{
        return $this->socket;
    }

    public function tick() : void{
        $this->lastUsed = time();
    }

    public function expired(int $timeout) : bool{
        return (time() - $this->lastUsed) > $timeout;
    }

	public function getPing() : int
	{
		return $this->ping;
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
}
