#!/usr/bin/env bash
# The cases run without the extensions in E2E_EXTENSIONS, such as "xdebug pcov", however PHP's ini
# files load them. Each test loads them another way, and checks that the case does not see them
# and counts what it counts on a PHP without them. CI runs the tests once for each PHP setup.

function set_up() {
	e2e::set_up
	split_ini
}

function tear_down() {
	e2e::tear_down
}

function test_extensions_from_the_scan_directory_of_php() {
	# as the PHP setup installs them, such as conf.d/20-xdebug.ini
	assert_counts_without_the_extensions php
}

function test_extensions_from_an_ini_file_in_another_scan_directory() {
	mkdir "$E2E_TMPDIR/extra"
	cp "$E2E_TMPDIR/lines.ini" "$E2E_TMPDIR/extra/99-profilers.ini"
	assert_counts_without_the_extensions env PHPRC="$E2E_TMPDIR/without" PHP_INI_SCAN_DIR="$E2E_TMPDIR/without/conf.d:$E2E_TMPDIR/extra" php
}

function test_extensions_from_the_php_ini_in_phprc() {
	assert_counts_without_the_extensions env PHPRC="$E2E_TMPDIR/with" PHP_INI_SCAN_DIR="$E2E_TMPDIR/without/conf.d" php
}

function test_extensions_from_the_php_ini_given_with_c() {
	# tally run gives the workers neither its -c nor its -d options, so without stripping they would
	# not get the extensions either: this one checks only that they do not reach the case
	assert_counts_without_the_extensions env PHP_INI_SCAN_DIR="$E2E_TMPDIR/without/conf.d" php -c "$E2E_TMPDIR/with/php.ini"
}

function test_the_case_sees_the_extensions_the_configuration_keeps() {
	# the probe of the other tests would pass for the wrong reason if it did not see them
	tally_run php -- --config=kept.php && code=0 || code=$?

	assert_same "1" "$code"
	assert_file_contains "$E2E_TMPDIR/output.txt" "RuntimeException: The case runs with ${E2E_EXTENSIONS// /, }."
}

function test_refuses_to_count_with_extensions_that_no_ini_file_loads() {
	# loaded with -d, which tally run cannot comment out
	php -r 'echo "<?php return Tally\\Configuration::create()->withPaths([", var_export($argv[1], true), "])->withIniEntries(", var_export(array_map("trim", file($argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)), true), ");\n";' \
		"$PWD/bench" "$E2E_TMPDIR/lines.ini" > "$E2E_TMPDIR/tally.php"

	tally_run "${WITHOUT[@]}" -- --config="$E2E_TMPDIR/tally.php" && code=0 || code=$?

	assert_same "1" "$code"
	assert_file_contains "$E2E_TMPDIR/output.txt" "PHP loads extensions that the cases run without: ${E2E_EXTENSIONS// /, }."
}

# Splits PHP's ini files into $E2E_TMPDIR/without, the php.ini and the scanned files in conf.d
# without the lines that load the extensions, and $E2E_TMPDIR/lines.ini, those lines.
# $E2E_TMPDIR/with/php.ini is the php.ini with them, and WITHOUT the PHP command without them.
function split_ini() {
	local loaded file
	mkdir -p "$E2E_TMPDIR/without/conf.d" "$E2E_TMPDIR/with"
	touch "$E2E_TMPDIR/without/php.ini" "$E2E_TMPDIR/lines.ini"
	loaded=$(php -r 'echo (string) php_ini_loaded_file();')
	if [ -n "$loaded" ]; then
		split_file "$loaded" "$E2E_TMPDIR/without/php.ini"
	fi
	while IFS= read -r file; do
		split_file "$file" "$E2E_TMPDIR/without/conf.d/$(basename "$file")"
	done < <(php -r 'foreach (explode(",", (string) php_ini_scanned_files()) as $file) { if (trim($file) !== "") { echo trim($file), "\n"; } }')
	cat "$E2E_TMPDIR/without/php.ini" "$E2E_TMPDIR/lines.ini" > "$E2E_TMPDIR/with/php.ini"
	WITHOUT=(env PHPRC="$E2E_TMPDIR/without" PHP_INI_SCAN_DIR="$E2E_TMPDIR/without/conf.d" php)
}

function split_file() {
	local pattern="^[[:space:]]*(zend_)?extension[[:space:]]*=.*(${E2E_EXTENSIONS// /|})"
	grep -Eiv "$pattern" "$1" > "$2" || true
	grep -Ei "$pattern" "$1" >> "$E2E_TMPDIR/lines.ini" || true
}

# Checks the case with the PHP command given, such as env PHPRC=dir php: tally run counts it
# without the extensions, which the command loads, and like a PHP without them.
function assert_counts_without_the_extensions() {
	local code counted without
	assert_same "$E2E_EXTENSIONS" "$(loaded_extensions "$@")"
	assert_same "" "$(loaded_extensions "${WITHOUT[@]}")"

	tally_run "$@" -- && code=0 || code=$?
	assert_same "0" "$code" || cat "$E2E_TMPDIR/output.txt"
	counted=$(instructions)
	tally_run "${WITHOUT[@]}" -- && code=0 || code=$?
	assert_same "0" "$code" || cat "$E2E_TMPDIR/output.txt"
	without=$(instructions)

	# The php.ini of the workers keeps every other setting, a lost one would move the counts. The
	# command lines and the environments of the workers differ in length, which can move a few.
	assert_within_delta "$without" "$counted" "$((without / 1000))"
}

function loaded_extensions() {
	"$@" -r 'echo implode(" ", array_intersect(explode(" ", $argv[1]), get_loaded_extensions()));' "$E2E_EXTENSIONS"
}

# Runs tally run on the probe with the PHP command before --, and the options after it. The output
# goes to $E2E_TMPDIR/output.txt, the results to $E2E_TMPDIR/results.
function tally_run() {
	local php=()
	while [ "$1" != "--" ]; do
		php+=("$1")
		shift
	done
	shift
	rm -rf "$E2E_TMPDIR/results"
	# outside GitHub Actions, where tally run would write annotations and the job summary
	env -u GITHUB_ACTIONS "${php[@]}" ../../bin/tally run --output="$E2E_TMPDIR/results" "$@" > "$E2E_TMPDIR/output.txt" 2>&1
}

function instructions() {
	php -r 'echo json_decode(file_get_contents($argv[1]), true)["metrics"]["instructions"];' "$E2E_TMPDIR"/results/*.json
}
