<?php

declare(strict_types=1);

namespace aquarelay\network;

use aquarelay\network\compression\ZlibCompressor;
use pmmp\encoding\ByteBufferReader;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
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
