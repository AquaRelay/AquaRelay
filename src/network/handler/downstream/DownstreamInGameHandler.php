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

namespace aquarelay\network\handler\downstream;

use aquarelay\event\default\player\PlayerJoinEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketDisassembler;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use function array_map;
use function in_array;
use function strtolower;
use function ucfirst;

class DownstreamInGameHandler extends AbstractDownstreamPacketHandler
{

	private function sendPostTransferSpawnInitialized() : void
	{
		$player = $this->getPlayer();
		$rewriteData = $player->getRewriteData();
		$downstream = $player->getDownstream();

		if ($downstream === null || !$downstream->isConnected()) {
			return;
		}

		if ($rewriteData->postTransferSpawnInitialized) {
			return;
		}

		$rewriteData->postTransferSpawnInitialized = true;

		$player->getNetworkSession()->getLogger()->debug(
			"Sending post-transfer SetLocalPlayerAsInitialized to backend with runtimeId={$rewriteData->originalEntityId}"
		);

		$downstream->sendGamePacket(SetLocalPlayerAsInitializedPacket::create($rewriteData->originalEntityId));

		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;

		$downstream->sendGamePacket($chunkRadiusPacket);
	}

	private function rewriteLocalRuntimeId(int $runtimeId, string $packetName = "unknown") : int
	{
		$rewriteData = $this->getPlayer()->getRewriteData();

		if ($runtimeId === $rewriteData->originalEntityId && $rewriteData->entityId !== 0) {
			$this->getPlayer()->getNetworkSession()->getLogger()->debug(
				"RuntimeId rewrite in {$packetName}: backend={$runtimeId} -> client={$rewriteData->entityId}"
			);

			return $rewriteData->entityId;
		}

		if ($runtimeId === $rewriteData->entityId) {
			$this->getPlayer()->getNetworkSession()->getLogger()->debug(
				"RuntimeId already client id in {$packetName}: {$runtimeId}"
			);
		}

		return $runtimeId;
	}

	public function handleMovePlayer(MovePlayerPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteLocalRuntimeId($packet->actorRuntimeId, "MovePlayerPacket");
		return false;
	}

	public function handleSetActorData(SetActorDataPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteLocalRuntimeId($packet->actorRuntimeId, "SetActorDataPacket");
		return false;
	}

	public function handleSetActorMotion(SetActorMotionPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteLocalRuntimeId($packet->actorRuntimeId, "SetActorMotionPacket");
		return false;
	}

	public function handleUpdateAttributes(UpdateAttributesPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteLocalRuntimeId($packet->actorRuntimeId, "UpdateAttributesPacket");
		return false;
	}

	public function handleMobEffect(MobEffectPacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteLocalRuntimeId($packet->actorRuntimeId, "MobEffectPacket");
		return false;
	}

	public function handleAnimate(AnimatePacket $packet) : bool
	{
		$packet->actorRuntimeId = $this->rewriteLocalRuntimeId($packet->actorRuntimeId, "AnimatePacket");
		return false;
	}

	public function handleLevelChunk(LevelChunkPacket $packet) : bool
	{
		static $chunkDebugCount = 0;

		if ($chunkDebugCount < 20) {
			++$chunkDebugCount;

			$this->getPlayer()->getNetworkSession()->getLogger()->debug(
				"LevelChunk from backend after transfer: " .
				"x={$packet->getChunkPosition()->getX()}, z={$packet->getChunkPosition()->getZ()}, count={$chunkDebugCount}"
			);
		}

		return false;
	}

	public function handleNetworkChunkPublisherUpdate(NetworkChunkPublisherUpdatePacket $packet) : bool
	{
		return false;
	}

	public function handleAvailableCommands(AvailableCommandsPacket $packet) : bool{
		$player = $this->getPlayer();
		$server = $player->getServer();

		if(!$server->getConfig()->getMiscSettings()->getCommandInjection()){
			return false;
		}

		$commandMap = $server->getCommandMap();

		$disassembled = AvailableCommandsPacketDisassembler::disassemble($packet);
		$data = $disassembled->commandData;
		$softEnums = $disassembled->unusedSoftEnums;
		$hardEnums = $disassembled->unusedHardEnums;

		foreach ($commandMap->getCommands() as $command) {
				$exists = false;

				foreach($data as $commandData){
						if(strtolower($commandData->name) === strtolower($command->getName())){
								$exists = true;
								break;
						}
				}

				if($exists || $command->getName() === "help" || !$command->testPermission($player)){
						continue;
				}

				$name = strtolower($command->getName());
				$enum = null;
				$aliases = array_map('strtolower', $command->getAliases());

				if (!empty($aliases)) {
						if (!in_array($name, $aliases, true)) {
								$aliases[] = $name;
						}

						$enum = new CommandHardEnum(
								ucfirst($name) . "Aliases",
								$aliases
						);
				}

				$commandData = new CommandData(
						$name,
						$command->getBuilder()->getDescription(),
						0,
						CommandPermissions::NORMAL,
						$enum,
						[
							new CommandOverload(false, [
								CommandParameter::standard(
										"args",
										AvailableCommandsPacket::ARG_TYPE_RAWTEXT,
										0,
										true
								)
							]),
						],
						[]
				);

				$data[] = $commandData;
		}

		$this->getPlayer()->sendDataPacket(
				AvailableCommandsPacketAssembler::assemble($data, $hardEnums, $softEnums)
		);

		return true;
	}

	public function handleStartGame(StartGamePacket $packet) : bool
	{
		$player = $this->getPlayer();
		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;

		if ($player->getRewriteData()->entityId !== 0) {
			$player->getDownstream()->sendGamePacket($chunkRadiusPacket);
		}
		$player->setBackendRuntimeId($packet->actorRuntimeId);

		$rewriteData = $player->getRewriteData();
		$rewriteData->entityId = $packet->actorRuntimeId;
		$rewriteData->originalEntityId = $packet->actorRuntimeId;
		$rewriteData->dimension = $packet->levelSettings->spawnSettings->getDimension();

		return false;
	}

	public function handleAddPlayer(AddPlayerPacket $packet) : bool
	{
		$this->getPlayer()->addEntity($packet->actorRuntimeId);
		return false;
	}

	public function handleAddActor(AddActorPacket $packet) : bool
	{
		$this->getPlayer()->addEntity($packet->actorRuntimeId);
		return false;
	}

	public function handleRemoveActor(RemoveActorPacket $packet) : bool
	{
		$this->getPlayer()->removeEntity($packet->actorUniqueId);
		return false;
	}

	public function handleBossEvent(BossEventPacket $packet) : bool
	{
		if ($packet->eventType === BossEventPacket::TYPE_SHOW) {
			$this->getPlayer()->addBossbar($packet->bossActorUniqueId);
		} elseif ($packet->eventType === BossEventPacket::TYPE_HIDE) {
			$this->getPlayer()->removeBossbar($packet->bossActorUniqueId);
		}
		return false;
	}

	public function handlePlayerList(PlayerListPacket $packet) : bool
	{
		$player = $this->getPlayer();
		foreach ($packet->entries as $entry) {
			if ($packet->type === PlayerListPacket::TYPE_ADD) {
				$player->addPlayerToList($entry->uuid->toString());
			} else {
				$player->removePlayerFromList($entry->uuid->toString());
			}
		}
		return false;
	}

	public function handleSetDisplayObjective(SetDisplayObjectivePacket $packet) : bool
	{
		$this->getPlayer()->addObjective($packet->objectiveName);
		return false;
	}

	public function handleRemoveObjective(RemoveObjectivePacket $packet) : bool
	{
		$this->getPlayer()->removeObjective($packet->objectiveName);
		return false;
	}

	public function handlePlayStatus(PlayStatusPacket $packet) : bool
	{
		if ($packet->status === PlayStatusPacket::LOGIN_SUCCESS) {
			$this->getPlayer()->getNetworkSession()->getLogger()->debug('Suppressing duplicate LOGIN_SUCCESS from backend');
			return true;
		}

		if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
			$player = $this->getPlayer();
			$rewriteData = $player->getRewriteData();

			if ($rewriteData->entityId !== 0 && $rewriteData->originalEntityId !== $rewriteData->entityId) {
				$this->sendPostTransferSpawnInitialized();

				return false;
			}

			if ($player->backendRuntimeId === null) {
				$player->getNetworkSession()->getLogger()->debug('Cannot send spawn notification: backendRuntimeId is null.');
			} else {
				$player->getNetworkSession()->getLogger()->debug('Sending spawn notification, waiting for spawn response');
				$event = new PlayerJoinEvent($player);
				$event->call();
			}
		}

		return false;
	}

	public function handleTransfer(TransferPacket $packet) : bool
	{
		$serverManager = $this->getPlayer()->getServer()->getServerManager();
		$ipAddress = $packet->address;

		$server = $serverManager->get($ipAddress);
		if ($server !== null){
			$this->getPlayer()->transferToBackend($server);
			return true;
		}

		$port = $packet->port;

		foreach ($serverManager->getAll() as $data) {
			if ($data->getAddress() === $ipAddress && $data->getPort() === $port) {
				$this->getPlayer()->transferToBackend($serverManager->get($data->getName()));
				break;
			}
		}

		return true;
	}

	public function handleDisconnect(DisconnectPacket $packet) : bool
	{
		$this->getPlayer()->tryFallbackOrDisconnect();
		return true;
	}
}
