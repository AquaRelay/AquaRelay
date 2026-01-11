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

	private function split(string $jwt) : array{
		$v = explode(".", $jwt, limit: 4);
		if(count($v) !== 3){
			throw new JWTException("Expected exactly 3 JWT parts delimited by a period");
		}
		return [$v[0], $v[1], $v[2]];
	}

	public function b64UrlEncode(string $str) : string{
		return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
	}

	public function b64UrlDecode(string $str) : string{
		if(($len = strlen($str) % 4) !== 0){
			$str .= str_repeat('=', 4 - $len);
		}
		$decoded = base64_decode(strtr($str, '-_', '+/'), true);
		if($decoded === false){
			throw new JWTException("Malformed base64url encoded payload could not be decoded");
		}
		return $decoded;
	}

	public function parse(string $token) : array{
		$v = $this->split($token);
		$header = json_decode($this->b64UrlDecode($v[0]), true);
		if(!is_array($header)){
			throw new JWTException("Failed to decode JWT header JSON: " . json_last_error_msg());
		}
		$body = json_decode($this->b64UrlDecode($v[1]), true);
		if(!is_array($body)){
			throw new JWTException("Failed to decode JWT payload JSON: " . json_last_error_msg());
		}
		$signature = $this->b64UrlDecode($v[2]);
		return [$header, $body, $signature];
	}

}