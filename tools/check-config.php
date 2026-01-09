<?php

declare(strict_types=1);

namespace aquarelay\tools;

/**
 * AquaRelay config validator
 * Checks if the local config.yml matches the required structure of the resource template.
 */

class Log {
	public static function info(string $m) { echo "\033[32m[INFO]\033[0m $m\n"; }
	public static function error(string $m) { echo "\033[31m[ERROR]\033[0m $m\n"; }
	public static function warn(string $m) { echo "\033[33m[WARN]\033[0m $m\n"; }
}

$projectRoot = dirname(__DIR__);
$localConfigPath = $projectRoot . "/config.yml";
$templatePath = $projectRoot . "/resources/config.yml";


if (!file_exists($templatePath)) {
	Log::error("Template config not found at $templatePath");
	exit(1);
}

if (!file_exists($localConfigPath)) {
	Log::warn("Local config.yml not found. Checking template only...");
	$localConfig = [];
} else {
	if (function_exists('yaml_parse_file')) {
		$localConfig = yaml_parse_file($localConfigPath);
		$templateConfig = yaml_parse_file($templatePath);
	} else {
		Log::warn("PHP yaml extension not found. Using primitive string matching.");
		$templateContent = file_get_contents($templatePath);
		$localContent = file_exists($localConfigPath) ? file_get_contents($localConfigPath) : "";

		preg_match_all('/^([a-zA-Z0-9_-]+):/m', $templateContent, $matches);
		$requiredKeys = $matches[1];

		$missing = [];
		foreach ($requiredKeys as $key) {
			if (!str_contains($localContent, $key . ":")) {
				$missing[] = $key;
			}
		}

		if (empty($missing)) {
			Log::info("Config looks valid (all top-level keys present).");
		} else {
			Log::error("Missing keys in config.yml: " . implode(", ", $missing));
			exit(1);
		}
		exit(0);
	}
}

function array_diff_key_recursive($array1, $array2) {
	$diff = array_diff_key($array1, $array2);
	foreach ($array1 as $key => $value) {
		if (is_array($value) && isset($array2[$key]) && is_array($array2[$key])) {
			$d = array_diff_key_recursive($value, $array2[$key]);
			if (!empty($d)) $diff[$key] = $d;
		}
	}
	return $diff;
}

$missing = array_diff_key_recursive($templateConfig, $localConfig);

if (empty($missing)) {
	Log::info("Everything is perfect! Your config.yml is up to date.");
} else {
	Log::error("Your config.yml is outdated or missing sections:");
	print_r($missing);
	echo "\033[33mSuggestion: Delete your config.yml and let the proxy regenerate it.\033[0m\n";
}