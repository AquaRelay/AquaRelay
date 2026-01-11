<?php

/*
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *             |_|                                |___/
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

namespace aquarelay\network\handler;

use aquarelay\utils\JWTUtils;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;

class PreLoginHandler extends PacketHandler {

	public function handleRequestNetworkSettings(RequestNetworkSettingsPacket $packet): bool {
		if ($packet->getProtocolVersion() > ProtocolInfo::CURRENT_PROTOCOL){
			$this->session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::LOGIN_FAILED_SERVER));
			return false;
		}

		$pk = NetworkSettingsPacket::create(
			NetworkSettingsPacket::COMPRESS_EVERYTHING,
			CompressionAlgorithm::ZLIB,
			false,
			0,
			0
		);
		$this->session->sendDataPacket($pk, true);
		$this->session->enableCompression();
		return true;
	}

	public function handleLogin(LoginPacket $packet): bool {
		$clientDataJwt = $packet->clientDataJwt;
		try {
			[, $clientDataClaims, ] = JWTUtils::getInstance()->parse($clientDataJwt);
			$clientData = $this->defaultJsonMapper()->map($clientDataClaims, new ClientData());

			$this->session->setUsername($clientData->ThirdPartyName);

			$this->logger->info("Player login received: " . $this->session->getUsername());
		} catch (\Exception $e) {
			$this->session->disconnect("Login decode error: " . $e->getMessage());
			return false;
		}

		$this->session->onClientLoginSuccess($packet);
		return true;
	}

	private function defaultJsonMapper() : \JsonMapper{
		$mapper = new \JsonMapper();
		$mapper->bExceptionOnMissingData = true;
		$mapper->undefinedPropertyHandler = fn(object $object, string $name, mixed $value) => $this->logger->warning(
			"Unexpected JSON property for " . (new \ReflectionClass($object))->getShortName() . ": " . $name . " = " . var_export($value, return: true)
		);
		$mapper->bStrictObjectTypeChecking = true;
		$mapper->bEnforceMapType = false;
		return $mapper;
	}

}