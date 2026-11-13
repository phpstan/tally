# Tally – notes for coding agents

Tally benchmarks PHP code by counting instructions, cycles, and memory under CodSpeed's CPU simulation instead of measuring time. CodSpeed is required: there is no mode that measures without it. Benchmarks are `bench*` methods of classes in `*Bench.php` files; their expected numbers live next to them, in `#[Expected]` attributes or in an `ExpectationSource` a data provider attaches to a data set. See README.md for how it is used.

## Layout

- `src/Attribute` – the attributes benchmark classes use: `Expected` (a method without a data provider), `ExpectedFor` (one data set), `Warmup`, `BeforeMethods`, `AfterMethods`, `DataProvider`, and the tolerance policies `RelativeTolerance` and `DurationTolerance`.
- `src/Quantity` – `QuantityFormat` formats and parses numbers with units (`3.119B`, `44.49MiB`).
- `src/Expectation` – where expected metrics are stored. `ExpectationSource` is the interface, `AttributeExpectations` the file adapter for `#[Expected]` and `#[ExpectedFor]`, `ExpectedAttributeRewriter` the pure code-to-code rewrite it uses, `Tokens` the token list it walks.
- `src/Discovery` – `DirectorySuiteLoader` finds and loads `*Bench.php` files (I/O), `CaseExpander` turns one method into its cases (pure, works on reflection).
- `src/Selection` – `CaseSelector` implementations: `FilterSelector`, `ShardSelector` (longest-processing-time sharding by expected instructions).
- `src/Runner` – running one case in this process. `CaseRunner` calls the hooks and warm-up uncounted, then counts an empty `#baseline` call and one call of the benchmark, and subtracts the baseline's memory. `MeterSession` is the `Meter` a benchmark gets: it drives a `Counter` (`CodSpeedCounter` over `InstrumentHooks`, `IdleCounter`) and measures memory above the start through `Memory`. `ArgumentResolver` fills parameters: `Meter` and `ScratchDirectory` by type, the rest from the data set. `FfiInstrumentHooks` is the only class that calls C.
- `src/Comparison` – `Comparator` turns a case's expectations and measurement into a `CaseComparison` of `MetricComparison`s with a `Status`. `TolerancePolicy` is implemented by the tolerance attributes; `CaseExpander` picks the method's, else the class's, else ±10 %. `Percent` formats shares.
- `src/Report` – `Report` groups comparisons into `Section`s by their worst status. Renderers are pure and return strings: `ConsoleReport` (summary and full table, with or without colors), `MarkdownReport` (GitHub job summary), `GitHubAnnotations` (workflow commands). `Rows` and `TextTable` lay out the tables. The golden files in `tests/Unit/Report/golden` are the expected output; regenerate them only when the output changes on purpose, and read the diff.
- `src/Result` – `MeasurementJson`, the format of a measured case in results files; `ResultsDirectory` reads and writes a directory of them; `CallgrindProfile` reads the instructions and estimated cycles of each named part of a profile CodSpeed wrote.
- `src/Process` – a generic process pool. `ProcessPool` runs `Job`s, each a list of `ProcessCommand` steps that run while each succeeds, in parallel, and kills a step that exceeds its time limit with all its descendants through `ProcessKiller`. `ProcessTable` is the pure part: parsing `/proc/<pid>/stat` and finding descendants. Descendants matter because killing `codspeed` leaves Valgrind running.
- `src/Execution` – measuring a case in worker processes. `CaseJobs` builds a case's job (a native run, then a run under `codspeed run`, sharing a scratch directory) and reads what the job left. `WorkerCommandLine` builds the commands, `CaseOutcome` turns the runs and the profile parts into a `Measurement` (both pure). `CodSpeedInstallation` finds the CLI and the instrument-hooks library, `DirectoryRemover` cleans up the work directory.
- `src/Cli` – `Application`, the commands (`list`, `run`, `compare`, `accept`, and the hidden `worker` that `run` starts), `ArgumentParser`, `Input`, `Output`. `ReportOutput` writes the report and, in GitHub Actions, the annotations and job summary. `ApplicationFactory` is the composition root; `bin/tally` passes it the streams, the environment, and the binaries the workers run.
- `src/` root – value objects shared by everything: `Metric`, `Metrics`, `CaseId`, `BenchmarkCase`, `DataSet`, `Measurement`, `ScratchDirectory`, `Configuration`, and the `Meter` interface.
- `bin/tally` – finds the autoloader and runs `ApplicationFactory`.
- `build/` – scripts for the build, such as the coverage check. `build/PHPStan` holds Tally's own PHPStan rules, `build/stubs` the stubs for what PHPStan cannot see, such as the C functions FFI calls.
- `tests/Unit` – pure logic, no file system, processes, or network. `tests/Integration` – adapters and the CLI. `tests/Fixtures` – benchmark classes and configuration files the tests load; excluded from PHPStan and the coding standard because some are invalid on purpose.

## Design rules

- Every class is `final`. Behavior is composed from injected collaborators, never inherited. Interfaces are for real variation points (`ExpectationSource`, `CaseSelector`, `Command`), not for every class.
- Services are stateless and get their collaborators through the constructor. No singletons, no static service methods, no service creating another service with `new`. `ApplicationFactory` decides which implementation each service gets.
- Value objects (`Metrics`, `CaseId`, `DataSet`, `Measurement`, `Configuration`, `Tokens`) are immutable: `readonly` properties, `with…()` methods that return a copy. `MeterSession` is the one mutable object, because a benchmark pauses and resumes it; it counts one span and is thrown away.
- No optional parameters and no default values, in `src` and `tests` alike: every parameter is required and every call passes every argument, and properties get their values in the constructor. Attributes too: `#[Expected]` takes all three metrics. A value used by default, like the 10 % tolerance, is a named constant where it is chosen. PHPStan enforces this with the rules in `build/PHPStan`.
- Keep pure logic and I/O in separate classes. Logic takes everything it needs as arguments and returns its result (`ExpectedAttributeRewriter` takes code and returns code); a thin adapter reads and writes (`AttributeExpectations` reads and writes the file).
- No runtime dependencies besides PHP 8.5. Dev dependencies are fine.
- Errors the user causes and can fix (invalid benchmark, bad CLI usage, unparsable quantity) are checked exceptions extending `Exception` and are declared with `@throws`. Errors that only a bug in Tally causes extend `LogicException`.
- Public API for benchmark authors and extensions is marked `@api`. Everything else may change.

## Tests

- No mocks. Test logic through its inputs and outputs, with data providers that name each case. A fake implementation of an interface is fine where an adapter is needed (`tests/InMemoryExpectations.php`).
- External programs are replaced by fakes with the same interface: `tests/Fixtures/CodSpeed/bin/codspeed` runs the command like `codspeed run` and writes a profile with the counts from `TALLY_FAKE_COUNTS`; `tests/Fixtures/InstrumentHooks/recording.c` (built by `tests/InstrumentHooksFixture.php`) records the calls instead of talking to Valgrind.
- `tests/InProcessTally.php` runs the application in the test process, so coverage counts it. Coverage collection allocates memory, so assertions on measured memory run `bin/tally` in its own process.
- Unit-test pure classes thoroughly. Integration-test adapters and the CLI end to end (`Application` through `ApplicationFactory`, and `bin/tally` in a process).
- Assert exact outputs, such as whole strings and exact exception messages, not substrings.
- Write the test first. Every line of `src` is covered and every mutant is killed; when a mutant survives, add the test that tells the two versions apart, or restructure the code when the mutant is equivalent (an initial value that is always overwritten, a check another check already makes). No `@infection-ignore-all`.

## Checks

All must pass before a commit:

```bash
make lint       # parallel-lint
make cs         # phpstan/build-cs, make cs-fix fixes what it can
make tests      # PHPUnit
make phpstan    # level max, strict rules, bleeding edge, dead code detector, checked exceptions
make coverage   # pcov, fails below 100 % of lines
make infection  # mutation testing of the whole codebase, fails below 100 % MSI
```

`make cs-install` clones build-cs. PHPStan runs with no ignores and no baseline: fix the types. The dead code detector reports unused code; delete it, or mark it `@api` when it is public API. A stale result cache of the dead code detector makes PHPStan fail with "DCD cache file not found"; `make infection` clears the result cache first, run `vendor/bin/phpstan clear-result-cache` before other runs when it happens. CI runs all of these on PHP 8.5 and 8.6; Infection runs on the whole codebase on pushes and on the changed lines in pull requests.

## Conventions

- Every file starts with `<?php declare(strict_types = 1);`, tabs for indentation.
- Concise technical English in code comments, docs, and commit messages. Comments say why, not what.
- Commits are small and explain why. One logical change per commit, its tests included.
- Keep this file up to date when the layout or a rule changes.
