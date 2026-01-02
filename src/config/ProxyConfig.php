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

namespace aquarelay\config;

use Symfony\Component\Yaml\Yaml;

final class ProxyConfig {

    public function __construct(
        public readonly string $bindAddress,
        public readonly int $bindPort,
        public readonly string $backendAddress,
        public readonly int $backendPort,
        public readonly int $sessionTimeout
    ){}

    public static function load(string $file) : self{
        $data = Yaml::parseFile($file);

        return new self(
            $data["bind"]["address"],
            (int) $data["bind"]["port"],
            $data["backend"]["address"],
            (int) $data["backend"]["port"],
            (int) $data["session-timeout"]
        );
    }
}
