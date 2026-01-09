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

namespace aquarelay\network\raklib;

use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;

class RakLibPacketSender {
	public function __construct(
		private int $sessionId,
		private RakLibInterface $interface
	){}

	public function send(string $payload): void {
		$pk = new EncapsulatedPacket();
		$pk->buffer = "\xfe" . $payload;
		$pk->reliability = PacketReliability::RELIABLE_ORDERED;
		$pk->orderChannel = 0;

		$this->interface->getRakLibInterface()->sendEncapsulated($this->sessionId, $pk, true);
	}
}