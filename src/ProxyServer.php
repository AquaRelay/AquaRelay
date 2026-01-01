<?php

declare(strict_types=1);

namespace aquarelay;

use aquarelay\config\ProxyConfig;
use aquarelay\utils\Logger;
use raklib\client\ClientSocket;
use raklib\server\ServerSocket;
use raklib\utils\InternetAddress;
use Throwable;

class ProxyServer {

    public ServerSocket $listener;
    public ClientSocket $unconnectedBackend;

    private Logger $logger;
    private bool $running = true;
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

        $this->logger = new Logger("Main Thread", false);
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
        $this->logger->info("Binding client socket to: {$config->backendAddress}:{$config->backendPort}");

        $this->listener = new ServerSocket(
            new InternetAddress($config->bindAddress, $config->bindPort, 4)
        );
        $this->listener->setBlocking(false);
        $this->logger->info("Binding listener to: {$config->bindAddress}:{$config->bindPort}");

        $this->logger->info("Started proxy! (" . round(microtime(true) - $startTime, 3) ."s)");
    }

    public function start() : void{
        while($this->running){
            $this->tick();
            usleep(1000);
        }
    }

    private function tick() : void{
        $read = [
            $this->listener->getSocket(),
            $this->unconnectedBackend->getSocket()
        ];

        $write = $except = null;

        if(@socket_select($read, $write, $except, 0, 200000) > 0){
            foreach($read as $socket){
                if($socket === $this->listener->getSocket()){
                    $this->handleClientPacket();
                }elseif($socket === $this->unconnectedBackend->getSocket()){
                    $this->handleBackendPacket();
                }
            }
        }
    }

    private function handleClientPacket() : void{
        $buffer = $this->listener->readPacket($ip, $port);
        if($buffer === null){
            return;
        }

        $this->logger->debug("Client packet from $ip:$port (" . strlen($buffer) . " bytes)");
        $this->unconnectedBackend->writePacket($buffer);
    }

    private function handleBackendPacket() : void{
        $buffer = $this->unconnectedBackend->readPacket();
        if($buffer === null){
            return;
        }

        $this->listener->writePacket($buffer, $this->getConfig()->backendAddress, $this->getConfig()->backendPort);
    }
}
