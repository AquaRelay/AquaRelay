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

use aquarelay\utils\InstanceTrait;

class NetworkSessionManager
{

	use InstanceTrait {
		setInstance as private;
	}

	private array $sessions = [];
	private array $pendingLoginSessions = [];

	public function add(NetworkSession $session) : void{
		$id = spl_object_id($session);
		$this->sessions[$id] = $session;
		$this->pendingLoginSessions[$id] = $session;
	}

	public function markLoginReceived(NetworkSession $session) : void{
		unset($this->pendingLoginSessions[spl_object_id($session)]);
	}

	public function remove(NetworkSession $session) : void{
		$id = spl_object_id($session);
		unset($this->sessions[$id]);
		unset($this->pendingLoginSessions[$id]);
	}

	public function getSessions() : array
	{
		return $this->sessions;
	}

	public function getPendingLoginSessions() : array
	{
		return $this->pendingLoginSessions;
	}

	public function tick() : void{
		foreach($this->sessions as $id => $session){
			$session->tick();
			if(!$session->isConnected()){
				unset($this->sessions[$id]);
			}
		}
	}
}