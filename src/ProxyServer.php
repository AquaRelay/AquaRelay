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
 * @link http://www.aquarelay.dev/
 *
 */

declare(strict_types=1);

namespace aquarelay;

use aquarelay\config\ProxyConfig;
use aquarelay\utils\MainLogger;
use raklib\client\ClientSocket;
use raklib\server\ServerSocket;
use raklib\utils\InternetAddress;
use Throwable;

class ProxyServer {

    public ServerSocket $listener;
    public ClientSocket $unconnectedBackend;

    private MainLogger $logger;
    private ProxyConfig $config;

    private static ?self $instance = null;

    public static function getInstance() : ?ProxyServer{
        return self::$instance;
    }

    public function getConfig() : ProxyConfig
    {
        return $this->config;
    }

    public function __construct(ProxyConfig $config){
        $startTime = microtime(true);
        self::$instance = $this;
		$this->config = $config;

        $this->logger = new MainLogger("Main Thread", false);
        $this->logger->info("Starting server...");

        set_exception_handler(function(Throwable $e){
            $this->logger->alert("Uncaught exception: " . $e->getMessage());
            $this->logger->debug($e->getTraceAsString());
            exit(1);
        });

        set_error_handler(function(
            int $severity,
            string $message,
            string $file,
            int $line
        ){
            $this->logger->error("$message ($file:$line)");
        });

        $this->unconnectedBackend = new ClientSocket(
            new InternetAddress($config->backendAddress, $config->backendPort, 4)
        );
        $this->unconnectedBackend->setBlocking(false);
        $this->logger->info("Binding client socket to: $config->backendAddress:$config->backendPort");

        $this->listener = new ServerSocket(
            new InternetAddress($config->bindAddress, $config->bindPort, 4)
        );
        $this->listener->setBlocking(false);
        $this->logger->info("Binding listener to: {$config->bindAddress}:{$config->bindPort}");

        $this->logger->info("Started proxy! (" . round(microtime(true) - $startTime, 3) ."s)");
    }

}
