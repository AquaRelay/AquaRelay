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

use pmmp\thread\Thread as NativeThread;
use pmmp\thread\ThreadSafe;

class MainLogger extends ThreadSafe implements \Logger
{

	private string $format = Colors::BLUE . "%s " . Colors::RESET . "[%s] [%s%s" . Colors::RESET . "]" . Colors::WHITE . " %s" . Colors::RESET;
	private MainLoggerThread $writerThread;

	public function __construct(private string $mainThreadName, string $logFile = "proxy.log", private bool $debugMode = false) {
		$this->writerThread = new MainLoggerThread($logFile);
		$this->writerThread->start(NativeThread::INHERIT_NONE);
	}

	private function send(string $level, string $color, string $message): void {
		$time = date("H:i:s");

		$currentThread = NativeThread::getCurrentThread();
		$threadName = $currentThread === null ? $this->mainThreadName : (new \ReflectionClass($currentThread))->getShortName();

		$formatted = sprintf($this->format, $time, $threadName, $color, $level, $message);

		$this->writerThread->write($formatted . PHP_EOL);
	}

	public function info($message): void { $this->send("INFO", Colors::GREEN, (string) $message); }
	public function warn($message): void { $this->send("WARN", Colors::YELLOW, (string) $message); }
	public function warning($message): void { $this->send("WARN", Colors::YELLOW, (string) $message); }
	public function error($message): void { $this->send("ERROR", Colors::RED, (string) $message); }
	public function debug($message): void {
		if ($this->debugMode) $this->send("DEBUG", Colors::GRAY, (string) $message);
	}

	public function emergency($message): void { $this->send("EMERGENCY", Colors::DARK_RED, (string) $message); }
	public function alert($message): void { $this->send("ALERT", Colors::DARK_RED, (string) $message); }
	public function critical($message): void { $this->send("CRITICAL", Colors::RED, (string) $message); }
	public function notice($message): void { $this->send("NOTICE", Colors::BLUE, (string) $message); }

	public function log($level, $message): void {
		$this->info("[" . strtoupper((string)$level) . "] " . $message);
	}

	public function logException(\Throwable $e, $trace = null): void {
		$this->critical("Uncaught exception: " . $e->getMessage());
	}

	public function shutdown(): void {
		$this->writerThread->shutdown();
	}
}