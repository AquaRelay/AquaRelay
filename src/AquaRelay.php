<?php

declare(strict_types=1);

namespace aquarelay {

    use aquarelay\config\ProxyConfig;
    use aquarelay\utils\Colors;
    use Throwable;

    require dirname(__DIR__) . '/vendor/autoload.php';

    if (Colors::supportsColors()){
        @sapi_windows_vt100_support(STDOUT, true);
    }

    ini_set("display_errors", "1");
    error_reporting(E_ALL);
    date_default_timezone_set("UTC");

    define("BASE_PATH", dirname(__DIR__));
    define("RESOURCE_PATH", BASE_PATH . "/resources");
    define("CONFIG_FILE", RESOURCE_PATH . "/config.yml");

    if(!extension_loaded("yaml")){
        echo "Unable to find the yaml extension.";
        exit(1);
    }

    try {
       $server = new ProxyServer(ProxyConfig::load(CONFIG_FILE));
       $server->start();
    } catch(Throwable $e) {
        echo "Error: " . $e->getMessage();
        exit(1);
    }

}