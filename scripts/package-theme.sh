#!/usr/bin/env bash
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
theme_dir=$(CDPATH= cd -- "$script_dir/../themes/liteScholar" && pwd)
theme_version=$(sed -n 's:.*<release>\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)\.0</release>.*:\1:p' "$theme_dir/version.xml")

if [ -z "$theme_version" ]; then
	echo "Unable to read the release number from version.xml" >&2
	exit 1
fi

archive_path=${1:-"$theme_dir/../../../liteScholar-v${theme_version}.tar.gz"}

tar \
	--exclude='./tests' \
	--transform='s,^\.,liteScholar,' \
	-C "$theme_dir" \
	-czf "$archive_path" \
	.

first_entry=$(tar -tzf "$archive_path" | sed -n '1p')
case "$first_entry" in
	liteScholar/*) ;;
	*)
		echo "Invalid package root: $first_entry" >&2
		exit 1
		;;
esac

echo "$archive_path"
