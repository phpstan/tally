/*
 * tally_hooks: lets the counted worker of Tally tell Callgrind what to count, with the client
 * requests of Valgrind's valgrind.h and callgrind.h (BSD-style license, see their headers), the
 * same requests CodSpeed's instrument-hooks library makes. Outside Valgrind they do nothing.
 *
 * Built with -DTALLY_HOOKS_RECORDING for Tally's tests, it records the calls instead, returns them
 * from Tally\Hooks\recorded_calls(), and counts as instrumented unless TALLY_HOOKS_OUTSIDE is set.
 */

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"

#define TALLY_HOOKS_VERSION "1.0.0"
#define TALLY_HOOKS_NAMESPACE "Tally\\Hooks"

#ifdef TALLY_HOOKS_RECORDING
# include <stdlib.h>
# include "zend_smart_str.h"

static smart_str recorded;

# define TALLY_INSTRUMENTED() (getenv("TALLY_HOOKS_OUTSIDE") == NULL)
# define TALLY_START() smart_str_appends(&recorded, "start\n")
# define TALLY_STOP() smart_str_appends(&recorded, "stop\n")
# define TALLY_TOGGLE_COLLECT() smart_str_appends(&recorded, "toggle\n")
# define TALLY_DUMP(name) do { \
		smart_str_appends(&recorded, "dump "); \
		smart_str_appends(&recorded, name); \
		smart_str_appendc(&recorded, '\n'); \
	} while (0)
#else
# include "callgrind.h"

# define TALLY_INSTRUMENTED() (RUNNING_ON_VALGRIND > 0)
# define TALLY_START() do { CALLGRIND_ZERO_STATS; CALLGRIND_START_INSTRUMENTATION; } while (0)
# define TALLY_STOP() CALLGRIND_STOP_INSTRUMENTATION
# define TALLY_TOGGLE_COLLECT() CALLGRIND_TOGGLE_COLLECT
# define TALLY_DUMP(name) CALLGRIND_DUMP_STATS_AT(name)
#endif

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_bool, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_void, 0, 0, IS_VOID, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_dump, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

/* Whether the process runs under Valgrind */
PHP_FUNCTION(tally_hooks_instrumented)
{
	ZEND_PARSE_PARAMETERS_NONE();

	RETURN_BOOL(TALLY_INSTRUMENTED());
}

/* Zeroes the counts and starts counting */
PHP_FUNCTION(tally_hooks_start)
{
	ZEND_PARSE_PARAMETERS_NONE();

	TALLY_START();
}

PHP_FUNCTION(tally_hooks_stop)
{
	ZEND_PARSE_PARAMETERS_NONE();

	TALLY_STOP();
}

/* Turns collecting the counts off or on, keeping the simulated caches */
PHP_FUNCTION(tally_hooks_toggle_collect)
{
	ZEND_PARSE_PARAMETERS_NONE();

	TALLY_TOGGLE_COLLECT();
}

/* Writes the counts so far as a part of the profile with this name */
PHP_FUNCTION(tally_hooks_dump)
{
	zend_string *name;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(name)
	ZEND_PARSE_PARAMETERS_END();

	TALLY_DUMP(ZSTR_VAL(name));
}

#ifdef TALLY_HOOKS_RECORDING
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_string, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

/* The calls since the last call of this function */
PHP_FUNCTION(tally_hooks_recorded_calls)
{
	ZEND_PARSE_PARAMETERS_NONE();

	smart_str_0(&recorded);
	if (recorded.s == NULL) {
		RETURN_EMPTY_STRING();
	}
	RETVAL_STR(recorded.s);
	recorded.s = NULL;
}
#endif

static const zend_function_entry tally_hooks_functions[] = {
	ZEND_NS_NAMED_FE(TALLY_HOOKS_NAMESPACE, instrumented, ZEND_FN(tally_hooks_instrumented), arginfo_bool)
	ZEND_NS_NAMED_FE(TALLY_HOOKS_NAMESPACE, start, ZEND_FN(tally_hooks_start), arginfo_void)
	ZEND_NS_NAMED_FE(TALLY_HOOKS_NAMESPACE, stop, ZEND_FN(tally_hooks_stop), arginfo_void)
	ZEND_NS_NAMED_FE(TALLY_HOOKS_NAMESPACE, toggle_collect, ZEND_FN(tally_hooks_toggle_collect), arginfo_void)
	ZEND_NS_NAMED_FE(TALLY_HOOKS_NAMESPACE, dump, ZEND_FN(tally_hooks_dump), arginfo_dump)
#ifdef TALLY_HOOKS_RECORDING
	ZEND_NS_NAMED_FE(TALLY_HOOKS_NAMESPACE, recorded_calls, ZEND_FN(tally_hooks_recorded_calls), arginfo_string)
#endif
	ZEND_FE_END
};

PHP_MINFO_FUNCTION(tally_hooks)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "tally_hooks", TALLY_HOOKS_VERSION);
#ifdef TALLY_HOOKS_RECORDING
	php_info_print_table_row(2, "Recording calls", "yes");
#endif
	php_info_print_table_end();
}

zend_module_entry tally_hooks_module_entry = {
	STANDARD_MODULE_HEADER,
	"tally_hooks",
	tally_hooks_functions,
	NULL,
	NULL,
	NULL,
	NULL,
	PHP_MINFO(tally_hooks),
	TALLY_HOOKS_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_TALLY_HOOKS
ZEND_GET_MODULE(tally_hooks)
#endif
