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

namespace aquarelay\utils;

class JWTUtils
{

	use InstanceTrait;

	public function getUsernameFromJwt(string $jwt) : ?string{
		$parts = explode('.', $jwt);
		if(count($parts) < 2){
			throw new JWTException("The payload is corrupted");
		}

		$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
		if(!is_array($payload)){
			throw new JWTException("The payload is corrupted");
		}

		$displayName = $payload["extraData"]["displayName"] ?? null;

		if (is_null($displayName)){
			throw new JWTException("Could not parse display name from JWT");
		}

		return $displayName;
	}

}