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

use aquarelay\ProxyServer;
use aquarelay\utils\JWTUtils;
use aquarelay\utils\LoginData;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;

class LoginHandler extends PacketHandler {

	public function handleLogin(LoginPacket $packet): bool {
		try {
			[, $clientDataClaims, ] = JWTUtils::getInstance()->parse($packet->clientDataJwt);
			$clientData = $this->defaultJsonMapper()->map($clientDataClaims, new ClientData());

			$loginData = new LoginData(
				username: $clientData->ThirdPartyName,
				clientUuid: (string) $clientData->ClientRandomId,
				xuid: $clientData->SelfSignedId,
				chainData: json_decode($packet->authInfoJson, true),
				clientData: $packet->clientDataJwt,
				protocolVersion: $packet->protocol
			);

			$this->session->setUsername($clientData->ThirdPartyName);

			$player = ProxyServer::getInstance()->getPlayerManager()->createPlayer($this->session, $loginData);
			$this->session->setPlayer($player);

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