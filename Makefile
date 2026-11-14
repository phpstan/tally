.PHONY: check
check: lint cs tests phpstan coverage infection

# The tests run with the recording build of tally_hooks, which records the calls instead of making
# Callgrind's client requests, loaded from an ini file that PHP scans after its own.
RECORDING := tmp/tally-hooks-recording
tests coverage infection: export PHP_INI_SCAN_DIR := :$(CURDIR)/$(RECORDING)/ini

.PHONY: tests
tests: $(RECORDING)/ini/tally_hooks.ini
	php vendor/bin/phpunit

$(RECORDING)/ini/tally_hooks.ini: $(RECORDING)/tally_hooks.so
	mkdir -p $(@D)
	echo "extension=$(CURDIR)/$<" > $@

$(RECORDING)/tally_hooks.so: build/build-tally-hooks.sh $(wildcard ext/tally_hooks/*)
	CFLAGS=-DTALLY_HOOKS_RECORDING build/build-tally-hooks.sh $@

.PHONY: coverage
coverage: $(RECORDING)/ini/tally_hooks.ini
	php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover tmp/clover.xml --coverage-text --only-summary-for-coverage-text
	php build/check-coverage.php tmp/clover.xml

.PHONY: infection
infection: $(RECORDING)/ini/tally_hooks.ini
	# Infection's first PHPStan run fails on a stale result cache of the dead code detector
	php vendor/bin/phpstan clear-result-cache -q
	php -d pcov.enabled=1 vendor/bin/infection --threads=max --show-mutations=max $(INFECTION_ARGS)

.PHONY: bench
bench: codspeed
	php bin/tally run --output=tmp/tally-results

# CodSpeed's Valgrind and tally_hooks for this platform and PHP, which the release branch commits
CODSPEED := codspeed/linux-$(shell uname -m)
PHP_BUILD := $(shell php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION, ZEND_THREAD_SAFE ? "-zts" : "", ZEND_DEBUG_BUILD ? "-debug" : "";')

.PHONY: codspeed
codspeed: $(CODSPEED)/valgrind $(CODSPEED)/tally_hooks-$(PHP_BUILD).so

$(CODSPEED)/valgrind: build/build-codspeed.sh
	build/build-codspeed.sh

$(CODSPEED)/tally_hooks-%.so: build/build-tally-hooks.sh $(wildcard ext/tally_hooks/*)
	build/build-tally-hooks.sh $@

.PHONY: lint
lint:
	php vendor/bin/parallel-lint --colors bin/tally \
		--exclude tests/Fixtures/Loading/Syntax \
		bench build examples src tests tally.php

.PHONY: cs-install
cs-install:
	git clone https://github.com/phpstan/build-cs.git || true
	git -C build-cs fetch origin && git -C build-cs reset --hard origin/2.x
	composer install --working-dir build-cs

.PHONY: cs
cs:
	php build-cs/vendor/bin/phpcs bin/tally bench build examples src tests tally.php

.PHONY: cs-fix
cs-fix:
	php build-cs/vendor/bin/phpcbf bin/tally bench build examples src tests tally.php

.PHONY: phpstan
phpstan:
	php vendor/bin/phpstan
