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

namespace aquarelay\config;

final class NetworkSettings {

	public function __construct(
		private string $bindAddress,
		private int $bindPort,
		private string $backendAddress,
		private int $backendPort,
		private int $sessionTimeout,
		private int $maxMtu
	){}

	public function getBindAddress() : string{
		return $this->bindAddress;
	}

	public function setBindAddress(string $bindAddress) : void{
		$this->bindAddress = $bindAddress;
	}

	public function getBindPort() : int{
		return $this->bindPort;
	}

	public function setBindPort(int $bindPort) : void{
		$this->bindPort = $bindPort;
	}

	public function getBackendAddress() : string{
		return $this->backendAddress;
	}

	public function setBackendAddress(string $backendAddress) : void{
		$this->backendAddress = $backendAddress;
	}

	public function getBackendPort() : int{
		return $this->backendPort;
	}

	public function setBackendPort(int $backendPort) : void{
		$this->backendPort = $backendPort;
	}

	public function getSessionTimeout() : int{
		return $this->sessionTimeout;
	}

	public function setSessionTimeout(int $sessionTimeout) : void{
		$this->sessionTimeout = $sessionTimeout;
	}

	public function getMaxMtu() : int{
		return $this->maxMtu;
	}

	public function setMaxMtu(int $maxMtu) : void{
		$this->maxMtu = $maxMtu;
	}
}
