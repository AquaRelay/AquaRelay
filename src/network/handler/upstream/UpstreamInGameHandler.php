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

use aquarelay\event\default\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\ClientCacheStatusPacket;
use pocketmine\network\mcpe\protocol\CommandRequestPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use function array_shift;
use function count;
use function explode;
use function implode;
use function ltrim;
use function strtolower;
use function trim;

class UpstreamInGameHandler extends AbstractUpstreamPacketHandler
{
	private function rewriteClientRuntimeId(int $runtimeId, string $packetName = "unknown") : int
	{
		$player = $this->session->getPlayer();

		if ($player === null) {
			return $runtimeId;
		}

		$rewriteData = $player->getRewriteData();
		$backendRuntimeId = $rewriteData->originalEntityId;
		$clientRuntimeId = $rewriteData->entityId;

		if ($clientRuntimeId !== 0 && $backendRuntimeId !== 0 && $runtimeId === $clientRuntimeId && $clientRuntimeId !== $backendRuntimeId) {
			$this->session->getLogger()->debug(
				"Upstream RuntimeId rewrite in {$packetName}: client={$clientRuntimeId} -> backend={$backendRuntimeId}"
			);

			return $backendRuntimeId;
		}

		return $runtimeId;
	}

	public function shouldForwardUnhandled() : bool
	{
		return true;
	}

	public function handleMovePlayer(MovePlayerPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteClientRuntimeId($packet->actorRuntimeId, "MovePlayerPacket");
		return false;
	}

	public function handleAnimate(AnimatePacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteClientRuntimeId($packet->actorRuntimeId, "AnimatePacket");
		return false;
	}

	public function handleMobEquipment(MobEquipmentPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteClientRuntimeId($packet->actorRuntimeId, "MobEquipmentPacket");
		return false;
	}

	public function handleSetLocalPlayerAsInitialized(SetLocalPlayerAsInitializedPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteClientRuntimeId($packet->actorRuntimeId, "SetLocalPlayerAsInitializedPacket");
		return false;
	}

	public function handleInteract(InteractPacket $packet) : bool
	{
		$packet->targetActorRuntimeId = $this->rewriteClientRuntimeId($packet->targetActorRuntimeId, "InteractPacket");
		return false;
	}

	public function handleText(TextPacket $packet) : bool
	{
		$event = new PlayerChatEvent($this->session->getPlayer(), $packet->message);
		$event->call();

		$packet->message = $event->getMessage();

		if ($event->isCancelled()) {
			return true;
		}
		return false;
	}

	public function handlePlayerAuthInput(PlayerAuthInputPacket $packet) : bool
	{
		return false;
	}

	public function handleInventoryTransaction(InventoryTransactionPacket $packet) : bool
	{
		return false;
	}

	public function handleCommandRequest(CommandRequestPacket $packet) : bool
	{
		$message = trim($packet->command);

		if ($message === "" || $message[0] !== "/") {
			return false;
		}

		$commandLine = ltrim($message, "/");

		if ($commandLine === "") {
			return false;
		}

		if ($this->session->getPlayer() !== null) {
			$player = $this->session->getPlayer();
			$commandMap = $player->getServer()->getCommandMap();

			$parts = explode(" ", $commandLine);
			$commandName = strtolower(array_shift($parts));
			$args = $parts;

			$command = $commandMap->getCommand($commandName);

			if ($command === null) {
				return false;
			}

			$commandMap->dispatch($player, $commandName . (count($args) > 0 ? " " : "") . implode(" ", $args));
		}
		return true;
	}

	public function handleModalFormResponse(ModalFormResponsePacket $packet) : bool
	{
		if ($this->session->getPlayer()->onFormSubmit($packet->formId, $packet->formData)) {
			return true;
		}

		return false;
	}

	public function handlePlayerAction(PlayerActionPacket $packet) : bool
	{
		if ($packet->action === PlayerAction::DIMENSION_CHANGE_ACK) {
			$rewriteData = $this->session->getPlayer()->getRewriteData();

			if ($rewriteData->transferCallback !== null) {
				return $rewriteData->transferCallback->onDimChangeSuccess();
			}
		}
		$packet->actorRuntimeId = $this->rewriteClientRuntimeId($packet->actorRuntimeId, "PlayerActionPacket");
		return false;
	}

	public function handleClientCacheStatus(ClientCacheStatusPacket $packet) : bool
	{
		return true;
	}

	public function handleTransfer(TransferPacket $packet) : bool
	{
		$serverManager = $this->session->getPlayer()?->getServer()->getServerManager();
		$ipAddress = $packet->address;

		$server = $serverManager->get($ipAddress);
		if ($server !== null){
			$this->session->getPlayer()?->transferToBackend($server);
			return false;
		}

		$port = $packet->port;

		foreach ($serverManager->getAll() as $data) {
			if ($data->getAddress() === $ipAddress && $data->getPort() === $port) {
				$this->session->getPlayer()?->transferToBackend($serverManager->get($data->getName()));
				break;
			}
		}

		return false;
	}
}
