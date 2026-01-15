<?php

declare(strict_types=1);

namespace aquarelay;

use function define;
use function defined;
use function dirname;

if (defined('aquarelay\_CORE_CONSTANTS_INCLUDED')) {
	return;
}

define('aquarelay\_CORE_CONSTANTS_INCLUDED', true);
define('aquarelay\PATH', dirname(__DIR__) . '/');
define('aquarelay\RESOURCE_PATH', dirname(__DIR__) . '/resources/');
define('aquarelay\DATA_PATH', getcwd() . '/');