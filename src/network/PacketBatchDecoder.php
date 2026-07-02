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

namespace aquarelay\network;

use aquarelay\network\compression\ZlibCompressor;
use pmmp\encoding\ByteBufferReader;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use function ord;
use function substr;

final class PacketBatchDecoder
{
	private const MCPE_RAKNET_PACKET_ID = 0xFE;

	public static function decodeRaw(string $payload, \Logger $logger, bool $expectCompressionByte = true) : \Generator
	{
		if ($payload === "") {
			return;
		}

		$offset = 0;
		if (ord($payload[0]) === self::MCPE_RAKNET_PACKET_ID) {
			$offset = 1;
		}

		if (!isset($payload[$offset])) {
			return;
		}

		if ($expectCompressionByte) {
			$compressionType = ord($payload[$offset]);
			$data = substr($payload, $offset + 1);

			if ($compressionType === CompressionAlgorithm::ZLIB) {
				try {
					$data = ZlibCompressor::getInstance()->decompress($data);
				} catch (\Throwable $e) {
					$logger->error("Decompressing error: " . $e->getMessage());
					return;
				}
			} elseif ($compressionType !== CompressionAlgorithm::NONE) {
				if ($compressionType < 0x80) {
					$data = substr($payload, $offset);
				}
			}
		} else {
			$data = substr($payload, $offset);
		}

		if ($data === "") {
			return;
		}

		try {
			$stream = new ByteBufferReader($data);
			foreach (PacketBatch::decodeRaw($stream) as $buffer) {
				yield $buffer;
			}
		} catch (\Throwable $e) {
			$logger->debug("Batch decode error: " . $e->getMessage());
		}
	}
}
