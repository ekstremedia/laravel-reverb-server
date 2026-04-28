SHELL := /bin/bash
COMPOSE := docker compose
APP_PORT ?= 8120
REVERB_PORT ?= 8080

.DEFAULT_GOAL := help

.PHONY: help up down restart build rebuild logs logs-app logs-reverb shell tinker migrate seed fresh test pint ping stats restart-reverb clean info status

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

up: .env ## Build images, start the stack, migrate, seed, print info
	$(COMPOSE) up -d --build
	@$(MAKE) --no-print-directory _wait
	@$(MAKE) --no-print-directory _bootstrap
	@$(MAKE) --no-print-directory info

down: ## Stop the stack
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

build: ## Build images
	$(COMPOSE) build

rebuild: ## Rebuild images without cache
	$(COMPOSE) build --no-cache

logs: ## Tail combined logs
	$(COMPOSE) logs -f --tail=200

logs-app: ## Tail app logs
	$(COMPOSE) logs -f --tail=200 app

logs-reverb: ## Tail reverb logs
	$(COMPOSE) logs -f --tail=200 reverb

shell: ## Bash into app container
	$(COMPOSE) exec app bash

tinker: ## Open Laravel tinker
	$(COMPOSE) exec app php artisan tinker

migrate: ## Run pending migrations
	$(COMPOSE) exec app php artisan migrate --force

seed: ## Run database seeders
	$(COMPOSE) exec app php artisan db:seed --force

fresh: ## Drop, migrate, and seed
	$(COMPOSE) exec app php artisan migrate:fresh --seed --force

test: ## Run pest tests in container
	$(COMPOSE) exec app php artisan test --compact

pint: ## Run pint formatter
	$(COMPOSE) exec app vendor/bin/pint --format agent

ping: ## Broadcast a test ping event
	$(COMPOSE) exec app php artisan reverb:ping

stats: ## Show websocket counters
	$(COMPOSE) exec app php artisan reverb:stats

restart-reverb: ## Gracefully restart the reverb websocket server
	$(COMPOSE) exec app php artisan reverb:restart

status: ## Show running services
	$(COMPOSE) ps

clean: ## Stop the stack and remove volumes (deletes the SQLite db)
	$(COMPOSE) down -v

info: ## Print the ready banner with copy-paste .env block
	@./scripts/print-info.sh

# --- internal targets -------------------------------------------------------

.env:
	@echo "→ Copying .env.example to .env"
	@cp .env.example .env
	@$(COMPOSE) run --rm --no-deps app php artisan key:generate --force

_wait:
	@printf "→ Waiting for app container "
	@for i in $$(seq 1 30); do \
		if $(COMPOSE) exec -T app php -r "exit(0);" >/dev/null 2>&1; then \
			echo " ready"; exit 0; \
		fi; \
		printf "."; sleep 1; \
	done; \
	echo " timed out"; exit 1

_bootstrap:
	@$(COMPOSE) exec -T app sh -c 'touch /app/database/database.sqlite && chown www-data:www-data /app/database/database.sqlite'
	@$(COMPOSE) exec -T app php artisan migrate --force
	@$(COMPOSE) exec -T app php artisan db:seed --force
