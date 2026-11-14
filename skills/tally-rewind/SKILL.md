---
name: tally-rewind
description: Revisits the performance fixes in a PHP project's git history with Tally. It finds the commits that fixed slowness, adds a Tally benchmark for each to the project (setting up Tally and its CI job when the project has none), locks its numbers at the fix, replays it on every release since to find the best numbers that code ever had, then makes the current code beat them with a wall-clock gain on a real workload. Use when asked to revisit or rewind past performance fixes, guard old speedups with benchmarks, check whether they regressed, hunt performance regressions in history, or find performance work backed by history. Not for benchmarking a single new change.
---

# Tally rewind

Past performance fixes show where a project's hot paths are and how fast that code has already been. This workflow turns each fix into a Tally benchmark, finds the best numbers the code reached between the fix and today, and then improves today's code past them.

It delivers two things to the project, each as its own commits:

- The benchmarks, committed to the project with Tally set up and running in CI, so the fixes stay guarded after this work ends.
- Improvements of today's code, each one counting only when it makes a real workload measurably faster in wall-clock time.

## Rules for every step

- Every number you compare must come from the same machine, PHP build, and Tally version, because Tally's counts depend on all three. `scripts/measure.sh` in this skill's directory keeps the Tally version and configuration the same at every commit.
- Tally runs on Linux, x86_64 or aarch64, with PHP 8.2 or later and the POSIX extension. On another OS, do all the work in a Linux container with the newest PHP the project supports.
- Work on the line where new development lands, which is not always the default branch. Replay points and best numbers come only from that line's first-parent history. A fix made on another branch counts where it arrives on this line.
- Keep notes in `$TALLY_REWIND_DIR/notes.md`, by default under `${TMPDIR:-/tmp}/tally-rewind/<project directory name>/`: the period, the workload and its profile, the fixes and their commits, the bench files, the replay points, the best numbers, and the decisions you made. `measure.sh` appends every measurement to `measurements.tsv` in the same directory. Update the notes after each phase; after a context reset, read both files and continue from there.

## How to measure

`bash scripts/measure.sh <commit>... -- <bench file or directory>...` copies the bench files as they are in the working copy, with the data files of a directory, into a git worktree at each commit. It installs the project's dependencies there and runs Tally, in the working copy's version and with its `tally.php`, and prints one line per case with instructions, cycles, and memory in bytes. The commits and files are fixed when it starts, so you can keep editing while a batch runs. Options such as `--filter=<text>` go to `tally run`. Exit code 1 means a commit could not be measured, and the output says why. Set `TALLY_REWIND_PREPARE` to a command that makes old commits installable or bootable, such as `cp .env.example .env`. Run `measure.sh --help` for the rest.

Differences within Tally's tolerance are noise: ±2 % instructions, ±5 % cycles but at least 2,000 cycles, ±5 % memory, unless the project's `tally.php` sets another tolerance. Cycles move by a few percent when unrelated code changes, while the instructions do not.

The simulation runs PHP 50 to 200 times slower than native. Run each case only at the commits that contain its fix, with `--filter`, and estimate the total time from the first measurements before starting a long batch.

## 1. Ask for the period

Ask the user how far back to look, 12 months by default, unless they already said.

## 2. Profile a real workload

Pick the project's own main use, such as the command it ships run on a real input, a replayed request, or a script that uses the library on real data. Ask the user when the project has no obvious one. Profile it with the profiler the project uses, or one that is installed, such as Excimer, SPX, or Xdebug, and note which code paths take what share of the time. Load the profiler only for the profiling run, such as with `php -d extension=excimer.so`, because a loaded profiler or coverage extension slows down every other run. A fix on a path that takes 3 % of the workload can save at most 3 %.

## 3. Find the performance fixes

Read the subject of every non-merge commit of the period that reached the line, and the body of each one that could be about speed. Fixes are often worded as what the code does now, such as "Compute token offsets once", so a keyword search alone misses them. With more than a few hundred commits, start from a search and widen it:

```bash
git log --no-merges --since='12 months ago' -i -E --grep='perf|speed|fast|slow|optimi[sz]|quadratic|O\(n|once|cache|memory|allocat|rescan|hot path|bottleneck' --format='%h %cs %s'
```

Also read the changelog entries of the period, and when `gh` works, merged pull requests labeled or titled as performance work; their descriptions often have the input and the numbers.

Read the diff of each candidate. Keep it when production code does less work for the same result: a better algorithm, work moved out of a loop, a cache, fewer allocations, queries, or file reads. Drop speedups of tests, CI, builds, or dev tooling, and fixes that were later reverted. "After" is the fix commit and "before" its parent; when a fix spans several commits, "before" is the parent of the first.

When the period has no fixes, tell the user and offer to look further back. Otherwise list the fixes for the user, grouped by code path, each with its commit, date, what got faster, and that path's share of the profile. Ask which ones to pursue, by default the ones on the paths with the largest share.

## 4. Set up Tally in the project

Do the parts the project does not have yet. `measure.sh` needs Tally installed in the main checkout.

- **Install.** When the lowest PHP the project supports, in `require.php` and `config.platform.php` of `composer.json`, is 8.2 or later, run `composer require --dev phpstan/tally`. Otherwise requiring Tally would break the project's installs on its older PHP versions: install it as its own Composer project in the project's tools directory, like the other tools it keeps that way, commit its `composer.lock`, and ignore its `vendor/`. Until Tally has a tagged release, require `dev-release/1.x` with `"minimum-stability": "dev"`, because `1.x-dev` has no builds of Valgrind.
- **Configure.** Add `tally.php` at the project root, returning `Tally\Configuration::create()->withPaths([__DIR__ . '/benchmarks'])`. When Tally has its own Composer project, its autoloader knows only Tally: start `tally.php` with `require_once __DIR__ . '/vendor/autoload.php';`, which loads the project's own autoloader.
- **Package.** When `.gitattributes` export-ignores the tests, export-ignore `benchmarks` and `tally.php` too, and Tally's tools directory if it is not already.
- **Command.** Add a command to run the benchmarks next to the project's other check commands, in the same form, and one line about it where the project documents its checks: CONTRIBUTING.md, AGENTS.md, CLAUDE.md, or the README. Say there to take expected numbers from CI.
- **Checks.** Make the project's lint, coding standard, and static analysis cover `benchmarks/` like the tests, unless they run on a PHP older than 8.2 or cannot see Tally's classes; then leave `benchmarks/` out of them.
- **CI.** When the project has `.github/workflows`, add `.github/workflows/benchmarks.yml`, which runs `tally run --output=tmp/tally-results` and uploads `tmp/tally-results` as the artifact `tally-results` with `if: always()`. Run it on a fixed image such as `ubuntu-24.04`, never `ubuntu-latest`, with one fixed PHP version, the newest the project's CI tests, with the `posix` extension, because a new image or PHP release changes every count. Copy the conventions of the project's existing workflows: triggers, `permissions`, `concurrency`, hardening steps, and the same actions at the same pinned versions. Run the workflow linters the project runs. On another CI service, add the same job there. Without any CI, tell the user that the benchmarks guard nothing until something runs them.

## 5. Write a benchmark for each fix and lock it at the fix

Write the bench files in `benchmarks/`, one per code path, named after the behavior, such as `benchmarks/RouteMatchingBench.php`. One file can cover several fixes of the same path. The working-copy file is the master copy; `measure.sh` copies it to each commit.

- Call the fixed code through the most stable public entry point that reaches it. The same file must run unchanged at every commit from the fix to today, so private methods and internal classes are the wrong target.
- The project's autoloader at an old commit does not know classes you add, so put helper classes in the bench file, next to the benchmark class. Keep large inputs as PHP files returning arrays in `benchmarks/data/`, each with a comment naming its source and license, and pass `measure.sh` the `benchmarks` directory so they go along.
- Use the workload the fix was for, at the size real users have, with real data such as a real config, file, or request from the commit, pull request, or issue. When those name only a synthetic input, find a real file that takes the same code path, for example with `gh search code`, because a benchmark of a made-up input guards a case nobody has.
- Add `#[Warmup(1)]` so autoloading and first-call caches stay out of the count, unless the fix was about the first call.

Measure "before" and "after" without expected numbers. The fix must show up: "after" counts fewer instructions, cycles, or memory than "before" by more than the tolerance. When it does not, the benchmark misses the fixed path or the input does not trigger the problem; change the benchmark and measure again. A "fix" that makes its own input slower is a finding to report. Drop a fix when you cannot make its benchmark show it. The numbers at "after" are the locked numbers; record them in the notes.

## 6. Replay on every release since the fix

The replay points are the tags on the line's first-parent history after the commit where the fix arrived, oldest first, plus the commit `HEAD` pointed to when you started:

```bash
git log --first-parent --reverse --decorate-refs=refs/tags/ --format='%h %cs %D' <arrival>..HEAD | grep 'tag:'
```

With fewer than 6 tags, add the last first-parent commit of each month: `git rev-list -1 --first-parent --before=<YYYY-MM-01> HEAD`. With more than 30 points, keep 30 evenly spaced, always keeping the first and the last. Run `measure.sh` on the points with the locked files. Separate `measure.sh` runs on different commits can run in parallel.

When a file stops running because the entry point changed, adapt the master copy to the new API. Measure the previous point again with it when it runs there, and note where the benchmark changed, because numbers on the two sides of a change compare less exactly. When the fixed code was removed or rewritten, so that the benchmark no longer measures it, stop replaying this fix and note why. Skip a point that cannot be measured and note why. Do not switch PHP versions between points.

## 7. Take the best numbers as the target

For each case and metric, the best number is the lowest one measured at the fix or at any replay point, ignoring differences within the tolerance. Note it and the commit that measured it.

When the start commit is slower than the best, find the commit that made it slower. Binary-search the first-parent commits between the last point at the best numbers and the next point with `measure.sh`, as `git bisect` would, skipping a commit that cannot be measured as `git bisect skip` does, and read the commit you find. If it slowed the code down on purpose, for correctness or a bug fix, note that and keep its behavior.

## 8. Make today's code beat the best numbers

1. Improve the code paths the fixes touched, as they are today, including what the slowing commits changed, starting with the paths that take the largest share of the profile. Run the project's tests after every change. Do not special-case the benchmark's input.
2. Commit each attempt and measure it with `measure.sh`, next to the start commit. Judge it by cycles, the metric closest to time: fewer instructions with more cache misses can be slower. Local counts repeat closely, so a drop smaller than the tolerance can still be real; the wall clock decides. Memory must not grow in a way the workload notices.
3. Before timing, estimate the most a change can save on the workload: its cycle drop times the path's share of the profile. When that is smaller than the noise of the timing, stack it with other changes on the workload's paths and judge them together.
4. Time the workload in wall-clock time before and after, with the same dependencies on both sides, such as in the worktree of the start commit that `measure.sh` made, before and after applying the change. Run at least 20 pairs, alternating before and after, pinned to one CPU with `taskset -c`. Report the mean ± standard deviation of both. Keep a change only when the 95 % confidence interval of the difference of the means excludes zero. A change that only moves Tally's numbers did not have real-world impact. Leave rejected changes out of the project's branch and list them in the report with their numbers.

## 9. Commit and hand over

The expected numbers in the project must come from its CI job, because numbers measured on this machine do not fit CI's environment. Use the numbers of this machine only for the comparisons above and the report.

1. Commit the Tally setup and the bench files first, on top of the start commit and before any improvement, without `#[Expected]` and `#[ExpectedFor]` attributes. Without CI, run `tally run --output=tmp/tally-results` and `tally accept tmp/tally-results` in the working copy to put this machine's numbers in, and say so in the commit message.
2. Commit each kept improvement separately, with its benchmark numbers and wall-clock result in the commit message, so it can be reviewed and reverted on its own.
3. Ask the user before pushing. Once they push, CI prints a patch with its numbers for the new cases. Download its results with `gh run download <run id> -p 'tally-*' -D tmp/tally` and run `tally accept tmp/tally`. Put the numbers of today's code into the setup commit and those of each improvement into its commit, from runs on those commits; an improvement's run that reports its case as faster is the proof in CI's environment.
4. Report each fix: its commit and what it sped up, the case, and a table of instructions, cycles, and memory at "before", at the fix, at each replay point, at the start commit, and after your change. Then say where the best numbers came from, what slowed the code down since and why, and the wall-clock result on the real workload as mean ± standard deviation, before and after. List the fixes and changes you dropped and why, and what you set up in the project.
5. Remove the worktrees: `git worktree remove --force` each one under `$TALLY_REWIND_DIR/worktrees/`, then `git worktree prune`.
