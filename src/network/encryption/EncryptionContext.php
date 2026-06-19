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

namespace aquarelay\network\encryption;

use Crypto\Cipher;
use pmmp\encoding\LE;
use function openssl_digest;
use function strlen;
use function substr;

final class EncryptionContext
{
	private const CHECKSUM_ALGO = "sha256";
	private const CHECKSUM_LENGTH = 8;

	private Cipher $encryptCipher;
	private Cipher $decryptCipher;

	private int $encryptCounter = 0;
	private int $decryptCounter = 0;

	private function __construct(private readonly string $key, string $algorithm, string $iv)
	{
		$this->decryptCipher = new Cipher($algorithm);
		$this->decryptCipher->decryptInit($this->key, $iv);

		$this->encryptCipher = new Cipher($algorithm);
		$this->encryptCipher->encryptInit($this->key, $iv);
	}

	public static function fakeGCM(string $encryptionKey) : self
	{
		return new self($encryptionKey, "AES-256-CTR", substr($encryptionKey, 0, 12) . "\x00\x00\x00\x02");
	}

	public function encrypt(string $payload) : string
	{
		return $this->encryptCipher->encryptUpdate($payload . $this->calculateChecksum($this->encryptCounter++, $payload));
	}

	public function decrypt(string $payload) : string
	{
		if (strlen($payload) < self::CHECKSUM_LENGTH + 1) {
			throw new EncryptionException("Encrypted payload is too short");
		}

		$decrypted = $this->decryptCipher->decryptUpdate($payload);

		$content = substr($decrypted, 0, -self::CHECKSUM_LENGTH);
		$checksum = substr($decrypted, -self::CHECKSUM_LENGTH);

		$counter = $this->decryptCounter++;
		$expected = $this->calculateChecksum($counter, $content);
		if ($checksum !== $expected) {
			throw new EncryptionException("Packet $counter checksum verification failed (possible decryption desync)");
		}

		return $content;
	}

	private function calculateChecksum(int $counter, string $payload) : string
	{
		$hash = openssl_digest(LE::packUnsignedLong($counter) . $payload . $this->key, self::CHECKSUM_ALGO, true);
		if ($hash === false) {
			throw new EncryptionException("Failed to compute packet checksum");
		}

		return substr($hash, 0, self::CHECKSUM_LENGTH);
	}
}
