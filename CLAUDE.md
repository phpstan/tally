# Tally – notes for coding agents

Tally benchmarks PHP code by counting instructions, cycles, and memory under CodSpeed's CPU simulation instead of measuring time. Benchmarks are `bench*` methods of classes in `*Bench.php` files; their expected numbers live next to them, in `#[Expected]` attributes or in an `ExpectationSource` a data provider attaches to a data set. See README.md for how it is used.

## Layout

- `src/Attribute` – the attributes benchmark classes use: `Expected` (a method without a data provider), `ExpectedFor` (one data set), `Warmup`, `BeforeMethods`, `AfterMethods`, `DataProvider`, and the tolerance policies `RelativeTolerance` and `DurationTolerance`.
- `src/Quantity` – `QuantityFormat` formats and parses numbers with units (`3.119B`, `44.49MiB`).
- `src/Expectation` – where expected metrics are stored. `ExpectationSource` is the interface, `AttributeExpectations` the file adapter for `#[Expected]` and `#[ExpectedFor]`, `ExpectedAttributeRewriter` the pure code-to-code rewrite it uses, `Tokens` the token list it walks.
- `src/Discovery` – `DirectorySuiteLoader` finds and loads `*Bench.php` files (I/O), `CaseExpander` turns one method into its cases (pure, works on reflection).
- `src/Selection` – `CaseSelector` implementations: `FilterSelector`, `ShardSelector` (longest-processing-time sharding by expected instructions).
- `src/Runner` – running one case in this process. `CaseRunner` calls the hooks and warm-up uncounted, then counts an empty `#baseline` call and one call of the benchmark. `MeterSession` is the `Meter` a benchmark gets: it drives a `Counter` (`CodSpeedCounter` over `InstrumentHooks`, `NativeCounter`) and measures memory above the start through `Memory`. `ArgumentResolver` fills parameters: `Meter` and `ScratchDirectory` by type, the rest from the data set. `FfiInstrumentHooks` is the only class that calls C.
- `src/Comparison` – `TolerancePolicy`, implemented by the tolerance attributes, and `Percent` for reports.
- `src/Result` – `MeasurementJson`, the format of a measured case in results files.
- `src/Cli` – `Application`, the commands, `ArgumentParser`, `Input`, `Output`. `ApplicationFactory` is the composition root.
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
