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

use aquarelay\network\NetworkSession;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDifficultyPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\GameRule;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function chr;
use function str_repeat;

final class PlayerRewriteUtils
{
	private function __construct() {}

	public static function determineDimensionId(int $from, int $to) : int
	{
		if ($from === $to) {
			return $from === DimensionIds::OVERWORLD ? DimensionIds::NETHER : DimensionIds::OVERWORLD;
		}
		return $to;
	}

	public static function injectChunkPublisherUpdate(NetworkSession $session, BlockPosition $position, int $radius) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(NetworkChunkPublisherUpdatePacket::create($position, $radius, []));
	}

	public static function injectGameMode(NetworkSession $session, int $gamemode) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(SetPlayerGameTypePacket::create($gamemode));
	}

	/**
	 * @param GameRule[] $gameRules
	 * @phpstan-param array<string, GameRule> $gameRules
	 */
	public static function injectGameRules(NetworkSession $session, array $gameRules) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(GameRulesChangedPacket::create($gameRules));
	}

	public static function injectClearWeather(NetworkSession $session) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, Vector3::zero()));
		$session->sendDataPacket(LevelEventPacket::create(LevelEvent::STOP_RAIN, 10000, Vector3::zero()));
	}

	public static function injectSetDifficulty(NetworkSession $session, int $difficulty) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(SetDifficultyPacket::create($difficulty));
	}

	public static function injectRemoveEntity(NetworkSession $session, int $uniqueId) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(RemoveActorPacket::create($uniqueId));
	}

	/**
	 * @param UuidInterface[] $playerList
	 */
	public static function injectRemoveAllPlayers(NetworkSession $session, array $playerList) : void
	{
		if (!$session->isConnected() || empty($playerList)) {
			return;
		}
		$entries = [];
		foreach ($playerList as $uuid) {
			/** @var string $uuid */
			$entry = new PlayerListEntry();
			$entry->uuid = Uuid::fromString($uuid);
			$entries[] = $entry;
		}
		$session->sendDataPacket(PlayerListPacket::remove($entries));
	}

	public static function injectRemoveAllEffects(NetworkSession $session, int $runtimeId) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		for ($i = 0; $i < 30; $i++) {
			$session->sendDataPacket(MobEffectPacket::remove($runtimeId, $i, 0));
		}
	}

	public static function injectRemoveObjective(NetworkSession $session, string $objectiveId) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(RemoveObjectivePacket::create($objectiveId));
	}

	public static function injectRemoveBossbar(NetworkSession $session, int $bossbarId) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(BossEventPacket::hide($bossbarId));
	}

	public static function injectPosition(NetworkSession $session, Vector3 $position, float $pitch, float $yaw, int $runtimeId) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(MovePlayerPacket::simple(
			$runtimeId,
			$position,
			$pitch,
			$yaw,
			$yaw,
			MovePlayerPacket::MODE_RESET,
			false,
			0,
			0
		));
	}

	public static function injectDimensionChange(NetworkSession $session, int $dimensionId, Vector3 $position, int $runtimeId, bool $chunks) : void
	{
		if (!$session->isConnected()) {
			return;
		}

		$session->sendDataPacket(ChangeDimensionPacket::create($dimensionId, $position, true, null));

		if ($chunks) {
			self::injectChunkPublisherUpdate($session, BlockPosition::fromVector3($position), 3);
			self::injectEmptyChunks($session, $position, 3, $dimensionId);
		}
	}

	public static function injectEmptyChunks(NetworkSession $session, Vector3 $spawnPosition, int $radius, int $dimension) : void
	{
		$chunkX = $spawnPosition->getFloorX() >> 4;
		$chunkZ = $spawnPosition->getFloorZ() >> 4;

		for ($x = -$radius; $x <= $radius; $x++) {
			for ($z = -$radius; $z <= $radius; $z++) {
				$session->sendDataPacket(self::createEmptyChunk($chunkX + $x, $chunkZ + $z, $dimension));
			}
		}
	}

	private static function createEmptyChunk(int $chunkX, int $chunkZ, int $dimension) : LevelChunkPacket
	{
		$biomeSections = match ($dimension) {
			DimensionIds::NETHER => 8,
			DimensionIds::THE_END => 16,
			default => 24,
		};

		$buffer = '';
		$buffer .= chr(8);  // section version
		$buffer .= chr(0);  // zero block storages

		$buffer .= chr((1 << 1) | 1); // runtime flag and palette id (1 bit per block, palette)
		$buffer .= str_repeat("\x00", 512); // 128 * 4 = 512 bytes
		// palette size = 1 (varint)
		$buffer .= chr(1);
		// palette entry = 0 (air, varint)
		$buffer .= chr(0);

		// remaining biome sections link to previous
		for ($i = 1; $i < $biomeSections; $i++) {
			$buffer .= chr((127 << 1) | 1); // link to previous biome palette
		}

		$buffer .= chr(0); // borders

		return LevelChunkPacket::create(
			new ChunkPosition($chunkX, $chunkZ),
			$dimension,
			1, // subChunkCount
			false, // clientSubChunkRequestsEnabled
			null, // usedBlobHashes
			$buffer
		);
	}

	public static function injectStopSound(NetworkSession $session) : void
	{
		if (!$session->isConnected()) {
			return;
		}
		$session->sendDataPacket(StopSoundPacket::create('portal.travel', true, false));
	}
}
