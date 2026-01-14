#!/usr/bin/env bash

set -e

function bump_version() {
	BASE_VERSION="$1"

	suffix="$(echo "$BASE_VERSION" | cut -d- -sf2)"
	if [ "$suffix" != "" ]; then
		major_minor_patch="$(echo "$BASE_VERSION" | cut -d- -f1)"
		suffix_type="$(echo "$suffix" | sed -n "s/^\([A-Za-z]*\)\([0-9]*\)$/\1/p")"
		suffix_number="$(echo "$suffix" | sed -n "s/^\([A-Za-z]*\)\([0-9]*\)$/\2/p")"

		if [ "$suffix_number" == "" ]; then
			# alpha -> alpha1
			new_version="$major_minor_patch-$suffix_type1"
		else
			new_release_number=$((suffix_number+1))
			new_version="$major_minor_patch-$suffix_type$new_release_number"
		fi
	else
		patch="$(echo "$BASE_VERSION" | cut -d. -sf3)"
		major_minor="$(echo "$BASE_VERSION" | cut -d. -sf1-2)"
		new_patch=$((patch+1))
		new_version="$major_minor.$new_patch"
	fi

	echo "$new_version"
}

cd "$1"
additional_info="$2"

version_regex='^(\s*public const VERSION = \")(.+)(\";)'

BASE_VERSION="$(sed -nE "s/$version_regex/\2/p" "./src/ProxyServer.php")"

if [ "$BASE_VERSION" == "" ]; then
	echo "error: VERSION not found in ProxyServer.php"
	exit 1
fi

NEW_VERSION="$(bump_version "$BASE_VERSION")"

sed -i -E "s/$version_regex/\1___replaceme___\3/" "./src/ProxyServer.php"
sed -i "s/___replaceme___/$NEW_VERSION/" "./src/ProxyServer.php"

git commit -m "Next: $NEW_VERSION" -m "$additional_info" --only "./src/ProxyServer.php"
