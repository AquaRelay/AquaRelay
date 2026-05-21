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

namespace aquarelay\network\protocol;

use aquarelay\network\handler\downstream\DownstreamInGameHandler;
use aquarelay\player\Player;
use aquarelay\server\BackendServer;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class TransferCallback
{
	public const PHASE_RESET = 0;
	public const PHASE_1 = 1;
	public const PHASE_2 = 2;

	private int $phase = self::PHASE_1;

	public function __construct(
		private readonly Player        $player,
		private readonly ?BackendServer $sourceServer,
		private readonly int           $targetDimension
	) {}

	public function onDimChangeSuccess() : bool
	{
		return match ($this->phase) {
			self::PHASE_1 => $this->handlePhase1(),
			self::PHASE_2 => $this->handlePhase2(),
			default => false,
		};
	}

	private function handlePhase1() : bool
	{
		$this->phase = self::PHASE_2;
		$rewriteData = $this->player->getRewriteData();
		$session = $this->player->getNetworkSession();

		if ($rewriteData->dimension === $this->targetDimension) {
			return true;
		}

		$spawnPos = $rewriteData->spawnPosition ?? new Vector3(0, 64, 0);
		$fakePosition = $spawnPos->add(-2000, 0, -2000);

		PlayerRewriteUtils::injectPosition(
			$session,
			$fakePosition,
			$rewriteData->pitch,
			$rewriteData->yaw,
			$rewriteData->entityId
		);

		$rewriteData->dimension = $this->targetDimension;

		return true;
	}

	private function handlePhase2() : bool
	{
		$this->phase = self::PHASE_RESET;

		$rewriteData = $this->player->getRewriteData();
		$session = $this->player->getNetworkSession();

		$rewriteData->transferCallback = null;
		$rewriteData->postTransferSpawnInitialized = false;

		PlayerRewriteUtils::injectStopSound($session);

		$spawnPos = $rewriteData->spawnPosition ?? new Vector3(0, 64, 0);

		$session->getLogger()->debug(
			"TransferCallback PHASE_2: " .
			"clientRuntimeId={$rewriteData->entityId}, " .
			"backendRuntimeId={$rewriteData->originalEntityId}, " .
			"targetDimension={$this->targetDimension}, " .
			"spawn={$spawnPos->x},{$spawnPos->y},{$spawnPos->z}, " .
			"pitch={$rewriteData->pitch}, yaw={$rewriteData->yaw}"
		);

		// secure mode
		$rewriteData->dimension = $this->targetDimension;

		PlayerRewriteUtils::injectPosition(
			$session,
			$spawnPos,
			$rewriteData->pitch,
			$rewriteData->yaw,
			$rewriteData->entityId
		);

		$downstream = $this->player->getDownstream();

		if ($downstream === null || !$downstream->isConnected()) {
			$this->onTransferFailed();
			return true;
		}
		$downstream->sendGamePacket(SetLocalPlayerAsInitializedPacket::create($rewriteData->originalEntityId));

		$session->getLogger()->debug(
			"Sent manual SetLocalPlayerAsInitialized to backend with runtimeId={$rewriteData->originalEntityId}"
		);

		$this->player->setHandler(new DownstreamInGameHandler(
			$this->player,
			$this->player->getServer()->getLogger()
		));

		$this->player->getServer()->getScheduler()->scheduleDelayed(function () use ($session, $downstream, $spawnPos) : void {
			if (!$session->isConnected() || !$downstream->isConnected()) {
				return;
			}

			$session->getLogger()->debug(
				"Requesting chunks from backend after manual init: " .
				"radius=8, spawn={$spawnPos->x},{$spawnPos->y},{$spawnPos->z}"
			);

			$session->sendDataPacket(NetworkChunkPublisherUpdatePacket::create(
				new BlockPosition((int)$spawnPos->x, (int)$spawnPos->y, (int)$spawnPos->z),
				8 * 16,
				[]
			));

			$chunkRadiusPacket = new RequestChunkRadiusPacket();
			$chunkRadiusPacket->radius = 8;
			$chunkRadiusPacket->maxRadius = 8;

			$downstream->sendGamePacket($chunkRadiusPacket);
		}, 10);

		$session->getLogger()->debug('Transfer completed successfully');

		return true;
	}

	public function onTransferFailed() : void
	{
		$rewriteData = $this->player->getRewriteData();
		$rewriteData->transferCallback = null;

		$this->player->getNetworkSession()->getLogger()->warning('Transfer failed, attempting fallback');
		$this->player->tryFallbackOrDisconnect();
	}

	public function getPhase() : int
	{
		return $this->phase;
	}
}
