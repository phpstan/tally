#!/usr/bin/env bash
# Tags a release on release/1.x, which CI force-pushes after every push to 1.x: the tag goes on the
# generated commit only when it sits directly on the 1.x commit to release, so it ships that code
# with its builds. Pushes nothing; prints the command that does.
# Usage: build/tag-release.sh <version> [<1.x commit>, origin/1.x by default]
set -euo pipefail

version="${1:?Usage: build/tag-release.sh <version> [<1.x commit>]}"
git fetch -q origin 1.x release/1.x
source_commit="$(git rev-parse "${2:-origin/1.x}^{commit}")"
release_commit="$(git rev-parse origin/release/1.x)"

if [ "$(git rev-parse "$release_commit^")" != "$source_commit" ]; then
	echo "origin/release/1.x is not built from $source_commit yet: wait for CI on 1.x to push it." >&2
	exit 1
fi
if ! git log -1 --format=%s "$release_commit" | grep -q '^\[GENERATED\]'; then
	echo "origin/release/1.x does not end with the generated commit of the builds." >&2
	exit 1
fi

git tag -a "$version" -m "$version" "$release_commit"
echo "Tagged $version on $release_commit, built from $source_commit. Push it with:"
echo "  git push origin $version"
