#!/usr/bin/env bash
set -ex

bump_version() {
	local v="$1"

	if [[ "$v" == *"-"* ]]; then
		base="${v%%-*}"
		suffix="${v##*-}"

		if [[ "$suffix" =~ ^([a-zA-Z]+)([0-9]*)$ ]]; then
			type="${BASH_REMATCH[1]}"
			num="${BASH_REMATCH[2]}"

			if [[ -z "$num" ]]; then
				echo "$base-${type}1"
			else
				echo "$base-$type$((num+1))"
			fi
		else
			echo "error: unknown suffix: $suffix"
			exit 1
		fi
	else
		IFS='.' read -r a b c <<< "$v"
		echo "$a.$b.$((c+1))"
	fi
}

cd "$1"
additional_info="$2"

BASE_VERSION="$(sed -nE 's/.*public const VERSION = "([^"]+)".*/\1/p' ./src/ProxyServer.php)"

echo "BASE_VERSION=$BASE_VERSION"

NEW_VERSION="$(bump_version "$BASE_VERSION")"

sed -i -E "s|public const VERSION = \"[^\"]+\"|public const VERSION = \"$NEW_VERSION\"|" ./src/ProxyServer.php

git commit -m "Next: $NEW_VERSION" -m "$additional_info" --only ./src/ProxyServer.php
