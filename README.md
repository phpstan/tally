# Tally

Benchmarks PHP code by counting instructions, CPU cycles, and memory instead of measuring time.

```php
final class SortBench
{

	#[Expected(instructions: '59.00K', cycles: '94.43K', memory: '10.53KiB')]
	public function benchSortHundred(): void
	{
		$numbers = range(100, 1, -1);
		sort($numbers);
	}

}
```

## Why

Timing benchmarks don't work in CI. Runners differ from each other in CPU model, clock speed, and cache sizes, and share the hardware with other jobs, so the same code takes a different time on every run. The noise is bigger than most regressions worth catching.

Tally runs each benchmark once under [CodSpeed](https://codspeed.io)'s CPU simulation, which is built on Valgrind. The simulation counts executed instructions and estimates cycles from simulated cache misses. With the same PHP build, these numbers barely move between runs: in two CI runs of Tally's own benchmarks, instructions differed by at most 0.4 % and cycles by at most 0.8 %, and memory not at all. So the numbers are committed next to the benchmark and checked in every pull request, like the expected output of a test.

## Contents

- [Installation](#installation)
- [Writing benchmarks](#writing-benchmarks)
- [Attributes](#attributes)
- [Complexity](#complexity)
- [Running](#running)
- [Commands](#commands)
- [Configuration](#configuration)
- [In CI](#in-ci)
- [Contributing a benchmark](#contributing-a-benchmark)
- [Storing expectations elsewhere](#storing-expectations-elsewhere)
- [Lifecycle of a case](#lifecycle-of-a-case)

## Installation

```bash
composer require --dev phpstan/tally
```

Tally needs PHP 8.2 or later with the POSIX extension, on Linux on x86_64 or aarch64. Nothing else to set up: each release includes, in `codspeed/` for both platforms,

- a build of [CodSpeed's Valgrind](https://github.com/CodSpeedHQ/valgrind-codspeed), with its license and where its source is. Tally starts it with the options of `codspeed run --mode=simulation`, so it counts like CodSpeed, but needs neither the `codspeed` CLI nor a CodSpeed account, and uploads nothing.
- builds of Tally's `tally_hooks` extension ([ext/tally_hooks](ext/tally_hooks)) for PHP 8.2 to 8.6, thread safe or not. It tells Callgrind when to count, with the same client requests as CodSpeed's [instrument-hooks](https://github.com/CodSpeedHQ/instrument-hooks). `tally run` loads the build for your PHP into the process that counts a case with `-d extension=`, so it needs no `php.ini` change and nothing else in your other processes.

A debug build of PHP, or another PHP whose extension API differs from the official one, needs its own build of `tally_hooks`. Build it with `phpize` and load it in `php.ini`, then `tally run` uses it instead:

```bash
build/build-tally-hooks.sh /usr/local/lib/php/tally_hooks.so  # in vendor/phpstan/tally
echo 'extension=/usr/local/lib/php/tally_hooks.so' > /usr/local/etc/php/conf.d/tally_hooks.ini
```

Releases are tagged on the `release/1.x` branch, which CI builds from `1.x` with the builds committed. A clone of `1.x`, or `dev-1.x` from Composer, has no builds: run `make codspeed`, which builds Valgrind and `tally_hooks` for your PHP and needs gcc, make, autotools, and `phpize`.

The counts depend on the PHP build and the system libraries. Numbers measured on a laptop differ from those of a CI runner, so take the expected numbers from CI.

## Writing benchmarks

A benchmark is a public, non-static method whose name starts with `bench`, in a class in a file whose name ends with `Bench.php`. Tally creates the class with the constructor arguments described [below](#meter-and-scratch-directory). A `bench*` method that is static or not public is an error, so is a path without any benchmarks.

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
	#[ExpectedFor('size-10', instructions: '4.563K', cycles: '20.96K', memory: '1.023KiB')]
	#[ExpectedFor('size-1000', instructions: '955.3K', cycles: '1.119M', memory: '59.88KiB')]
	public function benchSort(array $numbers): void
	{
		sort($numbers);
	}

}
```

Each data set of a data provider is a case with the ID `Class::method@name`. A case without a data provider has the ID `Class::method`. The data provider runs before counting, so building the input is not counted. Keep the input the same on every run: a random order must come from a fixed seed.

Numbers are written with units: `K`, `M`, `B`, and `T` for counts, `B`, `KiB`, `MiB`, and `GiB` for memory, with four significant digits.

### Meter and scratch directory

A benchmark method takes the arguments of its data set. Tally creates one object of the class for each case, and its constructor may take these, by type, in any order:

- `Tally\Meter` turns counting off and on:

  ```php
  public function __construct(private Meter $meter)
  {
  }

  public function benchExclude(): int
  {
  	$numbers = $this->meter->exclude(static fn (): array => range(1, 10_000));

  	return array_sum($numbers);
  }

  public function benchPauseAndResume(): string
  {
  	$this->meter->pause();
  	$text = str_repeat('tally ', 10_000);
  	$this->meter->resume();

  	return mb_strtoupper($text);
  }
  ```

  Paused code still runs under the simulation, only its cost is not collected. Memory allocated while paused does not count either. The meter works in every call Tally makes on the object, so hooks can use it too; in them, it counts nothing anyway. Using it in the constructor is an error.

- `Tally\ScratchDirectory` is an empty directory of the case's own, for files the benchmark reads or writes. `path()` returns it. Hooks see the same directory, so a `#[Before]` method can write a file the benchmark reads:

  ```php
  public function __construct(private ScratchDirectory $scratch)
  {
  }

  #[Before]
  public function writeFile(): void
  {
  	file_put_contents($this->scratch->path() . '/numbers.json', json_encode(range(1, 1_000)));
  }

  public function benchDecode(): void
  {
  	json_decode(file_get_contents($this->scratch->path() . '/numbers.json'), true);
  }
  ```

  The directory is the same in both processes that run the case, see [lifecycle](#lifecycle-of-a-case), and Tally removes it when `tally run` finishes.

A constructor parameter of any other type, and a method parameter of one of these types, is an error.

### Hooks

A method with `#[Before]` runs before every case of every benchmark in the class, and a method with `#[After]` after it, also when the benchmark throws. Neither is counted. Hooks run on the same object as the benchmark, so a `#[Before]` method can prepare properties the benchmark uses. Several hooks run in the order they are declared. They may be private.

A hook takes the case's data set arguments, from the first one, as many as it declares:

```php
/** @var array<int, int> */
private array $index;

#[Before]
private function buildIndex(int $size): void
{
	$this->index = array_flip(range(1, $size));
}

/**
 * @return iterable<string, array{int}>
 */
public static function sizes(): iterable
{
	yield 'size-10' => [10];
	yield 'size-1000' => [1000];
}

#[DataProvider('sizes')]
public function benchLookup(int $size): bool
{
	return isset($this->index[$size]);
}
```

To give two benchmarks different hooks, put them in different classes.

What a benchmark returns is ignored.

[examples/](examples) has a benchmark for each feature.

## Attributes

All attributes are in the `Tally\Attribute` namespace.

| Attribute | On | Does |
| --- | --- | --- |
| `#[Expected(instructions: '…', cycles: '…', memory: '…')]` | method | The expected numbers of a method without a data provider. |
| `#[ExpectedFor('data set', instructions: '…', cycles: '…', memory: '…')]` | method | The expected numbers of one data set. Repeat it for each data set. The name must be a string literal, so `tally accept` can find it. |
| `#[DataProvider('method')]` | method | A public static method that returns an iterable of argument lists, keyed by the data set name, or of `Tally\DataSet` objects. |
| `#[Before]` | method | Runs the method before every case of the class, not counted. See [hooks](#hooks). |
| `#[After]` | method | Runs the method after every case of the class, also when it throws, not counted. |
| `#[Warmup(calls)]` | class, method | Calls the benchmark this many times before counting, so the counted call does not include autoloading or filling caches. |
| `#[RelativeTolerance(share)]` | class, method | Each metric may differ from its expected value by this share, such as `0.05` for ±5 %. |
| `#[DurationTolerance(tolerances: […], instructionsPerMillisecond: …, cyclesPerMillisecond: …, memory: …)]` | class, method | Allows a larger share the quicker the benchmark is. See below. |
| `#[Complexity(size: '…', formula: '…')]` | method | Instead of expected numbers, how the instructions may grow with the size of the data sets. See [complexity](#complexity). |
| `#[Constant(size)]`, `#[Logarithmic(size)]`, `#[Linear(size)]`, `#[Linearithmic(size)]`, `#[Quadratic(size)]`, `#[Polynomial(size, degree: k)]`, `#[Exponential(size)]` | method | `#[Complexity]` with the formula `1`, `log(n)`, `n`, `n * log(n)`, `n^2`, `n^k`, `2^n`. |

A benchmark without a tolerance policy may differ by ±10 %. A policy on the method replaces the one on the class.

Without `#[Warmup]`, the counted call is the first one. The difference can be large:

```php
public function benchCold(): int|false
{
	// 75.88K instructions, mostly compiling the regular expression
	return preg_match('~^(?<user>[a-z]+)@(?<domain>[a-z]+)\.cold$~', 'tally@example.cold');
}

#[Warmup(1)]
public function benchWarm(): int|false
{
	// 864 instructions, the compiled expression is cached
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

## Complexity

Expected numbers catch any change. Sometimes what matters is how the cost grows: a lookup that must stay constant, a sort that must not turn quadratic. A complexity class checks that, with no numbers to keep up to date:

```php
/**
 * @param list<int> $numbers
 */
#[DataProvider('lists')]
#[Linearithmic('numbers')]
public function benchSort(array $numbers): void
{
	sort($numbers);
}
```

The data set of the smallest size is the base. Each other data set may count at most the base's instructions times the growth of the formula between the two sizes, plus the tolerance. For `n * log(n)` from 100 to 10000 elements, that is 200 times. Growing less passes: the class is an upper bound. Cycles and memory are not checked.

The size is an expression of the method's parameters. An array's size is its count, a number is the size itself. A path picks it from inside an argument:

```php
#[DataProvider('orders')]
#[Linear('order.items')]
public function benchTotal(array $order): int
```

Paths take keys by name (`order.items`), by number (`rows[0]`), or by a quoted string (`input['a b']`).

The formula is an expression of `n` with `+ - * / ^`, parentheses, and the functions `log` (natural), `sqrt`, `exp`, and `factorial`. Any row of a table of time complexities can be written, such as `#[Complexity(size: 'number', formula: 'sqrt(n)')]` for trial division. It must be above 0 at every size of the data sets. The presets are this attribute with a fixed formula.

When the instructions grow faster than the class allows:

```
Tally counts 3 cases under CodSpeed's CPU simulation, 16 at once.

.SS                                                                 3 / 3 (100%)

Benchmarks failed: 2 slower, 1 unchanged
Allowed difference: instructions grow at most as O(n) from the smallest data set, ±10 % of each metric

Slower than expected
These benchmarks do more work than expected. Find out why before merging. If it is the price of a fix, loosen the complexity class.

  Benchmark                            Metric         Expected   Measured     Change   Allowed
  App\PairsBench::benchPairs@size-20   instructions     93.44K     178.7K    +91.2 %     ±10 %
  App\PairsBench::benchPairs@size-40   instructions     186.9K     699.7K   +274.4 %     ±10 %

Reproduce locally
  vendor/bin/tally run --filter='App\PairsBench::benchPairs@size-40'
```

`tally accept` does not write anything for these cases; change the attribute instead. A method needs a data provider with at least two data sets of different sizes, and cannot also have `#[ExpectedFor]` or expectations from its data provider. `--filter` adds the base of each selected case, and `--shard` keeps the cases of a method in one shard, because each is compared with its base.

Mistakes in the expressions are found when the benchmarks are loaded, before anything is counted:

```
App\PairsBench::benchPairs(): #[Complexity] formula: Unknown function "lg". Did you mean "log"?
  n * lg(n)
      ^^
```

```
App\PairsBench::benchPairs(): #[Complexity] size: Unknown variable "input". The variables are "numbers".
  input.numbers
  ^^^^^
```

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
                                    memory             832B       832B     0.0 %     ±10 %

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
| `--filter=<filter>` | Only cases whose ID contains this text, or matches it as a regular expression like `/@size-10$/`. A filter that matches no case is an error. |
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

The counts depend on everything that runs the benchmark:

- the PHP version, including the patch release,
- how PHP was built, which extensions are loaded, and the PHP settings, such as whether opcache and JIT are on,
- the system libraries, such as glibc, which come with the runner's operating system image.

Expected numbers measured in one environment only fit that environment. So:

- use a fixed runner image, like `ubuntu-24.04`, not `ubuntu-latest`, which moves to a new Ubuntu release from time to time,
- keep the PHP setup the same: version, extensions, and `ini-values`,
- expect to accept new numbers for every benchmark when you change any of these. `setup-php` installs the latest patch release of a PHP version, so a new PHP release can change the numbers too, usually of many cases at once.

```yaml
jobs:
  benchmarks:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          extensions: posix
      - run: composer install --no-interaction --no-progress
      - run: vendor/bin/tally run --output=tmp/tally-results
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: tally-results
          path: tmp/tally-results
```

In GitHub Actions, Tally also writes the report to the job summary and annotates the benchmarks that are slower, faster, new, or not measured, with paths relative to `GITHUB_WORKSPACE`, so Tally can run in a subdirectory of the repository. The report's accept command downloads the `tally-*` artifacts of the run:

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
 * instructions: 679.5K
 * cycles: 854.8K
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

## Lifecycle of a case

`tally run` loads the benchmarks, then runs each case in two processes of its own: first natively, which fails quickly when the case is broken, then under CodSpeed's Valgrind, which counts it. Several cases run at once, see `--jobs`.

Every process loads the whole suite, so **data providers run in every process**: in `tally run` itself, and twice for each case. Every `tally` command runs them too. Keep them cheap and do expensive preparation in a `#[Before]` method, which runs only for the case that needs it.

In each of the two processes of a case, Tally:

1. loads the benchmarks and calls every data provider, not counted,
2. creates the benchmark class, once, with its `Meter` and `ScratchDirectory`,
3. calls the `#[Before]` methods, not counted,
4. calls the benchmark as many times as `#[Warmup]` says, not counted,
5. counts a warm-up span, because the first counted span costs about twice as much as later ones, and throws it away,
6. counts an empty call through the same path as the benchmark, the baseline,
7. counts one call of the benchmark,
8. calls the `#[After]` methods, not counted.

The native process runs the same steps, so hooks and warm-up calls run twice per case, once in each process. The scratch directory is shared between the two, so a `#[Before]` method that writes a file finds it already there in the second process.

Instructions and cycles come from Callgrind's profile of the benchmark's span. Memory is the peak above the memory used when counting started, minus that of the baseline.

Cycles are Valgrind's estimate: instructions, plus 10 for each first-level cache miss and 100 for each last-level cache miss of the simulated cache. The cache sizes are the same on every machine.

## License

MIT
