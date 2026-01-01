<?php

declare(strict_types=1);

namespace aquarelay\network;

use aquarelay\config\ProxyConfig;
use aquarelay\ProxyServer;
use aquarelay\session\ClientSession;
use raklib\client\ClientSocket;
use raklib\protocol\MessageIdentifiers;
use raklib\generic\SocketException;
use raklib\utils\InternetAddress;

class ProxyLoop {

    /** @var ClientSession[][] */
    private array $clients = [];

    private ?string $cachedPong = null;

    public function __construct(private ProxyServer $server, private ProxyConfig $config){}

    public function run() : void{
        while(true){
            $this->tick();
        }
    }

    private function tick() : void{
        $r = [$this->server->listener->getSocket(), $this->server->unconnectedBackend->getSocket()];
        $map = [];

        foreach($this->clients as $ipClients){
            foreach($ipClients as $client){
                $r[] = $client->socket()->getSocket();
                $map[(int) end($r)] = $client;
            }
        }

        if(socket_select($r, $w, $e, 10) <= 0){
            return;
        }

        foreach($r as $sock){
            if($sock === $this->server->unconnectedBackend->getSocket()){
                $this->handleBackendUnconnected();
            }elseif($sock === $this->server->listener->getSocket()){
                $this->handleClient();
            }elseif(isset($map[(int) $sock])){
                $this->relayServerToClient($map[(int) $sock]);
            }
        }

        $this->cleanup();
    }

    private function handleBackendUnconnected() : void{
        $buf = $this->server->unconnectedBackend->readPacket();
        if(!is_null($buf) && ord($buf[0]) === MessageIdentifiers::ID_UNCONNECTED_PONG){
            $this->cachedPong = $buf;
        }
    }

    private function handleClient() : void{
        try {
            $buf = $this->server->listener->readPacket($ip, $port);
        } catch(SocketException) {
            return;
        }

        if($buf === null){
            return;
        }

        if(isset($this->clients[$ip][$port])) {
            $c = $this->clients[$ip][$port];
            $c->touch();
            $c->socket()->writePacket($buf);
            return;
        }

        if(ord($buf[0]) === MessageIdentifiers::ID_UNCONNECTED_PING) {
            $this->server->unconnectedBackend->writePacket($buf);
            if($this->cachedPong !== null){
                $this->server->listener->writePacket($this->cachedPong, $ip, $port);
            }
            return;
        }

        if(ord($buf[0]) === MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_1){
            $this->server->unconnectedBackend->writePacket($buf);

            $socket = new ClientSocket(
                new InternetAddress($this->config->backendAddress, $this->config->backendPort, 4)
            );
            $socket->setBlocking(false);

            $this->clients[$ip][$port] = new ClientSession(
                new InternetAddress($ip, $port, 4),
                $socket
            );
        }
    }

    private function relayServerToClient(ClientSession $client) : void {
        $buf = $client->socket()->readPacket();
        if($buf !== null){
            $addr = $client->address();
            $this->server->listener->writePacket($buf, $addr->getIp(), $addr->getPort());
        }
    }

    private function cleanup() : void {
        foreach($this->clients as $ip => $ports){
            foreach($ports as $port => $client){
                if($client->expired($this->config->sessionTimeout)){
                    unset($this->clients[$ip][$port]);
                }
            }
        }
    }
}
