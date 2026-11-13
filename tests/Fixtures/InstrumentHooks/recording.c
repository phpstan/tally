// Stands in for CodSpeed's instrument-hooks library in the tests: records each call
// instead of talking to Valgrind. Build with -DINSTRUMENTED=0 for a process not under the simulation.

#include <stdbool.h>
#include <stdint.h>
#include <stdio.h>
#include <string.h>

typedef struct InstrumentHooks {
	int unused;
} InstrumentHooks;

static InstrumentHooks instance;
static char calls[4096];

static void record(const char *call) {
	strncat(calls, call, sizeof(calls) - strlen(calls) - 1);
}

static const char *on(InstrumentHooks *hooks) {
	return hooks == &instance ? "" : " on an unknown instance";
}

InstrumentHooks *instrument_hooks_init(void) {
	record("init\n");
	return &instance;
}

bool instrument_hooks_is_instrumented(InstrumentHooks *hooks) {
	return hooks == &instance && INSTRUMENTED;
}

uint8_t instrument_hooks_start_benchmark(InstrumentHooks *hooks) {
	char call[64];
	snprintf(call, sizeof(call), "start%s\n", on(hooks));
	record(call);
	return 0;
}

uint8_t instrument_hooks_stop_benchmark(InstrumentHooks *hooks) {
	char call[64];
	snprintf(call, sizeof(call), "stop%s\n", on(hooks));
	record(call);
	return 0;
}

uint8_t instrument_hooks_set_executed_benchmark(InstrumentHooks *hooks, int32_t pid, const char *uri) {
	char call[512];
	snprintf(call, sizeof(call), "executed %d %s%s\n", pid, uri, on(hooks));
	record(call);
	return 0;
}

void callgrind_toggle_collect(void) {
	record("toggle\n");
}

const char *recorded_calls(void) {
	return calls;
}

void clear_recorded_calls(void) {
	calls[0] = '\0';
}
