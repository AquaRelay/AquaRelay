<?php

declare(strict_types=1);

namespace aquarelay\utils;

class Colors {

    public const RESET = "\033[0m";
    public const BLACK = "\033[30m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const PURPLE = "\033[35m";
    public const CYAN = "\033[36m";
    public const WHITE = "\033[37m";
    public const GRAY = "\033[90m";
    public const DARK_RED = "\033[91m";
    public const DARK_GREEN = "\033[92m";
    public const DARK_YELLOW = "\033[93m";
    public const DARK_BLUE = "\033[94m";
    public const MATERIAL_GOLD = "\033[38;5;220m";
    public const BOLD = "\033[1m";
    public const ITALIC = "\033[3m";
    public const UNDERLINE = "\033[4m";

    public static function clean(string $text) : string{
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    public static function supportsColors() : bool{
        if(PHP_OS_FAMILY === "Windows"){
            return function_exists("sapi_windows_vt100_support")
                && sapi_windows_vt100_support(STDOUT);
        }
        return true;
    }
}
