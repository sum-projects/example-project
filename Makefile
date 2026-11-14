.DEFAULT_GOAL := help
.PHONY: help up down logs sh install

export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-10s %s\n", $$1, $$2}'

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
