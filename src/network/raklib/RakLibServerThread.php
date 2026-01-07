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

namespace aquarelay\network\raklib;

use aquarelay\network\raklib\ipc\PthreadsChannelReader;
use aquarelay\network\raklib\ipc\PthreadsChannelWriter;
use aquarelay\utils\MainLogger;
use pmmp\thread\Thread;
use pmmp\thread\ThreadSafeArray;
use raklib\generic\SocketException;
use raklib\server\ipc\RakLibToUserThreadMessageSender;
use raklib\server\ipc\UserToRakLibThreadMessageReceiver;
use raklib\server\Server;
use raklib\server\ServerSocket;
use raklib\server\SimpleProtocolAcceptor;
use raklib\utils\ExceptionTraceCleaner;
use raklib\utils\InternetAddress;

class RakLibServerThread extends Thread {

	private bool $running = false;
	private ThreadSafeArray $mainToThread;
	private ThreadSafeArray $threadToMain;
	private int $serverId;

	public function __construct(
		private MainLogger $logger,
		private string $address,
		private int $port,
		private int $maxMtu,
		private int $protocolVersion
	){
		$this->mainToThread = new ThreadSafeArray();
		$this->threadToMain = new ThreadSafeArray();
	}

	public function getReadBuffer() : ThreadSafeArray {
		return $this->threadToMain;
	}

	public function getWriteBuffer() : ThreadSafeArray {
		return $this->mainToThread;
	}

	public function stop() : void {
		$this->running = false;
	}

	public function run(): void {
		$this->running = true;
		require dirname(__DIR__, 3) . '/vendor/autoload.php';

		try {
			$socket = new ServerSocket(new InternetAddress($this->address, $this->port, 4));
		} catch (SocketException $e) {
			$this->logger->error("Socket bind failed: " . $e->getMessage());
			return;
		}

		\GlobalLogger::set($this->logger);

		$this->serverId = mt_rand(0, 1000000);

		$server = new Server(
			mt_rand(0, 1000000),
			$this->logger,
			$socket,
			$this->maxMtu,
			new SimpleProtocolAcceptor($this->protocolVersion),
			new UserToRakLibThreadMessageReceiver(new PthreadsChannelReader($this->mainToThread)),
			new RakLibToUserThreadMessageSender(new PthreadsChannelWriter($this->threadToMain)),
			new ExceptionTraceCleaner(dirname(__DIR__))
		);

		$server->setName("MCPE;§bAquaRelay§r Proxy;898;1.21.130;0;100;" . $this->serverId . ";Proxy;Survival;1;19132;");

		while ($this->running) {
			$server->tickProcessor();
			usleep(5000);
		}

		$server->waitShutdown();
	}
}