<?php

declare(strict_types=1);

namespace aquarelay\utils;

class LoginData {
	public function __construct(
		public readonly string $username,
		public readonly string $clientUuid,
		public readonly string $xuid,
		public readonly array $chainData,   // JWT Chain
		public readonly string $clientData, // JWT Client
		public readonly int $protocolVersion,
		public int $clientSubId = 0
	) {}
}