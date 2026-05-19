<?php

declare(strict_types=1);

namespace aquarelay\command\utils;

final class CommandStringHelper{

	public static function parseQuoteAware(string $commandLine) : array{
		$args = [];
		preg_match_all('/"((?:\\\\.|[^\\\\"])*)"|(\S+)/u', $commandLine, $matches);

		foreach($matches[0] as $k => $_){
			for($i = 1; $i <= 2; ++$i){
				if($matches[$i][$k] !== ""){
					$match = $matches[$i][$k];

					$args[] = preg_replace('/\\\\([\\\\"])/u', '$1', $match)
						?? throw new \Error(preg_last_error_msg());

					break;
				}
			}
		}

		return $args;
	}
}