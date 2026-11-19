#!/usr/bin/env bash
# Builds tally_hooks for one thread safety inside an official php Docker image, as root, into the
# given file, and checks that the file loads into a PHP of that thread safety.
# From 8.6, every official image is thread safe (docker-library/php#1686), while setup-php and the
# distributions still install NTS. When the PHP of the image is not of the requested thread safety, this
# builds that PHP from the source the image ships (docker-php-source), so both builds come from the
# same, newest version.
# Usage: build/build-tally-hooks-in-php-image.sh <nts|zts> <output.so>
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
thread_safety="${1:?Usage: build/build-tally-hooks-in-php-image.sh <nts|zts> <output.so>}"
output="${2:?Usage: build/build-tally-hooks-in-php-image.sh <nts|zts> <output.so>}"
case "$thread_safety" in
	nts) zts=0 ;;
	zts) zts=1 ;;
	*)
		echo "Unknown thread safety $thread_safety, expected nts or zts." >&2
		exit 1
		;;
esac

apt-get update -qq
# shellcheck disable=SC2086 # PHPIZE_DEPS is a list of packages
apt-get install -y -qq --no-install-recommends $PHPIZE_DEPS >/dev/null

if [ "$(php -r 'echo ZEND_THREAD_SAFE ? 1 : 0;')" != "$zts" ]; then
	echo "The PHP $(php -r 'echo PHP_VERSION;') of the image is not $thread_safety, building it from its source."
	configure_zts=--disable-zts
	if [ "$zts" = 1 ]; then
		configure_zts=--enable-zts
	fi
	docker-php-source extract
	# an extension needs only the API version, the thread safety, and the debug flag to match
	(
		cd /usr/src/php
		./configure --prefix=/opt/php --disable-all --disable-cgi --disable-phpdbg "$configure_zts" >/tmp/php-build.log 2>&1 \
			&& make -j"$(nproc)" >>/tmp/php-build.log 2>&1 \
			&& make install >>/tmp/php-build.log 2>&1
	) || {
		cat /tmp/php-build.log >&2
		exit 1
	}
	export PATH="/opt/php/bin:$PATH"
fi

"$repo_root/build/build-tally-hooks.sh" "$output"

if ! php -n -d extension="$output" -r "exit(extension_loaded('tally_hooks') && (ZEND_THREAD_SAFE ? 1 : 0) === $zts ? 0 : 1);"; then
	echo "$output does not load into the $thread_safety PHP it was built for." >&2
	exit 1
fi
php -n -d extension="$output" -r 'echo "Checked that tally_hooks loads into PHP ", PHP_VERSION, ZEND_THREAD_SAFE ? " ZTS" : " NTS", " (", basename(PHP_EXTENSION_DIR), ")\n";'
