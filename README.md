# Tally

Benchmarks PHP code by counting instructions, CPU cycles, and memory instead of measuring time.

## Why

Timing benchmarks don't work in CI. Runners differ from each other in CPU model, clock speed, and cache sizes, and share the hardware with other jobs, so the same code takes a different time on every run. The noise is bigger than most regressions worth catching.

Tally runs each benchmark once under [CodSpeed](https://codspeed.io)'s CPU simulation, which is built on Valgrind. The simulation counts executed instructions and estimates cycles from simulated cache misses. These numbers are the same on every run and on every runner, so they can be committed next to the benchmark and checked in every pull request.

## Status

Work in progress.
