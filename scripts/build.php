<?php

declare(strict_types=1);

namespace aquarelay\build\server_phar;

use Symfony\Component\Filesystem\Path;

require dirname(__DIR__) . '/vendor/autoload.php';

function preg_quote_array(array $strings, string $delim) : array{
	return array_map(fn(string $str) => preg_quote($str, $delim), $strings);
}

function buildPhar(
	string $pharPath,
	string $basePath,
	array $includedPaths,
	array $metadata,
	string $stub,
	int $signatureAlgo = \Phar::SHA1,
	?int $compression = null
){
	$basePath = rtrim(str_replace("/", DIRECTORY_SEPARATOR, $basePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	$includedPaths = array_map(
		fn(string $p) => rtrim(str_replace("/", DIRECTORY_SEPARATOR, $p), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
		$includedPaths
	);

	echo "Creating AquaRelay phar: $pharPath\n";

	if (file_exists($pharPath)) {
		try {
			\Phar::unlinkArchive($pharPath);
		} catch (\Throwable) {
			unlink($pharPath);
		}
	}

	$phar = new \Phar($pharPath);
	$phar->setMetadata($metadata);
	$phar->setStub($stub);
	$phar->setSignatureAlgorithm($signatureAlgo);
	$phar->startBuffering();

	$excludedSubstrings = preg_quote_array([
		realpath($pharPath),
	], '/');

	$folderPatterns = preg_quote_array([
		DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
		DIRECTORY_SEPARATOR . '.'
	], '/');

	$basePattern = preg_quote(rtrim($basePath, DIRECTORY_SEPARATOR), '/');
	foreach ($folderPatterns as $p) {
		$excludedSubstrings[] = $basePattern . '.*' . $p;
	}

	$regex = sprintf(
		'/^(?!.*(%s))^%s(%s).*/i',
		implode('|', $excludedSubstrings),
		preg_quote($basePath, '/'),
		implode('|', preg_quote_array($includedPaths, '/'))
	);

	$it = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator(
			$basePath,
			\FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS | \FilesystemIterator::CURRENT_AS_PATHNAME
		)
	);

	$count = count($phar->buildFromIterator(new \RegexIterator($it, $regex), $basePath));
	echo "Added $count files\n";

	if ($compression !== null) {
		$phar->compressFiles($compression);
	}

	$phar->stopBuffering();
	echo "AquaRelay build completed\n";
}

function main() : void{
	if (ini_get("phar.readonly") === "1") {
		echo "Run with -dphar.readonly=0\n";
		exit(1);
	}

	$opts = getopt("", ["out:", "build:"]);
	$build = isset($opts["build"]) ? (int)$opts["build"] : 0;

	$out = $opts["out"] ?? (getcwd() . DIRECTORY_SEPARATOR . "AquaRelay.phar");

	buildPhar(
		$out,
		dirname(__DIR__) . DIRECTORY_SEPARATOR,
		["src", "resources", "vendor"],
		[
			"name"  => "AquaRelay",
			"build" => $build
		],
		file_get_contents(Path::join(__DIR__, 'phar-stub.php')) . "\n__HALT_COMPILER();",
		\Phar::SHA1,
		\Phar::GZ
	);
}

main();
