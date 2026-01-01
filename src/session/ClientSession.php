<?php

declare(strict_types=1);

namespace aquarelay\session;

use raklib\client\ClientSocket;
use raklib\utils\InternetAddress;

class ClientSession {

    private int $lastUsed;

    public function __construct(private InternetAddress $address, private ClientSocket $socket){
        $this->lastUsed = time();
    }

    public function address() : InternetAddress{
        return $this->address;
    }

    public function socket() : ClientSocket{
        return $this->socket;
    }

    public function touch() : void{
        $this->lastUsed = time();
    }

    public function expired(int $timeout) : bool{
        return (time() - $this->lastUsed) > $timeout;
    }
}
