.DEFAULT_GOAL := help
.PHONY: help up down logs sh install migrate migration test-db cs cs-fix stan deptrac rector rector-fix test test-unit test-integration test-functional infection audit qa

MAKEFLAGS += --no-print-directory
PHP := docker compose exec php

export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-18s %s\n", $$1, $$2}'

up: ## Build and start the stack, wait until healthy
	docker compose up --detach --build --wait

down: ## Stop and remove the stack
	docker compose down --remove-orphans

logs: ## Follow the logs
	docker compose logs --follow

sh: ## Open a shell in the php container
	docker compose exec php bash

install: ## Install Composer dependencies
	docker compose exec php composer install

migrate: ## Run pending database migrations
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# A module owns its tables, its schema and its migrations. The filter limits the
# diff to that schema (Leads -> leads, CustomerCare -> customer_care), so a pending
# change in another module never leaks into this module's migration.
MODULE_SCHEMA = $(shell echo '$(MODULE)' | sed -E 's/([a-z0-9])([A-Z])/\1_\2/g' | tr '[:upper:]' '[:lower:]')
MODULE_MIGRATIONS = src/$(MODULE)/Infrastructure/Persistence/Migrations

migration: ## Generate a migration for one module from its mapping changes (MODULE=Name)
	@test -n '$(MODULE)' || { echo 'Usage: make migration MODULE=<Module>'; exit 1; }
	@test -d '$(MODULE_MIGRATIONS)' || { echo 'No migrations directory: $(MODULE_MIGRATIONS)'; exit 1; }
	$(PHP) bin/console doctrine:migrations:diff --namespace='App\$(MODULE)\Infrastructure\Persistence\Migrations' --filter-expression='~^$(MODULE_SCHEMA)\.~'
	$(PHP) vendor/bin/php-cs-fixer fix $(MODULE_MIGRATIONS) --quiet

cs: ## Check code style (PHP-CS-Fixer dry run)
	$(PHP) vendor/bin/php-cs-fixer check --diff --show-progress=none

cs-fix: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

stan: ## Run PHPStan
	$(PHP) bin/console cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

deptrac: ## Check layer and module boundaries
	$(PHP) vendor/bin/deptrac analyse --config-file=deptrac.php --no-progress --fail-on-uncovered

rector: ## Show pending Rector changes (dry run)
	$(PHP) vendor/bin/rector process --dry-run --no-progress-bar

rector-fix: ## Apply Rector changes
	$(PHP) vendor/bin/rector process --no-progress-bar

test: test-db ## Run all test suites (FILTER=name, FILE=path)
	$(PHP) vendor/bin/phpunit $(if $(FILTER),--filter '$(FILTER)') $(FILE)

test-unit: ## Run the unit suite
	$(PHP) vendor/bin/phpunit --testsuite unit

test-integration: test-db ## Run the integration suite
	$(PHP) vendor/bin/phpunit --testsuite integration

test-functional: test-db ## Run the functional suite
	$(PHP) vendor/bin/phpunit --testsuite functional

# Tests run against app_test, whose schema comes from the same migrations.
test-db:
	$(PHP) bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration --quiet

# The src/ path filter matches every source file, so it changes nothing once code
# exists. It is here because Infection fails on an empty source set unless a
# filter is active. Remove it, and ignoreMsiWithNoMutations in infection.json5,
# with the first Domain or Application class that has logic to mutate, so an
# empty mutation run fails again.
infection: ## Run mutation testing on Domain and Application
	docker compose exec -e XDEBUG_MODE=coverage php vendor/bin/infection --threads=max --no-progress --show-mutations --with-uncovered src/

audit: ## Audit Composer dependencies for known vulnerabilities
	$(PHP) composer audit

qa: ## Full quality gate: cs, rector, stan, deptrac, test
	$(MAKE) cs
	$(MAKE) rector
	$(MAKE) stan
	$(MAKE) deptrac
	$(MAKE) test
