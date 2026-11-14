#!/usr/bin/env bash
# Loaded by bashunit before the E2E tests, see BASHUNIT_BOOTSTRAP in .bashunitrc.
# Run the tests from the repository root: e2e/bashunit test e2e

# A test runs in the directory of its test file and gets E2E_TMPDIR, a directory of its own,
# which is also its TMPDIR: tally run keeps its work directory in sys_get_temp_dir().
function e2e::set_up() {
	E2E_TMPDIR=$(mktemp -d)
	export TMPDIR="$E2E_TMPDIR"
	cd "$(dirname "${BASH_SOURCE[1]}")" || return
}

function e2e::tear_down() {
	rm -rf "${E2E_TMPDIR:?}"
}
