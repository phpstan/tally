# Tally

Benchmarks PHP code by counting instructions, CPU cycles, and memory instead of measuring time.

```php
final class SortBench
{

	#[Expected(instructions: '54.87K', cycles: '89.24K', memory: '10.53KiB')]
	public function benchSortHundred(): void
	{
		$numbers = range(100, 1, -1);
		sort($numbers);
	}

}
```

## Why

Timing benchmarks don't work in CI. Runners differ from each other in CPU model, clock speed, and cache sizes, and share the hardware with other jobs, so the same code takes a different time on every run. The noise is bigger than most regressions worth catching.

Tally runs each benchmark once under [CodSpeed](https://codspeed.io)'s CPU simulation, which is built on Valgrind. The simulation counts executed instructions and estimates cycles from simulated cache misses. These numbers are the same on every run and on every runner with the same PHP build, so they are committed next to the benchmark and checked in every pull request, like the expected output of a test.

## Contents

- [Installation](#installation)
- [Setting up CodSpeed](#setting-up-codspeed)
- [Writing benchmarks](#writing-benchmarks)
- [Attributes](#attributes)
- [Running](#running)
- [Commands](#commands)
- [Configuration](#configuration)
- [In CI](#in-ci)
- [Contributing a benchmark](#contributing-a-benchmark)
- [Storing expectations elsewhere](#storing-expectations-elsewhere)
- [How cases are counted](#how-cases-are-counted)

## Installation

```bash
composer require --dev phpstan/tally
```

Tally needs PHP 8.5 with the FFI and POSIX extensions, and CodSpeed, which runs on Linux.

## Setting up CodSpeed

Tally needs two things from CodSpeed: the `codspeed` CLI on `PATH`, and the instrument-hooks library, which tells Valgrind when to count. Tally finds the library through the `INSTRUMENT_HOOKS_LIBRARY` environment variable.

```bash
# the CLI, with Valgrind
curl -fsSLO https://github.com/CodSpeedHQ/codspeed/releases/download/v5.6.3/codspeed-runner-x86_64-unknown-linux-musl.tar.gz
tar xzf codspeed-runner-x86_64-unknown-linux-musl.tar.gz --strip-components=1 codspeed-runner-x86_64-unknown-linux-musl/codspeed
sudo mv codspeed /usr/local/bin/
codspeed setup --mode=simulation

# instrument-hooks
git clone https://github.com/CodSpeedHQ/instrument-hooks.git
cd instrument-hooks
gcc -shared -fPIC -O2 -Iincludes -w dist/core.c -o libinstrument_hooks.so
export INSTRUMENT_HOOKS_LIBRARY=$PWD/libinstrument_hooks.so
```

Tally does not upload anything to CodSpeed and needs no CodSpeed account.

The counts depend on the PHP build and the system libraries. Numbers measured on a laptop differ from those of a CI runner, so take the expected numbers from CI.

## Writing benchmarks

A benchmark is a public, non-static method whose name starts with `bench`, in a class in a file whose name ends with `Bench.php`. Tally creates the class without constructor arguments.

```php
use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;

final class SortBench
{

	/**
	 * @return iterable<string, array{list<int>}>
	 */
	public static function shuffled(): iterable
	{
		foreach ([10, 1000] as $size) {
			$numbers = range(1, $size);
			mt_srand($size);
			shuffle($numbers);
			yield 'size-' . $size => [$numbers];
		}
	}

	/**
	 * @param list<int> $numbers
	 */
	#[DataProvider('shuffled')]
	#[ExpectedFor('size-10', instructions: '4.190K', cycles: '16.74K', memory: '832B')]
	#[ExpectedFor('size-1000', instructions: '873.5K', cycles: '1.038M', memory: '59.88KiB')]
	public function benchSort(array $numbers): void
	{
		sort($numbers);
	}

}
```

Each data set of a data provider is a case with the ID `Class::method@name`. A case without a data provider has the ID `Class::method`. The data provider runs before counting, so building the input is not counted. Keep the input the same on every run: a random order must come from a fixed seed.

Numbers are written with units: `K`, `M`, `B`, and `T` for counts, `B`, `KiB`, `MiB`, and `GiB` for memory, with four significant digits.

A benchmark method takes the arguments of its data set, and also these, by type, at any position:

- `Tally\Meter` turns counting off and on inside the method:

  ```php
  public function benchExclude(Meter $meter): int
  {
  	$numbers = $meter->exclude(static fn (): array => range(1, 10_000));

  	return array_sum($numbers);
  }

  public function benchPauseAndResume(Meter $meter): string
  {
  	$meter->pause();
  	$text = str_repeat('tally ', 10_000);
  	$meter->resume();

  	return mb_strtoupper($text);
  }
  ```

  Paused code still runs under the simulation, only its cost is not collected. Memory allocated while paused does not count either.

- `Tally\ScratchDirectory` is a directory of the case's own, removed after the run. Before and after methods get the same one.

What a benchmark returns is ignored.

[examples/](examples) has a benchmark for each feature.

## Attributes

All attributes are in the `Tally\Attribute` namespace.

| Attribute | On | Does |
| --- | --- | --- |
| `#[Expected(instructions: '…', cycles: '…', memory: '…')]` | method | The expected numbers of a method without a data provider. |
| `#[ExpectedFor('data set', instructions: '…', cycles: '…', memory: '…')]` | method | The expected numbers of one data set. Repeat it for each data set. The name must be a string literal, so `tally accept` can find it. |
| `#[DataProvider('method')]` | method | A public static method that returns an iterable of argument lists, keyed by the data set name, or of `Tally\DataSet` objects. |
| `#[BeforeMethods('method', …)]` | class, method | Public methods called before the benchmark, outside of counting. They take the data set's arguments from the first one, as many as they declare, and `Meter` and `ScratchDirectory`. On the class, they run before those on the method. |
| `#[AfterMethods('method', …)]` | class, method | Like `BeforeMethods`, called after the benchmark, also when it fails. |
| `#[Warmup(calls)]` | class, method | Calls the benchmark this many times before counting, so the counted call does not include autoloading or filling caches. |
| `#[RelativeTolerance(share)]` | class, method | Each metric may differ from its expected value by this share, such as `0.05` for ±5 %. |
| `#[DurationTolerance(tolerances: […], instructionsPerMillisecond: …, cyclesPerMillisecond: …, memory: …)]` | class, method | Allows a larger share the quicker the benchmark is. See below. |

A benchmark without a tolerance policy may differ by ±10 %. A policy on the method replaces the one on the class.

Without `#[Warmup]`, the counted call is the first one. The difference can be large:

```php
public function benchCold(): int|false
{
	// 72.84K instructions, mostly compiling the regular expression
	return preg_match('~^(?<user>[a-z]+)@(?<domain>[a-z]+)\.cold$~', 'tally@example.cold');
}

#[Warmup(1)]
public function benchWarm(): int|false
{
	// 882 instructions, the compiled expression is cached
	return preg_match('~^(?<user>[a-z]+)@(?<domain>[a-z]+)\.warm$~', 'tally@example.warm');
}
```

`DurationTolerance` is for suites whose benchmarks take from microseconds to seconds, like analysing whole files. A change elsewhere moves a quick benchmark by a larger share than a slow one. The expected instructions or cycles are converted to the milliseconds they take natively, and the share allowed comes from the range they fall in:

```php
#[DurationTolerance(
	tolerances: [0 => 0.5, 100 => 0.25, 500 => 0.2, 2000 => 0.1],
	instructionsPerMillisecond: 5_800_000,
	cyclesPerMillisecond: 7_400_000,
	memory: 0.1,
)]
```

Here a benchmark under 100 ms may differ by 50 ms, one from 100 ms by 25 %, and so on. Each range allows at least what the range below allows at its upper end. Memory may differ by 10 %.

## Running

```bash
vendor/bin/tally run --filter=SortBench benchmarks/
```

```
Tally counts 3 cases under CodSpeed's CPU simulation, 16 at once.

.S.                                                                 3 / 3 (100%)

Benchmarks failed: 1 slower, 2 unchanged
Allowed difference: ±10 % of each metric

Slower than expected
These benchmarks do more work than expected. Find out why before merging. If it is the price of a fix, accept the new numbers.

  Benchmark                         Metric         Expected   Measured    Change   Allowed
  App\SortBench::benchSort@size-10  instructions     3.490K     4.190K   +20.1 %     ±10 %
                                    cycles           16.74K     16.62K    -0.7 %     ±10 %
                                    memory             832B       832B    +0.0 %     ±10 %

Reproduce locally
  vendor/bin/tally run --filter='App\SortBench::benchSort@size-10'

Accept the new numbers
  vendor/bin/tally run --output=tmp/tally
  vendor/bin/tally accept tmp/tally
```

The progress bar shows one character per case: `.` unchanged, `S` slower, `F` faster, `N` new, `E` not measured.

A case is **slower** when a metric exceeds its expected value by more than the tolerance, and **faster** when it is lower by more. A case that throws, exits, or times out is **not measured**. A case without expected numbers is **new**. Slower and not measured cases fail the run. Faster and new cases pass, but their numbers should be accepted, so a later slowdown back to the old numbers does not go unnoticed.

Exit codes: `0` passed, `1` failed, `2` invalid usage, invalid benchmark, or CodSpeed not set up.

## Commands

```
tally <command> [options] [arguments]
```

Every command takes the paths of the benchmarks as arguments: directories, searched recursively for `*Bench.php`, and files. Without paths, it takes them from the configuration. These options select cases:

| Option | |
| --- | --- |
| `--config=<file>` | Configuration file, `tally.php` in the working directory by default. |
| `--filter=<filter>` | Only cases whose ID contains this text, or matches it as a regular expression like `/@size-10$/`. |
| `--shard=<shard>` | Only the cases of one shard, like `2/4`. See [sharding](#sharding). |

Every command takes `--help`.

### `tally list`

Lists the IDs of the cases, one per line, in a stable order.

### `tally run`

Counts the cases under CodSpeed and compares them with the expected numbers.

| Option | |
| --- | --- |
| `--jobs=<n>` | How many cases run at once, the number of CPUs by default. Valgrind runs each case on one CPU. |
| `--output=<directory>` | Keep the results there, for `tally compare` and `tally accept`. |

Before counting, `run` checks that every case is valid and that its expected numbers can be rewritten, and exits with code 2 if not.

### `tally compare <results>`

Compares the results of `tally run --output` with the expected numbers again, without counting. Useful after editing expectations, or to compare the results of several shards together.

| Option | |
| --- | --- |
| `--table` | Also print every case with its metrics and status. |

`<results>` is searched recursively, so the results of several runs can be downloaded into one directory.

### `tally accept <results>`

Makes the results of `tally run --output` the expected numbers of the cases that are new, slower, or faster. It rewrites the `#[Expected]` and `#[ExpectedFor]` attributes, or adds them, with the imports they need, and leaves the rest of the file as it is.

```
slower  App\SortBench::benchSort@size-10

Accepted the numbers of 1 case.
```

## Configuration

`tally.php` in the working directory returns a `Tally\Configuration`:

```php
<?php declare(strict_types = 1);

use Tally\Configuration;

return Configuration::create()
	->withPaths([__DIR__ . '/benchmarks'])
	->withIniEntries(['memory_limit=-1'])
	->withTimeLimits(60, 30 * 60);
```

| Method | |
| --- | --- |
| `withPaths(list<string>)` | Directories and files with benchmarks, used when a command gets no paths. |
| `withIniEntries(list<string>)` | PHP settings the cases run with, passed as `-d`. |
| `withTimeLimits(native, counted)` | Seconds a case may run natively, before it is counted, and under the simulation, which is about 50 times slower. 60 and 1800 by default. A case that runs longer is killed and reported as not measured. |

## In CI

The counts depend on the PHP build, so pin the runner image.

```yaml
jobs:
  benchmarks:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          extensions: ffi, posix
      - run: composer install --no-interaction --no-progress
      - name: Install CodSpeed
        run: |
          mkdir -p "$RUNNER_TEMP/codspeed" && cd "$RUNNER_TEMP/codspeed"
          curl -fsSLO https://github.com/CodSpeedHQ/codspeed/releases/download/v5.6.3/codspeed-runner-x86_64-unknown-linux-musl.tar.gz
          tar xzf codspeed-runner-x86_64-unknown-linux-musl.tar.gz --strip-components=1 codspeed-runner-x86_64-unknown-linux-musl/codspeed
          echo "$RUNNER_TEMP/codspeed" >> "$GITHUB_PATH"
          ./codspeed setup --mode=simulation
      - name: Build instrument-hooks
        run: |
          git clone -q --depth=1 https://github.com/CodSpeedHQ/instrument-hooks.git "$RUNNER_TEMP/instrument-hooks"
          cd "$RUNNER_TEMP/instrument-hooks"
          gcc -shared -fPIC -O2 -Iincludes -w dist/core.c -o libinstrument_hooks.so
      - run: vendor/bin/tally run --output=tmp/tally-results
        env:
          INSTRUMENT_HOOKS_LIBRARY: ${{ runner.temp }}/instrument-hooks/libinstrument_hooks.so
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: tally-results
          path: tmp/tally-results
```

In GitHub Actions, Tally also writes the report to the job summary and annotates the benchmarks that are slower, faster, new, or not measured. The report's accept command downloads the `tally-*` artifacts of the run:

```bash
gh run download 123456789 -R owner/repo -p 'tally-*' -D tmp/tally
vendor/bin/tally accept tmp/tally
```

### Sharding

`--shard=2/4` runs the second of four shards. The shards are balanced by expected instructions, from the most expensive case, each to the shard with the lowest total so far. A new case without numbers counts as much as the most expensive one. The same cases always give the same shards.

```yaml
    strategy:
      matrix:
        shard: [1, 2, 3, 4]
    steps:
      # …
      - run: vendor/bin/tally run --shard=${{ matrix.shard }}/4 --output=tmp/tally-results
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: tally-results-${{ matrix.shard }}
          path: tmp/tally-results
```

Each shard reports its own cases. To see them together, download all artifacts into one directory and run `tally compare`.

## Contributing a benchmark

A contributor without CodSpeed adds a benchmark without numbers and opens a pull request. CI measures it, and the report ends with a patch that adds the expected numbers:

```diff
--- a/benchmarks/StrBench.php
+++ b/benchmarks/StrBench.php
@@ -3,10 +3,13 @@
 namespace App;
 
 use Tally\Attribute\DataProvider;
+use Tally\Attribute\Expected;
+use Tally\Attribute\ExpectedFor;
 
 final class StrBench
 {
 
+	#[Expected(instructions: '122.3K', cycles: '203.9K', memory: '24.00KiB')]
 	public function benchImplode(): void
 	{
 		implode(',', range(1, 1000));
```

The patch is in the CI log and in the job summary. The contributor saves it and runs `git apply`, then pushes. A new case does not fail the run.

## Storing expectations elsewhere

Expected numbers do not have to be attributes. A data provider can yield `Tally\DataSet` objects with their own `Tally\Expectation\ExpectationSource`:

```php
#[DataProvider('scripts')]
public function benchScript(string $file): void
{
	require $file;
}

/**
 * @return iterable<string, DataSet>
 */
public static function scripts(): iterable
{
	$header = new ScriptHeader();
	foreach (glob(__DIR__ . '/scripts/*.php') as $file) {
		$name = basename($file, '.php');
		yield $name => DataSet::named($name, [$file])
			->withExpectations(new ScriptExpectations($header, $file, $header->read(file_get_contents($file))));
	}
}
```

Here each script keeps its numbers in a comment at its top:

```php
<?php declare(strict_types = 1);

/*
 * instructions: 1.247M
 * cycles: 1.419M
 * memory: 36.30KiB
 */
```

An `ExpectationSource` has four methods:

| Method | |
| --- | --- |
| `read(): ?Metrics` | The expected numbers, `null` for a new case. |
| `file(): string` | The file that keeps them. |
| `rewrite(string $code, Metrics $measured): string` | The code of the file with the measured numbers instead. Takes and returns code without reading or writing the file, so `tally accept` writes it and `tally run` shows it as a patch. |
| `location(): SourceLocation` | Where reports point to. Throws `ExpectationException` when the case's numbers cannot be rewritten, so `tally run` fails before counting. |

[examples/Scripts](examples/Scripts) is the whole example.

## How cases are counted

`tally run` starts each case in two processes: first natively, which fails quickly when the case is broken, then under `codspeed run`. In the counted process, Tally:

1. loads the benchmarks and finds the case,
2. calls the before methods and the warm-up calls, not counted,
3. counts a warm-up span, because the first counted span costs about twice as much as later ones, and throws it away,
4. counts an empty call through the same path as the benchmark, the baseline,
5. counts the benchmark,
6. calls the after methods, not counted.

Instructions and cycles come from CodSpeed's profile of the benchmark's span. Memory is the peak above the memory used when counting started, minus that of the baseline.

Cycles are Valgrind's estimate: instructions, plus 10 for each first-level cache miss and 100 for each last-level cache miss of the simulated cache. The cache sizes are the same on every machine.

## License

MIT
