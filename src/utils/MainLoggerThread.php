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

use pmmp\thread\Thread;
use pmmp\thread\ThreadSafeArray;

class MainLoggerThread extends Thread
{
	private ThreadSafeArray $buffer;
	private bool $shutdown = false;

	public function __construct(private string $logFile)
	{
		$this->buffer = new ThreadSafeArray();
	}

	public function write(string $line): void
	{
		$this->synchronized(function () use ($line): void {
			$this->buffer[] = $line;
			$this->notify();
		});
	}

	public function shutdown(): void
	{
		$this->synchronized(function (): void {
			$this->shutdown = true;
			$this->notify();
		});
		$this->join();
	}

	public function run(): void
	{
		$handle = fopen($this->logFile, "ab");

		while (!$this->shutdown) {
			$this->synchronized(function (): void {
				if (count($this->buffer) === 0 && !$this->shutdown) {
					$this->wait();
				}
			});

			while (($line = $this->buffer->shift()) !== null) {
				echo $line;
				fwrite($handle, preg_replace('/\x1b\[[0-9;]*m/', '', $line));
			}
		}
		fclose($handle);
	}
}