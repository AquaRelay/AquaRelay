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

use aquarelay\network\protocol\PlayerRewriteUtils;
use aquarelay\network\protocol\TransferCallback;
use pocketmine\network\mcpe\protocol\DisconnectPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;

class SwitchDownstreamHandler extends AbstractDownstreamPacketHandler
{
	public function handlePlayStatus(PlayStatusPacket $packet) : bool
	{
		if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
			$rewriteData = $this->getPlayer()->getRewriteData();

			if ($rewriteData->transferCallback !== null && $rewriteData->transferCallback->getPhase() === TransferCallback::PHASE_2) {
				$rewriteData->transferCallback->onDimChangeSuccess();
			}
			return true;
		}
		return true;
	}

	public function handleLevelChunk(LevelChunkPacket $packet) : bool
	{
		$rewriteData = $this->getPlayer()->getRewriteData();

		if ($rewriteData->transferCallback !== null) {
			$rewriteData->transferCallback->bufferPacket($packet);
			return true;
		}
		return false;
	}

	public function handleNetworkChunkPublisherUpdate(NetworkChunkPublisherUpdatePacket $packet) : bool
	{
		$rewriteData = $this->getPlayer()->getRewriteData();

		if ($rewriteData->transferCallback !== null) {
			$rewriteData->transferCallback->bufferPacket($packet);
			return true;
		}
		return false;
	}

	public function handleStartGame(StartGamePacket $packet) : bool
	{
		$player = $this->getPlayer();
		$rewriteData = $player->getRewriteData();
		$session = $player->getNetworkSession();

		$rewriteData->originalEntityId = $packet->actorRuntimeId;
		$player->setBackendRuntimeId($packet->actorRuntimeId);

		$rewriteData->gameRules = $packet->levelSettings->gameRules;
		$rewriteData->spawnPosition = $packet->playerPosition;
		$rewriteData->pitch = $packet->pitch;
		$rewriteData->yaw = $packet->yaw;

		if (!$session->isConnected()) {
			$player->getDownstream()?->disconnect();
			$player->disconnect('Transfer disconnected');
			return true;
		}

		if ($rewriteData->transferCallback !== null && $rewriteData->transferCallback->getPhase() !== TransferCallback::PHASE_RESET) {
			$session->getLogger()->warning('Aborted server transfer because player is already being transferred!');
			return true;
		}

		$oldDownstream = $player->getDownstream();
		$oldServer = $player->getBackendServer();

		if ($oldDownstream !== null && $oldDownstream !== $player->getDownstream()) {
			$oldDownstream->disconnect();
		}

		foreach ($player->getBossbars() as $bossbarId) {
			PlayerRewriteUtils::injectRemoveBossbar($session, $bossbarId);
		}
		$player->clearBossbars();

		PlayerRewriteUtils::injectRemoveAllPlayers($session, $player->getPlayerList());
		$player->clearPlayerList();

		foreach ($player->getEntities() as $entityId) {
			PlayerRewriteUtils::injectRemoveEntity($session, $entityId);
		}
		$player->clearEntities();

		foreach ($player->getScoreboards() as $objectiveId) {
			PlayerRewriteUtils::injectRemoveObjective($session, $objectiveId);
		}
		$player->clearScoreboards();

		PlayerRewriteUtils::injectRemoveAllEffects($session, $rewriteData->entityId);

		PlayerRewriteUtils::injectClearWeather($session);

		PlayerRewriteUtils::injectGameMode($session, $packet->playerGamemode);
		PlayerRewriteUtils::injectSetDifficulty($session, $packet->levelSettings->difficulty);
		PlayerRewriteUtils::injectGameRules($session, $packet->levelSettings->gameRules);

		$targetDim = $packet->levelSettings->spawnSettings->getDimension();
		$oldDimension = $rewriteData->dimension;

		$needsDimensionHop = $oldDimension === $targetDim;

		$newDimension = $needsDimensionHop
			? PlayerRewriteUtils::determineDimensionId($oldDimension, $targetDim)
			: $targetDim;

		$transferCallback = new TransferCallback($player, $oldServer, $targetDim);
		$rewriteData->transferCallback = $transferCallback;

		$chunkRadiusPacket = new RequestChunkRadiusPacket();
		$chunkRadiusPacket->radius = 8;
		$chunkRadiusPacket->maxRadius = 8;
		$player->getDownstream()?->sendGamePacket($chunkRadiusPacket);

		$useFastTransfer = $player->getServer()->getConfig()->getMiscSettings()->getFastTransfer();

		$startTransferWatchdog = function () use ($session, $player, $transferCallback, $useFastTransfer) : void {
			$player->getServer()->getScheduler()->scheduleDelayed(function () use ($session, $player, $transferCallback, $useFastTransfer) : void {
				if (!$session->isConnected()) {
					return;
				}
				$rewriteData = $player->getRewriteData();

				if ($rewriteData->transferCallback === $transferCallback && $transferCallback->getPhase() === TransferCallback::PHASE_1) {
					$transferCallback->onDimChangeSuccess();
				}
			}, $useFastTransfer ? 20 : 60);

			$player->getServer()->getScheduler()->scheduleDelayed(function () use ($session, $player, $transferCallback, $useFastTransfer) : void {
				if (!$session->isConnected()) {
					return;
				}
				$rewriteData = $player->getRewriteData();

				if ($rewriteData->transferCallback === $transferCallback && $transferCallback->getPhase() === TransferCallback::PHASE_2) {
					$transferCallback->onDimChangeSuccess();
				}
			}, $useFastTransfer ? 45 : 100);
		};

		if ($useFastTransfer) {
			$fakePosition = $packet->playerPosition->add(2000, 0, 2000);

			PlayerRewriteUtils::injectPosition(
				$session,
				$fakePosition,
				$packet->pitch,
				$packet->yaw,
				$rewriteData->entityId
			);

			$rewriteData->dimension = $newDimension;

			PlayerRewriteUtils::injectDimensionChange(
				$session,
				$newDimension,
				$fakePosition,
				$rewriteData->entityId,
				true
			);

			$startTransferWatchdog();
		} else {
			$rewriteData->dimension = $targetDim;

			PlayerRewriteUtils::injectPosition(
				$session,
				$packet->playerPosition,
				$packet->pitch,
				$packet->yaw,
				$rewriteData->entityId
			);

			$transferCallback->onDimChangeSuccess();
			$transferCallback->onDimChangeSuccess();
		}

		return true;
	}

	public function handleDisconnect(DisconnectPacket $packet) : bool
	{
		$rewriteData = $this->getPlayer()->getRewriteData();
		if ($rewriteData->transferCallback !== null) {
			$rewriteData->transferCallback->onTransferFailed();
			return true;
		}

		$this->getPlayer()->tryFallbackOrDisconnect();
		return true;
	}
}
