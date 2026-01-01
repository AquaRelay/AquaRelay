<?php

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
