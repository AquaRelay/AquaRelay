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

use pocketmine\network\mcpe\protocol\ClientCacheStatusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\types\Experiments;

class ResourcePackHandler extends PacketHandler {

	public function handleClientCacheStatus(ClientCacheStatusPacket $packet): bool {
		$this->session->debug("Client cache status received: " . ($packet->isEnabled() ? "Supported" : "Not Supported"));
		return true;
	}

	public function handleResourcePackClientResponse(ResourcePackClientResponsePacket $packet): bool {
		switch ($packet->status) {
			case ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS:
				$this->session->debug("Client has all packs. Sending stack...");

				$pk = ResourcePackStackPacket::create(
					resourcePackStack: [],
					mustAccept: false,
					baseGameVersion: "*",
					experiments: new Experiments([], false),
					useVanillaEditorPacks: false
				);

				$this->session->sendDataPacket($pk, true);
				return true;

			case ResourcePackClientResponsePacket::STATUS_COMPLETED:
				$this->session->debug("Resource packs sequence completed. Connecting to backend...");
				$this->session->connectToBackend();
				return true;

			case ResourcePackClientResponsePacket::STATUS_REFUSED:
				$this->session->disconnect("You must accept resource packs.");
				return false;
		}
		return true;
	}
}