#!/usr/bin/env bash
# Turns the 1.x source tree into the release/1.x tree: the same code with the builds of CodSpeed's
# Valgrind (build/build-codspeed.sh) and of the tally_hooks extension for PHP 8.2 to 8.6, NTS and
# ZTS (build/build-tally-hooks.sh) in codspeed/, made on each platform.
# Releases are tagged on release/1.x, so a project that requires Tally needs no CodSpeed setup.
# With --commit, the builds become a commit, as on the release branch.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

commit=false
if [ "${1:-}" = "--commit" ]; then
	commit=true
fi

for platform in linux-x86_64 linux-aarch64; do
	if [ ! -f "codspeed/$platform/valgrind" ]; then
		echo "codspeed/$platform is missing: build it with build/build-codspeed.sh on that platform." >&2
		exit 1
	fi
	for version in 8.2 8.3 8.4 8.5 8.6; do
		for build in "$version" "$version-zts"; do
			if [ ! -f "codspeed/$platform/tally_hooks-$build.so" ]; then
				echo "codspeed/$platform/tally_hooks-$build.so is missing: build it with build/build-tally-hooks.sh for that PHP." >&2
				exit 1
			fi
		done
	done
	# artifacts of GitHub Actions lose them
	chmod 0755 "codspeed/$platform/valgrind" "codspeed/$platform/libexec/"*-linux
done

# the source tree ignores the builds, the release tree has them
sed -i '\#^/codspeed$#d' .gitignore

if $commit; then
	git add .gitignore codspeed
	git commit -q -m "[GENERATED] CodSpeed's Valgrind and tally_hooks for linux-x86_64 and linux-aarch64"
fi
