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

use aquarelay\lang\TranslationFactory;
use aquarelay\network\PacketHandlingException;
use aquarelay\ProxyServer;
use aquarelay\utils\Colors;
use aquarelay\utils\JWTException;
use aquarelay\utils\JWTUtils;
use aquarelay\utils\LoginData;
use Closure;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationType;
use pocketmine\network\mcpe\protocol\types\login\legacy\LegacyAuthChain;
use pocketmine\network\mcpe\protocol\types\login\legacy\LegacyAuthIdentityData;
use pocketmine\network\mcpe\protocol\types\login\openid\XboxAuthJwtBody;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function chr;
use function count;
use function gettype;
use function is_array;
use function is_object;
use function json_decode;
use function md5;
use function ord;
use function var_export;
use const JSON_THROW_ON_ERROR;

class UpstreamLoginHandler extends AbstractUpstreamPacketHandler
{

	public function handleLogin(LoginPacket $packet) : bool
	{
		if ($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93) {
			$authInfo = $this->parseAuthInfo($packet->authInfoJson);
		} elseif ($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_90) {
			$authInfo = $this->parseAuthInfo($packet->authInfoJson);
			$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
		} else {
			$authInfo = new AuthenticationInfo();
			$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
			$authInfo->Certificate = $packet->authInfoJson;
			$authInfo->Token = '';
		}

		if ($authInfo->AuthenticationType === AuthenticationType::FULL->value) {
			try {
				[, $clientDataClaims] = JWTUtils::parse($authInfo->Token);
				$clientData = $this->mapXboxTokenBody($clientDataClaims);

				$loginData = new LoginData(
					username: $clientData->xname,
					uuid: self::calculateUuidFromXuid($clientData->xid),
					xuid: $clientData->xid,
					chainData: json_decode($packet->authInfoJson, true),
					clientData: $packet->clientDataJwt,
					protocolVersion: $packet->protocol
				);

				$this->session->getLogger()->info("Player: " . Colors::AQUA . $clientData->xname);
				$this->session->setUsername($clientData->xname);
				$this->session->getLogger()->setPrefix("NetworkSession: " . $this->session->getDisplayName());

				$player = ProxyServer::getInstance()->getPlayerManager()->createPlayer($this->session, $loginData, $packet);
				$this->session->setPlayer($player);

			} catch (\Exception $e) {
				$this->session->disconnect(TranslationFactory::translate('session.login.decode_error', [$e->getMessage()]));

				return false;
			}
		} elseif ($authInfo->AuthenticationType === AuthenticationType::SELF_SIGNED->value) {
			if (ProxyServer::getInstance()->getConfig()->getGameSettings()->getXboxAuth()) {
				$this->session->disconnect(TranslationFactory::translate('session.login.failed'));
				return false;
			}

			try {
				$chainData = json_decode($authInfo->Certificate, flags: JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				throw PacketHandlingException::wrap($e, 'Error parsing self-signed certificate chain');
			}
			if (!is_object($chainData)) {
				throw new PacketHandlingException('Unexpected type for self-signed certificate chain: ' . gettype($chainData) . ', expected object');
			}

			try {
				$chain = $this->defaultJsonMapper('Self-signed auth chain JSON')->map($chainData, new LegacyAuthChain());
			} catch (\JsonMapper_Exception $e) {
				throw PacketHandlingException::wrap($e, 'Error mapping self-signed certificate chain');
			}
			if ($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93) {
				if (count($chain->chain) > 1 || !isset($chain->chain[0])) {
					throw new PacketHandlingException('Expected exactly one certificate in self-signed certificate chain, got ' . count($chain->chain));
				}

				try {
					[, $claimsArray] = JWTUtils::parse($chain->chain[0]);
				} catch (JWTException $e) {
					throw PacketHandlingException::wrap($e, 'Error parsing self-signed certificate');
				}
				if (!isset($claimsArray['extraData']) || !is_array($claimsArray['extraData'])) {
					throw new PacketHandlingException('Expected "extraData" to be present in self-signed certificate');
				}
			} else {
				$claimsArray = null;

				foreach ($chain->chain as $jwt) {
					try {
						[, $claims] = JWTUtils::parse($jwt);
					} catch (JWTException $e) {
						throw PacketHandlingException::wrap($e, 'Error parsing legacy certificate');
					}
					if (isset($claims['extraData'])) {
						if ($claimsArray !== null) {
							throw new PacketHandlingException('Multiple certificates in self-signed certificate chain contain "extraData" field');
						}

						if (!is_array($claims['extraData'])) {
							throw new PacketHandlingException("'extraData' key should be an array");
						}

						$claimsArray = $claims;
					}
				}

				if ($claimsArray === null) {
					throw new PacketHandlingException("'extraData' not found in legacy chain data");
				}
			}

			try {
				$claims = $this->defaultJsonMapper("Self-signed auth JWT 'extraData'")->map($claimsArray['extraData'], new LegacyAuthIdentityData());
			} catch (\JsonMapper_Exception $e) {
				throw PacketHandlingException::wrap($e, 'Error mapping self-signed certificate extraData');
			}

			if (!Uuid::isValid($claims->identity)) {
				throw new PacketHandlingException('Invalid UUID string in self-signed certificate: ' . $claims->identity);
			}
			$legacyUuid = Uuid::fromString($claims->identity);
			$username = $claims->displayName;
			$xuid = $this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93 ? '' : $claims->XUID;

			$loginData = new LoginData(
				username: $username,
				uuid: $legacyUuid,
				xuid: $xuid,
				chainData: json_decode($authInfo->Certificate, true),
				clientData: $packet->clientDataJwt,
				protocolVersion: $packet->protocol
			);

			$this->session->getLogger()->info("Player: " . Colors::AQUA . $username);
			$this->session->setUsername($username);
			$this->session->getLogger()->setPrefix("NetworkSession: " . $this->session->getDisplayName());
			$player = ProxyServer::getInstance()->getPlayerManager()->createPlayer($this->session, $loginData, $packet);
			$this->session->setPlayer($player);
		}

		$this->session->onClientLoginSuccess();

		return true;
	}

	/**
	 * @throws PacketHandlingException
	 */
	protected function parseAuthInfo(string $authInfo) : AuthenticationInfo
	{
		try {
			$authInfoJson = json_decode($authInfo, associative: false, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw PacketHandlingException::wrap($e);
		}
		if (!is_object($authInfoJson)) {
			throw new PacketHandlingException('Unexpected type for auth info data: ' . gettype($authInfoJson) . ', expected object');
		}

		$mapper = $this->defaultJsonMapper('Root authentication info JSON');

		try {
			$clientData = $mapper->map($authInfoJson, new AuthenticationInfo());
		} catch (\JsonMapper_Exception $e) {
			throw PacketHandlingException::wrap($e);
		}

		return $clientData;
	}

	/**
	 * @param array<string, mixed> $bodyArray
	 *
	 * @throws PacketHandlingException
	 *                                 Thanks to PocketMine-MP for this code!
	 */
	protected function mapXboxTokenBody(array $bodyArray) : XboxAuthJwtBody
	{
		$mapper = $this->defaultJsonMapper('OpenID JWT body');

		try {
			$header = $mapper->map($bodyArray, new XboxAuthJwtBody());
		} catch (\JsonMapper_Exception $e) {
			throw PacketHandlingException::wrap($e);
		}

		return $header;
	}

	private function defaultJsonMapper(string $logContext) : \JsonMapper
	{
		$mapper = new \JsonMapper();
		$mapper->bExceptionOnMissingData = true;
		$mapper->undefinedPropertyHandler = $this->warnUndefinedJsonPropertyHandler($logContext);
		$mapper->bStrictObjectTypeChecking = true;
		$mapper->bEnforceMapType = false;

		return $mapper;
	}

	private static function calculateUuidFromXuid(string $xuid) : UuidInterface
	{
		$hash = md5('pocket-auth-1-xuid:' . $xuid, true);
		$hash[6] = chr((ord($hash[6]) & 0x0F) | 0x30);
		$hash[8] = chr((ord($hash[8]) & 0x3F) | 0x80);

		return Uuid::fromBytes($hash);
	}

	/**
	 * @phpstan-return Closure(object, string, mixed) : void
	 */
	private function warnUndefinedJsonPropertyHandler(string $context) : Closure
	{
		return fn (object $object, string $name, mixed $value) => ProxyServer::getInstance()->getLogger()->warning(
			"{$context}: Unexpected JSON property for " . (new \ReflectionClass($object))->getShortName() . ': ' . $name . ' = ' . var_export($value, return: true)
		);
	}
}
