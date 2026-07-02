<?php

/*
 *
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *               |_|                              |___/
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

namespace aquarelay\network\handler\upstream;

use aquarelay\lang\TranslationFactory;
use aquarelay\utils\Colors;
use pocketmine\network\mcpe\protocol\ClientCacheStatusPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\Experiments;
use function array_shift;

class UpstreamResourcePackHandler extends AbstractUpstreamPacketHandler
{
	/** @var string[] */
	private array $pendingPackIds = [];
	private ?string $sendingPackId = null;
	private int $sendingPackChunkCount = 0;

	public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet) : bool
	{
		$this->session->getPlayer()?->sendToBackend($packet);

		return true;
	}

	public function handleClientCacheStatus(ClientCacheStatusPacket $packet) : bool
	{
		$this->session->getLogger()->debug('Client cache status received: ' . ($packet->isEnabled() ? 'Supported' : 'Not Supported'));

		return true;
	}

	public function handleResourcePackClientResponse(ResourcePackClientResponsePacket $packet) : bool
	{
		$packManager = $this->session->getServer()->getResourcePackManager();
		$packsEnabled = $packManager->isEnabled();

		switch ($packet->status) {
			case ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS:
				$this->session->getLogger()->debug('Client has all packs. Sending stack...');
				if ($packsEnabled) {
					$this->session->sendDataPacket($packManager->getStackPacket(), true);
				} else {
					$pk = ResourcePackStackPacket::create([], [], false, '*', new Experiments([], false), false);
					$this->session->sendDataPacket($pk, true);
				}

				return true;

			case ResourcePackClientResponsePacket::STATUS_SEND_PACKS:
				if (!$packsEnabled) {
					return true;
				}

				$this->pendingPackIds = $packet->packIds;
				$this->sendNextPackInfo();
				return true;

			case ResourcePackClientResponsePacket::STATUS_COMPLETED:
				$this->completeLogin();

				return true;

			case ResourcePackClientResponsePacket::STATUS_REFUSED:
				if ($packsEnabled && !$packManager->isForceAccept()) {
					$this->completeLogin();
					return true;
				}

				$this->session->disconnect(TranslationFactory::translate("resource_pack.required"));

				return false;
		}

		return true;
	}

	public function handleResourcePackChunkRequest(ResourcePackChunkRequestPacket $packet) : bool
	{
		$packManager = $this->session->getServer()->getResourcePackManager();
		if (!$packManager->isEnabled()) {
			return true;
		}

		$response = $packManager->createPackChunkPacket($packet->packId, $packet->chunkIndex);
		if ($response === null) {
			$this->session->disconnect(TranslationFactory::translate('resource_pack.unknown'));
			return false;
		}

		$this->session->sendDataPacket($response, true);

		if ($this->sendingPackId === $packet->packId && $this->sendingPackChunkCount > 0) {
			if (($packet->chunkIndex + 1) >= $this->sendingPackChunkCount) {
				$this->sendNextPackInfo();
			}
		}

		return true;
	}

	private function sendNextPackInfo() : void
	{
		$packManager = $this->session->getServer()->getResourcePackManager();
		if (!$packManager->isEnabled()) {
			return;
		}

		$nextId = array_shift($this->pendingPackIds);
		if ($nextId === null) {
			return;
		}

		$infoPacket = $packManager->createPackInfoPacket($nextId);
		if ($infoPacket === null) {
			$this->session->disconnect(TranslationFactory::translate('resource_pack.unknown'));
			return;
		}

		$this->sendingPackId = $infoPacket->packId;
		$this->sendingPackChunkCount = $infoPacket->chunkCount;
		$this->session->sendDataPacket($infoPacket, true);
	}

	private function completeLogin() : void
	{
		$this->session->getLogger()->debug('Resource packs sequence completed.');

		$publisher = NetworkChunkPublisherUpdatePacket::create(
			new BlockPosition(0, 0, 0),
			8 * 16,
			[]
		);
		$this->session->sendDataPacket($publisher, false);

		$chunkPk = LevelChunkPacket::create(
			new ChunkPosition(0, 0),
			0,
			1,
			false,
			null,
			"\x01\x00\x00"
		);
		$this->session->sendDataPacket($chunkPk, false);

		$this->logger->info(Colors::AQUA . $this->session->getPlayer()?->getName() . Colors::WHITE . "[" . $this->session->getAddress() . ":" . $this->session->getPort() . "] logged in with v" . $this->session->getPlayer()?->getMinecraftVersion() . " (" . $this->session->getProtocolId() . ")");

		$this->session->flushGamePacketQueue();

		$backend = $this->session->getServer()->getServerManager()->select();

		$this->session->getPlayer()->transferToBackend($backend);
		$this->session->setHandler(new UpstreamInGameHandler($this->session, $this->logger));
	}
}
