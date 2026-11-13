.PHONY: check
check: lint cs tests phpstan coverage infection

.PHONY: tests
tests:
	php vendor/bin/phpunit

.PHONY: coverage
coverage:
	php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover tmp/clover.xml --coverage-text --only-summary-for-coverage-text
	php build/check-coverage.php tmp/clover.xml

.PHONY: infection
infection:
	# Infection's first PHPStan run fails on a stale result cache of the dead code detector
	php vendor/bin/phpstan clear-result-cache -q
	php -d pcov.enabled=1 vendor/bin/infection --threads=max --show-mutations=max $(INFECTION_ARGS)

.PHONY: lint
lint:
	php vendor/bin/parallel-lint --colors bin/tally \
		build src tests

.PHONY: cs-install
cs-install:
	git clone https://github.com/phpstan/build-cs.git || true
	git -C build-cs fetch origin && git -C build-cs reset --hard origin/2.x
	composer install --working-dir build-cs

.PHONY: cs
cs:
	php build-cs/vendor/bin/phpcs bin/tally build src tests

.PHONY: cs-fix
cs-fix:
	php build-cs/vendor/bin/phpcbf bin/tally build src tests

.PHONY: phpstan
phpstan:
	php vendor/bin/phpstan
