#!/usr/bin/env bash
# Checks a release tree the way a project gets it: Composer installs the archive GitHub would serve
# for the commit (git archive, which leaves out the export-ignore paths) into a new project, and
# tally run counts a benchmark there with nothing else set up.
# Usage: build/check-release.sh <commit of the release tree>
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
commit="${1:?Usage: build/check-release.sh <commit>}"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# Composer reads the version of an artifact from its composer.json
mkdir -p "$work/package" "$work/artifacts" "$work/project/bench"
git -C "$repo_root" archive "$commit" | tar -x -C "$work/package"
COMPOSER_ROOT_VERSION=1.0.0 composer config --working-dir="$work/package" version 1.0.0
(cd "$work/package" && zip -q -r -y "$work/artifacts/tally.zip" .)

cat >"$work/project/composer.json" <<JSON
{
	"repositories": [{"type": "artifact", "url": "$work/artifacts"}],
	"require-dev": {"phpstan/tally": "1.0.0"}
}
JSON
cat >"$work/project/bench/SortBench.php" <<'PHP'
<?php declare(strict_types = 1);

final class SortBench
{

	public function benchSortHundred(): void
	{
		$numbers = range(100, 1, -1);
		sort($numbers);
	}

}
PHP

cd "$work/project"
composer install --no-interaction --no-progress --quiet
# the case has no expectations yet, which passes as new
vendor/bin/tally run --output=results bench

instructions="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["metrics"]["instructions"] ?? 0;' results/SortBench-benchSortHundred-*.json)"
if [ "$instructions" -le 0 ]; then
	echo "tally run counted no instructions." >&2
	cat results/*.json >&2
	exit 1
fi
echo "The release tree counted SortBench::benchSortHundred: $instructions instructions."
