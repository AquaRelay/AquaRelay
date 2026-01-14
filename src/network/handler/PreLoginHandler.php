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

namespace aquarelay\network\handler;

use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;

class PreLoginHandler extends PacketHandler {

	public function handleRequestNetworkSettings(RequestNetworkSettingsPacket $packet): bool {
		if ($packet->getProtocolVersion() > ProtocolInfo::CURRENT_PROTOCOL){
			$this->session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::LOGIN_FAILED_SERVER));
			return false;
		}

		$pk = NetworkSettingsPacket::create(
			NetworkSettingsPacket::COMPRESS_EVERYTHING,
			CompressionAlgorithm::ZLIB,
			false,
			0,
			0
		);
		$this->session->sendDataPacket($pk, true);
		$this->session->enableCompression();

		$this->session->onNetworkSettingsSuccess();
		return true;
	}
}