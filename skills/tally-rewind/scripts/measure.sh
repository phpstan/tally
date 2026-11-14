#!/usr/bin/env bash
# Measures Tally benchmark files at commits of the project's history, each in a git worktree of its
# own, with the Tally version of the main checkout, so that numbers measured at different commits
# compare.
set -euo pipefail

usage() {
	cat <<'EOF'
Usage: measure.sh [<tally run option>...] <commit>... -- <bench file or directory>...
       measure.sh [<tally run option>...] <commit> <bench file or directory>...

Copies the benchmark files or directories, with the data files in them, as they are in the working
copy when it starts, into a git worktree at each commit, installs the project's dependencies there,
and runs tally run on them. Run it anywhere inside the project's main checkout.

The commits are resolved and the files copied once, when it starts, so later edits and commits in
the working copy do not change what a running batch measures. Tally itself is installed apart from
the project, in exactly the version the working copy has installed, in vendor/ or in a tools
directory such as tools/tally/ or tools/.tally/. It runs with the working copy's tally.php, or with
the defaults when there is none.

Prints one tab-separated line per measured case:
  commit  description  date  case  instructions  cycles  memory-bytes
and appends them to $TALLY_REWIND_DIR/measurements.tsv. The full Tally report is in
$TALLY_REWIND_DIR/results/<commit>.log, its results in $TALLY_REWIND_DIR/results/<commit>/.

Options starting with -- are passed to tally run, such as --filter=RouteMatchingBench.

Environment:
  TALLY_REWIND_DIR      Where the worktrees, results, and measurements.tsv are kept.
                        Default: ${TMPDIR:-/tmp}/tally-rewind/<name of the project directory>
  TALLY_REWIND_PREPARE  A shell command run in each new worktree before its dependencies are
                        installed, such as 'cp .env.example .env'.
  COMPOSER_ROOT_VERSION Set it when the project needs it. Otherwise, for a composer.json that uses
                        self.version, it is taken from the branch alias or the nearest tag.

Dependencies are installed as the commit locks them. When that fails, it retries with platform
requirements ignored, then without the dev dependencies, and says so in the install log.

Exit codes:
  0  every commit measured, also when cases are slower or faster than their expected numbers
  1  a commit was not measurable: its dependencies did not install, a benchmark is invalid there,
     or a case threw or timed out; the output says which and where the log is
  2  invalid usage
EOF
}

tally_options=()
positional=()
separated=false
commits=()
for argument in "$@"; do
	case "$argument" in
		-h | --help) usage; exit 0 ;;
		--) separated=true; commits=(${positional[@]+"${positional[@]}"}); positional=() ;;
		--*) tally_options+=("$argument") ;;
		*) positional+=("$argument") ;;
	esac
done
if $separated; then
	paths=(${positional[@]+"${positional[@]}"})
elif ((${#positional[@]} > 0)); then
	commits=("${positional[0]}")
	paths=("${positional[@]:1}")
else
	paths=()
fi
if ((${#commits[@]} == 0 || ${#paths[@]} == 0)); then
	usage >&2
	exit 2
fi

top=$(git rev-parse --show-toplevel)
top=$(cd "$top" && pwd -P)

shas=()
for commit in "${commits[@]}"; do
	if ! sha=$(git -C "$top" rev-parse --verify --quiet "$commit^{commit}"); then
		echo "Unknown commit: $commit" >&2
		exit 2
	fi
	shas+=("$sha")
done

relative=()
for path in "${paths[@]}"; do
	if [[ -d $path ]]; then
		absolute=$(cd "$path" && pwd -P)
	elif [[ -f $path ]]; then
		absolute=$(cd "$(dirname "$path")" && pwd -P)/$(basename "$path")
	else
		echo "No such benchmark file or directory: $path" >&2
		exit 2
	fi
	case "$absolute" in
		"$top"/*) relative+=("${absolute#"$top"/}") ;;
		*) echo "The benchmark path $path is outside the project in $top." >&2; exit 2 ;;
	esac
done

# a dev version is pinned to its commit, so every commit runs the same build
mapfile -t installed_files < <(find "$top" -maxdepth 5 \( -name .git -o -name node_modules \) -prune -o -path '*/vendor/composer/installed.json' -print)
if ! tally=$(php -r '
	foreach (array_slice($argv, 1) as $file) {
		$installed = json_decode((string) file_get_contents($file), true);
		foreach ($installed["packages"] ?? [] as $package) {
			if ($package["name"] !== "phpstan/tally") {
				continue;
			}
			$version = $package["version"];
			$reference = $package["source"]["reference"] ?? $package["dist"]["reference"] ?? "";
			$dev = str_starts_with($version, "dev-") || str_ends_with($version, "-dev");
			echo $dev && $reference !== "" ? $package["version"] . "#" . $reference : $version;
			exit(0);
		}
	}
	exit(1);
' "${installed_files[@]}"); then
	echo "Tally is not installed in $top, neither in vendor/ nor in a tools directory. Set it up there first." >&2
	exit 2
fi

base=${TALLY_REWIND_DIR:-${TMPDIR:-/tmp}/tally-rewind/$(basename "$top")}
mkdir -p "$base/worktrees" "$base/results" "$base/tally"
base=$(cd "$base" && pwd -P)

# Tally apart from the project, so that no commit's dependencies have to fit it
tally_project=$base/tally/$(printf '%s' "$tally" | sha1sum | cut -c1-12)
if [[ ! -x $tally_project/vendor/bin/tally ]]; then
	mkdir -p "$tally_project"
	php -r 'echo json_encode(["require" => ["phpstan/tally" => $argv[1]], "minimum-stability" => "dev", "prefer-stable" => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";' "$tally" >"$tally_project/composer.json"
	if ! composer update --working-dir="$tally_project" --no-interaction --no-progress >"$tally_project/install.log" 2>&1; then
		echo "Cannot install phpstan/tally $tally, see $tally_project/install.log" >&2
		exit 2
	fi
fi
tally_vendor=$(cd "$tally_project/vendor" && pwd -P)

snapshot=$(mktemp -d "$base/snapshot.XXXXXX")
trap 'rm -rf "$snapshot"' EXIT
for path in "${relative[@]}"; do
	mkdir -p "$snapshot/$(dirname "$path")"
	cp -R "$top/$path" "$snapshot/$path"
done
has_config=false
if [[ -f $top/tally.php ]]; then
	has_config=true
	cp "$top/tally.php" "$snapshot/.tally-rewind-config.php"
fi

install() {
	if [[ -f composer.lock ]]; then
		composer install --no-interaction --no-progress
	else
		composer update --no-interaction --no-progress
	fi
}

prepare() {
	local worktree=$1 sha=$2
	cd "$worktree"
	if [[ -n ${TALLY_REWIND_PREPARE:-} ]]; then
		bash -c "$TALLY_REWIND_PREPARE"
	fi
	if [[ -z ${COMPOSER_ROOT_VERSION:-} ]] && grep -q '"self.version"' composer.json; then
		COMPOSER_ROOT_VERSION=$(php -r '
			$alias = json_decode((string) file_get_contents("composer.json"), true)["extra"]["branch-alias"] ?? [];
			echo is_array($alias) && $alias !== [] ? reset($alias) : "";
		')
		if [[ -z $COMPOSER_ROOT_VERSION ]]; then
			COMPOSER_ROOT_VERSION=$(git describe --tags --abbrev=0 "$sha" 2>/dev/null | sed 's/^v//')
		fi
		export COMPOSER_ROOT_VERSION
		echo "COMPOSER_ROOT_VERSION=$COMPOSER_ROOT_VERSION"
	fi
	# old commits lock packages that have advisories since
	export COMPOSER_NO_SECURITY_BLOCKING=1 COMPOSER_NO_AUDIT=1
	install && return
	echo "Retrying with platform requirements ignored."
	COMPOSER_IGNORE_PLATFORM_REQS=1 install && return
	echo "Retrying without the dev dependencies."
	php -r '
		$json = json_decode((string) file_get_contents("composer.json"), true);
		unset($json["require-dev"]);
		file_put_contents("composer.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	'
	rm -f composer.lock
	COMPOSER_IGNORE_PLATFORM_REQS=1 composer update --no-dev --no-interaction --no-progress
}

measurements=$base/measurements.tsv
if [[ ! -f $measurements ]]; then
	printf 'commit\tdescription\tdate\tcase\tinstructions\tcycles\tmemory-bytes\n' >"$measurements"
fi

# 0 measured, 1 not measurable
measure() {
	local sha=$1
	local worktree=$base/worktrees/$sha
	if [[ ! -d $worktree ]]; then
		git -C "$top" worktree add --detach --quiet "$worktree" "$sha"
	fi

	# inside vendor/, so it goes when the worktree or its dependencies go
	if [[ ! -f $worktree/vendor/.tally-rewind-installed ]]; then
		local install_log=$base/results/$sha.install.log
		if ! (prepare "$worktree" "$sha") >"$install_log" 2>&1; then
			echo "$sha: not measurable: its dependencies do not install, see $install_log" >&2
			return 1
		fi
		touch "$worktree/vendor/.tally-rewind-installed"
	fi

	for path in "${relative[@]}"; do
		rm -rf "${worktree:?}/$path"
		mkdir -p "$worktree/$(dirname "$path")"
		cp -R "$snapshot/$path" "$worktree/$path"
	done
	if $has_config; then
		cp "$snapshot/.tally-rewind-config.php" "$worktree/.tally-rewind-config.php"
	fi
	# the project's autoloader prepends itself, so Tally's own takes the lead again: a commit may
	# have another version of Tally installed
	cat >"$worktree/.tally-rewind.php" <<PHP
<?php declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

\$tally = Composer\Autoload\ClassLoader::getRegisteredLoaders()['$tally_vendor'] ?? null;
if (\$tally !== null) {
	\$tally->unregister();
	\$tally->register(true);
}

return $($has_config && echo "require __DIR__ . '/.tally-rewind-config.php'" || echo 'Tally\Configuration::create()');
PHP

	local results=$base/results/$sha
	local report=$results.log
	local status=0
	rm -rf "$results"
	(
		cd "$worktree"
		NO_COLOR=1 "$tally_vendor/bin/tally" run --config="$worktree/.tally-rewind.php" --output="$results" ${tally_options[@]+"${tally_options[@]}"} "${relative[@]}"
	) >"$report" 2>&1 || status=$?
	if ((status == 2)) || [[ ! -d $results ]]; then
		echo "$sha: not measurable: tally run exited with $status, see $report" >&2
		tail -n 20 "$report" >&2
		return 1
	fi

	local description date
	description=$(git -C "$top" describe --tags --always "$sha")
	date=$(git -C "$top" show -s --format=%cs "$sha")
	local measured=0
	php -r '
		$failed = false;
		$files = glob($argv[1] . "/*.json");
		sort($files);
		foreach ($files as $file) {
			$result = json_decode((string) file_get_contents($file), true);
			if (isset($result["metrics"])) {
				$metrics = $result["metrics"];
				echo implode("\t", [$argv[2], $argv[3], $argv[4], $result["id"], $metrics["instructions"], $metrics["cycles"], $metrics["memory"]]), "\n";
			} else {
				$failed = true;
				fwrite(STDERR, $result["id"] . ": not measured: " . strtok($result["failure"], "\n") . "\n");
			}
		}
		exit($failed ? 1 : 0);
	' "$results" "$sha" "$description" "$date" | tee -a "$measurements" || measured=$?
	if ((measured != 0)); then
		echo "$sha: not measurable, see $report" >&2
		return 1
	fi
}

exit_code=0
for sha in "${shas[@]}"; do
	measure "$sha" || exit_code=1
done
exit "$exit_code"
