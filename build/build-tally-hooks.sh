#!/usr/bin/env bash
# Builds ext/tally_hooks for the PHP whose phpize and php-config are on PATH into the given file.
# CFLAGS pass through, -DTALLY_HOOKS_RECORDING builds the recording extension of the tests.
# Needs gcc, make, and autoconf. CI builds every PHP version in the official php Docker images.
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output="${1:?Usage: build/build-tally-hooks.sh <output.so>}"
mkdir -p "$(dirname "$output")"
output="$(cd "$(dirname "$output")" && pwd)/$(basename "$output")"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

cp "$repo_root"/ext/tally_hooks/* "$work/"
cd "$work"
if ! { phpize && ./configure --enable-tally-hooks && make -j"$(nproc)"; } >build.log 2>&1; then
	cat build.log >&2
	exit 1
fi

install -m 0644 modules/tally_hooks.so "$output"
case " ${CFLAGS:-} " in
	*TALLY_HOOKS_RECORDING*) ;;
	*) strip "$output" ;;
esac
echo "Built tally_hooks for PHP $(php-config --version) into $output"
