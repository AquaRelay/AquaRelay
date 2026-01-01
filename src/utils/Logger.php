<?php

declare(strict_types=1);

namespace aquarelay\utils;

final class Logger {

    // TODO: We need pmmp logger system for better logs

    private string $format =
        Colors::BLUE . "%s " .
        Colors::RESET . "[" . Colors::MATERIAL_GOLD . "%s" . Colors::RESET . "] " .
        "[" . "%s%s" . Colors::RESET . "]" .
        Colors::WHITE . " %s" .
        Colors::RESET;

    public function __construct(private string $name, private bool $debug = false){}

    private function log(string $level, string $color, string $message) : void{
        $time = date("H:i:s");

        echo sprintf(
                $this->format,
                $time,
                $this->name,
                $color,
                $level,
                $message
            ) . PHP_EOL;
    }

    public function info(string $message) : void{
        $this->log("INFO", Colors::GREEN, $message);
    }

    public function warn(string $message) : void{
        $this->log("WARN", Colors::YELLOW, $message);
    }

    public function error(string $message) : void{
        $this->log("ERROR", Colors::RED, $message);
    }

    public function alert(string $message) : void{
        $this->log("ALERT", Colors::DARK_RED, $message);
    }

    public function debug(string $message) : void{
        if(!$this->debug){
            return;
        }
        $this->log("DEBUG", Colors::GRAY, $message);
    }
}
