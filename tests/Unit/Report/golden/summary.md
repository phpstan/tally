## Benchmarks failed: 2 slower, 1 not measured, 1 new, 1 faster, 1 unchanged

Allowed difference: ±10 % of each metric

> [!CAUTION]
> **Slower than expected**
> These benchmarks do more work than expected. Find out why before merging. If it is the price of a fix, accept the new numbers.

| Benchmark | Metric | Expected | Measured | Change | Allowed |
|---|---|--:|--:|--:|--:|
| `App\ABench::benchSlower@big` | instructions | 1.000M | 1.500M | +50.0 % | ±10 % |
|  | cycles | 1.200M | 1.250M | +4.2 % | ±10 % |
|  | memory | 1.000MiB | 800.0KiB | -21.9 % | ±10 % |
| `App\ABench::benchSlower@small` | instructions | 1.000M | 1.200M | +20.0 % | ±10 % |
|  | cycles | 1.200M | 1.200M | +0.0 % | ±10 % |
|  | memory | 1.000MiB | 1.000MiB | +0.0 % | ±10 % |

> [!WARNING]
> **Not measured**
> These benchmarks failed, timed out, or did not run. Their errors are below.

| Benchmark | Error |
|---|---|
| `App\ABench::benchBroken` | RuntimeException: It broke. |

> [!NOTE]
> **New benchmarks**
> These benchmarks have no expected numbers yet. This does not fail, but accept the new numbers.

| Benchmark | Metric | Expected | Measured | Change | Allowed |
|---|---|--:|--:|--:|--:|
| `App\ABench::benchNew` | instructions | - | 1.204K | - | - |
|  | cycles | - | 1.387K | - | - |
|  | memory | - | 0B | - | - |

> [!TIP]
> **Faster than expected**
> Nice! This does not fail, but accept the new numbers, so a later slowdown back to the old ones does not go unnoticed.

| Benchmark | Metric | Expected | Measured | Change | Allowed |
|---|---|--:|--:|--:|--:|
| `App\ABench::benchFaster` | instructions | 1.000M | 500.0K | -50.0 % | ±10 % |
|  | cycles | 1.200M | 1.150M | -4.2 % | ±10 % |
|  | memory | 1.000MiB | 1.000MiB | +0.0 % | ±10 % |

### Reproduce locally

```
vendor/bin/tally run --filter='App\ABench::benchSlower@big'
```

### Accept the new numbers

```
gh run download 1 -D tmp/tally
vendor/bin/tally accept tmp/tally
```

<details><summary>All benchmarks</summary>

| Benchmark | Metric | Expected | Measured | Change | Allowed | Status |
|---|---|--:|--:|--:|--:|---|
| `App\ABench::benchBroken` | instructions | 1.000M | - | - | - | not measured |
|  | cycles | 1.200M | - | - | - | not measured |
|  | memory | 1.000MiB | - | - | - | not measured |
| `App\ABench::benchFaster` | instructions | 1.000M | 500.0K | -50.0 % | ±10 % | faster |
|  | cycles | 1.200M | 1.150M | -4.2 % | ±10 % | ok |
|  | memory | 1.000MiB | 1.000MiB | +0.0 % | ±10 % | ok |
| `App\ABench::benchNew` | instructions | - | 1.204K | - | - | new |
|  | cycles | - | 1.387K | - | - | new |
|  | memory | - | 0B | - | - | new |
| `App\ABench::benchOk` | instructions | 1.000M | 1.050M | +5.0 % | ±10 % | ok |
|  | cycles | 1.200M | 1.200M | +0.0 % | ±10 % | ok |
|  | memory | 1.000MiB | 1.000MiB | +0.0 % | ±10 % | ok |
| `App\ABench::benchSlower@big` | instructions | 1.000M | 1.500M | +50.0 % | ±10 % | slower |
|  | cycles | 1.200M | 1.250M | +4.2 % | ±10 % | ok |
|  | memory | 1.000MiB | 800.0KiB | -21.9 % | ±10 % | faster |
| `App\ABench::benchSlower@small` | instructions | 1.000M | 1.200M | +20.0 % | ±10 % | slower |
|  | cycles | 1.200M | 1.200M | +0.0 % | ±10 % | ok |
|  | memory | 1.000MiB | 1.000MiB | +0.0 % | ±10 % | ok |

</details>
