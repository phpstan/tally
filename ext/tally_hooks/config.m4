PHP_ARG_ENABLE([tally_hooks],
  [whether to enable tally_hooks],
  [AS_HELP_STRING([--enable-tally-hooks], [Enable Tally's hooks into Callgrind])],
  [no])

if test "$PHP_TALLY_HOOKS" != "no"; then
  PHP_NEW_EXTENSION(tally_hooks, tally_hooks.c, $ext_shared)
fi
