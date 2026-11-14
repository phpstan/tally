#!/usr/bin/env bash
# Builds what tally run counts with into codspeed/linux-<machine>: CodSpeed's Valgrind fork with
# only Callgrind and a static launcher. build/build-tally-hooks.sh builds the extension that tells
# it what to count next to it. The release branch commits both, so a project that requires Tally
# needs no setup.
# Needs git, gcc, make, and autotools. Builds for the machine it runs on.
set -euo pipefail

# The Valgrind of CodSpeed's 5.6.3 CLI (codspeed setup --mode=simulation installs it)
VALGRIND_REPOSITORY=https://github.com/CodSpeedHQ/valgrind-codspeed.git
VALGRIND_TAG=3.26.0-0codspeed13
VALGRIND_COMMIT=346abce500c33cb5e1b43fafb5520929bed8497e

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
machine="$(uname -m)"
output="$repo_root/codspeed/linux-$machine"
work="$repo_root/tmp/codspeed-build"

if [ "${1:-}" = "--print-key" ]; then
	# what the build depends on, for caching it in CI
	echo "$machine-$VALGRIND_COMMIT-$(sha256sum "${BASH_SOURCE[0]}" | cut -c1-16)"
	exit 0
fi

fetch() {
	rm -rf "$2"
	git init -q "$2"
	git -C "$2" fetch -q --depth=1 "$1" "$3"
	git -C "$2" -c advice.detachedHead=false checkout -q FETCH_HEAD
}

mkdir -p "$work"

fetch "$VALGRIND_REPOSITORY" "$work/valgrind" "$VALGRIND_COMMIT"
cd "$work/valgrind"
# Callgrind estimates cycles with the vendored Capstone
git submodule update -q --init --depth=1 third_party/capstone
./autogen.sh >/dev/null
# 64-bit only, as CodSpeed builds it: cycle estimation needs a 64-bit Capstone
./configure -q --enable-only64bit
jobs="$(nproc)"
# only what Callgrind needs, not the other tools
make -s include/vgversion.h default.supp >/dev/null
for directory in third_party include VEX coregrind callgrind; do
	make -s -j"$jobs" -C "$directory" >/dev/null
done
# A static launcher runs with any C library; the tool is static already.
rm coregrind/valgrind
make -s -C coregrind valgrind LDFLAGS=-static >/dev/null

# keeps the builds of tally_hooks
rm -rf "$output/libexec" "$output/valgrind"
mkdir -p "$output/libexec"
install -m 0755 coregrind/valgrind "$output/valgrind"
# the launcher starts <tool>-<platform> from VALGRIND_LIB, the tool preloads vgpreload_core
install -m 0755 callgrind/callgrind-*-linux "$output/libexec/"
install -m 0644 coregrind/vgpreload_core-*-linux.so default.supp "$output/libexec/"
strip "$output/valgrind" "$output/libexec/callgrind-"*-linux "$output/libexec/vgpreload_core-"*-linux.so
# the launcher starts a tool even for --version, from VALGRIND_LIB, not where it would be installed
VALGRIND_LIB="$output/libexec" "$output/valgrind" --tool=callgrind --version >"$output/VERSION"

# what the licenses ask of a binary distribution: their texts, and where the source is
install -m 0644 COPYING "$output/LICENSE-valgrind"
install -m 0644 third_party/capstone/LICENSE.TXT "$output/LICENSE-capstone"
install -m 0644 third_party/capstone/LICENSE_LLVM.TXT "$output/LICENSE-capstone-llvm"
cat >"$output/SOURCE" <<EOF
Built by build/build-codspeed.sh of https://github.com/phpstan/tally from this source:

CodSpeed's Valgrind, GPL-3.0-or-later (LICENSE-valgrind), with Capstone, BSD-3-Clause and
NCSA (LICENSE-capstone, LICENSE-capstone-llvm), in valgrind and libexec/:
$VALGRIND_REPOSITORY $VALGRIND_TAG
https://github.com/CodSpeedHQ/valgrind-codspeed/tree/$VALGRIND_COMMIT
https://github.com/capstone-engine/capstone/tree/$(git -C third_party/capstone rev-parse HEAD)

Tally's tally_hooks extension, MIT, with Valgrind's valgrind.h and callgrind.h, BSD-style, in
tally_hooks-<PHP version>[-zts].so: ext/tally_hooks of the same commit of Tally.
EOF

cd "$repo_root"
rm -rf "$work"
echo "Built $(cat "$output/VERSION") into $output"
