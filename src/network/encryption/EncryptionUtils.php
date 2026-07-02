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

use aquarelay\utils\JWTUtils;
use aquarelay\utils\LoginData;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationType;
use function base64_decode;
use function base64_encode;
use function bin2hex;
use function chunk_split;
use function file_put_contents;
use function gmp_init;
use function gmp_strval;
use function hex2bin;
use function is_file;
use function json_encode;
use function ltrim;
use function openssl_digest;
use function openssl_error_string;
use function openssl_pkey_derive;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function openssl_sign;
use function ord;
use function preg_replace;
use function str_pad;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function time;
use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const OPENSSL_ALGO_SHA384;
use const OPENSSL_KEYTYPE_EC;
use const STR_PAD_LEFT;

final class EncryptionUtils
{
	private const CURVE = "secp384r1";
	private const COORDINATE_LENGTH = 48; // P-384 -> 48-byte R and S components

	private const MOJANG_AUDIENCE = "api://auth-minecraft-services/multiplayer";

	private static ?\OpenSSLAsymmetricKey $keyPair = null;
	private static ?string $publicKeyDerBase64 = null;
	private static ?string $opensslConfigPath = null;

	public static function getKeyPair() : \OpenSSLAsymmetricKey
	{
		if (self::$keyPair === null) {
			$key = openssl_pkey_new([
				"private_key_type" => OPENSSL_KEYTYPE_EC,
				"curve_name" => self::CURVE,
				"config" => self::getOpenSslConfigPath(),
			]);
			if ($key === false) {
				throw new EncryptionException("Failed to generate EC key pair: " . (openssl_error_string() ?: "unknown error"));
			}
			self::$keyPair = $key;
		}

		return self::$keyPair;
	}

	private static function getOpenSslConfigPath() : string
	{
		if (self::$opensslConfigPath === null) {
			$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "aquarelay_openssl.cnf";
			if (!is_file($path) && file_put_contents($path, "[req]\ndefault_bits = 384\ndistinguished_name = dn\n[dn]\n") === false) {
				throw new EncryptionException("Failed to write OpenSSL configuration to " . $path);
			}
			self::$opensslConfigPath = $path;
		}

		return self::$opensslConfigPath;
	}

	public static function getPublicKeyDerBase64() : string
	{
		if (self::$publicKeyDerBase64 === null) {
			$details = openssl_pkey_get_details(self::getKeyPair());
			if ($details === false || !isset($details["key"])) {
				throw new EncryptionException("Failed to read public key details");
			}
			$der = base64_decode((string) preg_replace('/-----[^-]+-----|\s/', "", $details["key"]), true);
			if ($der === false) {
				throw new EncryptionException("Failed to decode public key PEM");
			}
			self::$publicKeyDerBase64 = base64_encode($der);
		}

		return self::$publicKeyDerBase64;
	}

	public static function deriveSharedKey(string $salt, string $peerKeyB64) : string
	{
		$der = base64_decode($peerKeyB64, true);
		if ($der === false) {
			throw new EncryptionException("Malformed peer public key in handshake");
		}

		$secret = openssl_pkey_derive(self::derToPem($der, "PUBLIC KEY"), self::getKeyPair(), 48);
		if ($secret === false) {
			throw new EncryptionException("Failed to derive shared secret: " . (openssl_error_string() ?: "unknown error"));
		}

		$padded = hex2bin(str_pad(gmp_strval(gmp_init(bin2hex($secret), 16), 16), 96, "0", STR_PAD_LEFT));
		if ($padded === false) {
			throw new EncryptionException("Failed to normalise shared secret");
		}

		$key = openssl_digest($salt . $padded, "sha256", true);
		if ($key === false) {
			throw new EncryptionException("Failed to derive encryption key");
		}

		return $key;
	}

	public static function createSelfSignedLoginPacket(LoginData $loginData, int $protocol, string $clientIp) : LoginPacket
	{
		$publicKey = self::getPublicKeyDerBase64();
		$header = ["alg" => "ES384", "x5u" => $publicKey];

		//The client data JWT is verified against our identity key (cpk), so it must be re-signed with our key.
		[, $clientDataBody] = JWTUtils::parse($loginData->clientData);

		$clientDataBody["AquaRelay_Authenticated"] = true;
		$clientDataBody["AquaRelay_XUID"] = $loginData->xuid;
		$clientDataBody["AquaRelay_IP"] = $clientIp;

		$clientDataJwt = self::signJwt($header, $clientDataBody);

		if ($protocol >= ProtocolInfo::PROTOCOL_1_21_90) {
			$authInfoJson = json_encode([
				"AuthenticationType" => AuthenticationType::SELF_SIGNED->value,
				"Certificate" => "",
				"Token" => self::buildSelfSignedAuthToken($loginData, $header, $publicKey),
			], JSON_THROW_ON_ERROR);
		} else {
			$authInfoJson = self::buildLegacyChain($loginData, $header, $publicKey);
		}

		return LoginPacket::create($protocol, $authInfoJson, $clientDataJwt);
	}

	private static function buildSelfSignedAuthToken(LoginData $loginData, array $header, string $publicKey) : string
	{
		$now = time();

		return self::signJwt($header, [
			"nbf" => $now - 60,
			"exp" => $now + 86400,
			"iat" => $now - 60,
			"iss" => "self",
			"aud" => self::MOJANG_AUDIENCE,
			"cpk" => $publicKey,
			"leguuid" => $loginData->uuid->toString(),
			"xname" => $loginData->username,
			"mid" => $loginData->uuid->toString(),
			"ap" => 0,
		]);
	}

	/**
	 * @param array<string, mixed> $header
	 * @throws \JsonException
	 */
	private static function buildLegacyChain(LoginData $loginData, array $header, string $publicKey) : string
	{
		$now = time();

		$chainJwt = self::signJwt($header, [
			"nbf" => $now - 60,
			"exp" => $now + 86400,
			"iat" => $now - 60,
			"iss" => "self",
			"certificateAuthority" => true,
			"identityPublicKey" => $publicKey,
			"extraData" => [
				"XUID" => $loginData->xuid,
				"identity" => $loginData->uuid->toString(),
				"displayName" => $loginData->username,
				"titleId" => "",
			],
		]);

		return json_encode(["chain" => [$chainJwt]], JSON_THROW_ON_ERROR);
	}

	/**
	 * @param array<string, mixed> $header
	 * @param array<string, mixed> $body
	 * @throws \JsonException
	 */
	private static function signJwt(array $header, array $body) : string
	{
		$signingInput = JWTUtils::b64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)) . "." . JWTUtils::b64UrlEncode(json_encode($body, JSON_THROW_ON_ERROR));

		$derSignature = "";
		if (!openssl_sign($signingInput, $derSignature, self::getKeyPair(), OPENSSL_ALGO_SHA384)) {
			throw new EncryptionException("Failed to sign JWT: " . (openssl_error_string() ?: "unknown error"));
		}

		return $signingInput . "." . JWTUtils::b64UrlEncode(self::derToRawSignature($derSignature));
	}

	private static function derToRawSignature(string $der) : string
	{
		$offset = 0;
		if (ord($der[$offset++]) !== 0x30) {
			throw new EncryptionException("Invalid DER signature: missing sequence");
		}

		$seqLen = ord($der[$offset++]);
		if (($seqLen & 0x80) !== 0) {
			$offset += $seqLen & 0x7f; //skip multi-byte length
		}

		$read = static function (string $der, int &$offset) : string {
			if (ord($der[$offset++]) !== 0x02) {
				throw new EncryptionException("Invalid DER signature: missing integer");
			}
			$len = ord($der[$offset++]);
			$value = substr($der, $offset, $len);
			$offset += $len;
			return ltrim($value, "\x00");
		};

		$r = $read($der, $offset);
		$s = $read($der, $offset);

		if (strlen($r) > self::COORDINATE_LENGTH || strlen($s) > self::COORDINATE_LENGTH) {
			throw new EncryptionException("Invalid DER signature: component too long");
		}

		return str_pad($r, self::COORDINATE_LENGTH, "\x00", STR_PAD_LEFT) . str_pad($s, self::COORDINATE_LENGTH, "\x00", STR_PAD_LEFT);
	}

	private static function derToPem(string $der, string $label) : string
	{
		return "-----BEGIN $label-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $label-----\n";
	}
}
