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

namespace aquarelay\session;

use raklib\client\ClientSocket;
use raklib\utils\InternetAddress;

class ClientSession {

    private int $lastUsed;

    public function __construct(private readonly InternetAddress $address, private readonly ClientSocket $socket, private ?string $username = null){
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

    public function touch() : void{
        $this->lastUsed = time();
    }

    public function expired(int $timeout) : bool{
        return (time() - $this->lastUsed) > $timeout;
    }
}
