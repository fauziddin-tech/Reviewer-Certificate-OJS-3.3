#!/usr/bin/env bash
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
plugin_dir=$(CDPATH= cd -- "$script_dir/.." && pwd)
plugin_version=$(sed -n 's:.*<release>\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)\.0</release>.*:\1:p' "$plugin_dir/version.xml")

if [ -z "$plugin_version" ]; then
	echo "Unable to read the release number from version.xml" >&2
	exit 1
fi

archive_path=${1:-"$plugin_dir/../reviewerCertificate-v${plugin_version}.tar.gz"}

tar \
	--exclude='./.git' \
	--exclude='./.github' \
	--exclude='./scripts' \
	--transform='s,^\.,reviewerCertificate,' \
	-C "$plugin_dir" \
	-czf "$archive_path" \
	.

first_entry=$(tar -tzf "$archive_path" | sed -n '1p')
case "$first_entry" in
	reviewerCertificate/*) ;;
	*)
		echo "Invalid package root: $first_entry" >&2
		exit 1
		;;
esac

echo "$archive_path"
