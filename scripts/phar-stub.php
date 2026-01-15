<?php

declare(strict_types=1);

namespace aquarelay\phar_stub;

use function sys_get_temp_dir;
use function mkdir;
use function is_dir;
use function tempnam;
use function copy;
use function getmypid;
use function flock;
use function fopen;
use function fwrite;
use function fflush;
use const LOCK_EX;

function prepareCacheDir() : string{
	$i = 0;
	do {
		$dir = sys_get_temp_dir() . "/AquaRelay-phar-cache.$i";
		$i++;
	} while (is_dir($dir));

	if (!@mkdir($dir)) {
		throw new \RuntimeException("Failed to create cache dir");
	}

	return $dir;
}

function lockCache(string $lockFile) : void{
	static $locks = [];
	$fp = fopen($lockFile, "wb");
	flock($fp, LOCK_EX);
	fwrite($fp, (string)getmypid());
	fflush($fp);
	$locks[] = $fp;
}

$tmpDir = prepareCacheDir();
$tmp = tempnam($tmpDir, "AR");
lockCache($tmp . ".lock");

copy(__FILE__, $tmp . ".phar");

$phar = new \Phar($tmp . ".phar");
$phar->convertToData(\Phar::TAR, \Phar::NONE);
unset($phar);
try {
	\Phar::unlinkArchive($tmp . ".phar");
} catch (\PharException $e) {
	echo "Error: " . $e->getMessage();
}

define('aquarelay\ORIGINAL_PHAR_PATH', __FILE__);
require 'phar://' . str_replace(DIRECTORY_SEPARATOR, '/', $tmp . ".tar") . '/src/AquaRelay.php';
