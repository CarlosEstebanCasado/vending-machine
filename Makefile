COMPOSE ?= docker compose

.PHONY: help install backend-install frontend-install backend-lint frontend-lint backend-test backend-test-bc frontend-test docker-up docker-down docker-build clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: backend-install frontend-install ## Install PHP and JS dependencies

backend-install: ## Install backend dependencies (Composer)
	@if [ -f backend/composer.json ]; then \
		$(COMPOSE) --profile dev run --rm backend composer install; \
	else \
		echo "backend/composer.json not found. Skipping backend install."; \
	fi

frontend-install: ## Install frontend dependencies (Node)
	@if [ -f frontend/package.json ]; then \
		$(COMPOSE) --profile dev run --rm frontend npm install; \
	else \
		echo "frontend/package.json not found. Skipping frontend install."; \
	fi

backend-lint: ## Run backend linters/static analysis
	@if [ -f backend/composer.json ]; then \
		$(COMPOSE) --profile dev run --rm backend composer run lint || true; \
	else \
		echo "backend/composer.json not found. Skipping backend lint."; \
	fi

frontend-lint: ## Run frontend linting
	@if [ -f frontend/package.json ]; then \
		$(COMPOSE) --profile dev run --rm frontend npm run lint || true; \
	else \
		echo "frontend/package.json not found. Skipping frontend lint."; \
	fi

backend-test: ## Execute backend test suite inside Docker
	$(COMPOSE) --profile dev run --rm backend ./vendor/bin/phpunit

backend-test-bc: ## Execute backend tests for a bounded context (CONTEXT=VendingMachine/Inventory)
	@if [ -z "$(CONTEXT)" ]; then \
		echo "Please provide CONTEXT=<relative test path, e.g. VendingMachine/Inventory or Unit/VendingMachine/Product>"; \
		exit 1; \
	fi
	$(COMPOSE) --profile dev run --rm backend ./vendor/bin/phpunit tests/$(CONTEXT)

frontend-test: ## Execute frontend test suite
	@if [ -f frontend/package.json ]; then \
		$(COMPOSE) --profile dev run --rm frontend npm test || true; \
	else \
		echo "frontend/package.json not found. Skipping frontend tests."; \
	fi

docker-up: ## Start the development stack (backend, frontend, mongo, redis)
	$(COMPOSE) --profile dev up --build -d

docker-down: ## Stop the development stack
	$(COMPOSE) --profile dev down

docker-build: ## Build development images
	$(COMPOSE) --profile dev build

docker-prod-up: ## Start the production stack
	$(COMPOSE) -f docker-compose.prod.yml up --build -d

docker-prod-down: ## Stop the production stack
	$(COMPOSE) -f docker-compose.prod.yml down

clean: ## Remove build artifacts and vendor directories
	rm -rf backend/vendor backend/var frontend/node_modules frontend/dist frontend/.vite
