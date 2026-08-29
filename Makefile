include .env
include .env.local

.DEFAULT_GOAL := help

# -----------------------------------------------------------------------------
# Project configuration
# -----------------------------------------------------------------------------

COMPOSE_DEV := docker compose \
	-p $(APP_NAME)-dev \
	--env-file .env \
	--env-file .env.local

COMPOSE_PROD := docker compose \
	-p $(APP_NAME)-prod \
	--env-file .env \
	--env-file .env.prod \
	-f compose.yaml \
	-f compose.prod.yaml

PHP_SERVICE := php
NODE_SERVICE := node
DB_SERVICE := mariadb

DEV_PHP_EXEC := $(COMPOSE_DEV) exec $(PHP_SERVICE)
DEV_NODE_EXEC := $(COMPOSE_DEV) exec $(NODE_SERVICE)
DEV_DB_EXEC := $(COMPOSE_DEV) exec $(DB_SERVICE)

PROD_PHP_EXEC := $(COMPOSE_PROD) exec -u www-data $(PHP_SERVICE)

TEST_DB_NAME = $(DB_NAME)_test

DEV_UP_FLAGS ?=
PROD_UP_FLAGS ?=

SERVICE ?=

RMI_MODE ?= local

CHECK_PATH ?=
TEST_PATH ?=

# -----------------------------------------------------------------------------
# Docker image tagging
# -----------------------------------------------------------------------------

# Set image tag to short Git SHA if the invoked command starts with "prod-"
ifneq ($(filter prod-%,$(MAKECMDGOALS)),)
    export IMAGE_TAG := $(shell git rev-parse --short HEAD 2>/dev/null || echo "latest")
endif

# -----------------------------------------------------------------------------
# Terminal colors & symbols
# -----------------------------------------------------------------------------

COLOR_BLUE   := \033[34m
COLOR_CYAN   := \033[36m
COLOR_GREEN  := \033[32m
COLOR_RED    := \033[31m
COLOR_YELLOW := \033[33m
COLOR_RESET  := \033[0m

ICON_SUCCESS := ✔
ICON_INFO    := ▶
ICON_WARNING := !
ICON_ERROR   := ✘
ICON_STEP    := ➜

MARK_CHECK   := $(COLOR_GREEN)$(ICON_SUCCESS)$(COLOR_RESET)
MARK_INFO    := $(COLOR_BLUE)$(ICON_INFO)$(COLOR_RESET)
MARK_WARNING := $(COLOR_YELLOW)$(ICON_WARNING)$(COLOR_RESET)
MARK_CROSS   := $(COLOR_RED)$(ICON_ERROR)$(COLOR_RESET)
MARK_ARROW   := $(COLOR_CYAN)$(ICON_STEP)$(COLOR_RESET)

UI_SEPARATOR := ================================================================================

# -----------------------------------------------------------------------------
# Helper macros
# -----------------------------------------------------------------------------

define check_dev_running
	${COMPOSE_DEV} ps --status running -q | grep -q .
endef

define load-test-env
	$(eval include .env.test.local)
	$(eval export)
endef

define ui_msg
	printf "%b %s\n" "$(1)" "$(2)"
endef

define ui_msg_success
	$(call ui_msg,$(MARK_CHECK),$(1))
endef

define ui_msg_error
	$(call ui_msg,$(MARK_CROSS),$(1))
endef

define ui_msg_info
	$(call ui_msg,$(MARK_INFO),$(1))
endef

define ui_msg_warning
	$(call ui_msg,$(MARK_WARNING),$(1))
endef

define ui_msg_step
	$(call ui_msg,$(MARK_ARROW),$(1))
endef

define ui_separator
	printf "%s\n" "$(UI_SEPARATOR)"
endef

define ui_banner
	$(call ui_separator)
	$(1)
	$(call ui_separator)
endef

# -----------------------------------------------------------------------------
# Phony targets
# -----------------------------------------------------------------------------

.PHONY: help \
	compose-dev compose-prod \
	composer-install \
	yarn-install \
	dev-build dev-up dev-down \
	dev-assets \
	dev-db-client dev-db-migrate \
	dev-cache-clear \
	dev-prepare-app dev-destroy \
	test-db-create test-run \
	prod-build prod-up prod-down \
	prod-logs \
	prod-db-migrate \
	prod-cache-clear \
	prod-deploy prod-destroy \
	prod-image-prune \
	code-check code-fix \
	_confirm

# -----------------------------------------------------------------------------
# Commands
# -----------------------------------------------------------------------------

_confirm:
	@read -p "Are you sure you want to proceed? [y/N] " ans; \
	if [ "$$ans" != "y" ] && [ "$$ans" != "Y" ]; then \
		printf "$(MARK_CROSS) Action cancelled\n"; \
		exit 1; \
	fi

##@ General

help: ## Displays a list of available commands
	@printf "Available commands:\n"
	@awk \
		-v section_color="$(COLOR_BLUE)" \
		-v command_color="$(COLOR_CYAN)" \
		-v reset="$(COLOR_RESET)" \
		'BEGIN {FS = ":.*?##"} \
		/^##@/ { \
			printf "\n%s%s%s\n", section_color, substr($$0, 5), reset \
		} \
		/^[a-zA-Z_-]+:.*?##/ { \
			printf "  %s%-25s%s %s\n", command_color, $$1, reset, $$2 \
		}' \
		$(MAKEFILE_LIST)

##@ Docker Compose

compose-dev: ## Runs Docker Compose commands with development environment configuration (use CMD="command [flags]")
	@$(COMPOSE_DEV) $(CMD)

compose-prod: ## Runs Docker Compose commands with production environment configuration (use CMD="command [flags]")
	@$(COMPOSE_PROD) $(CMD)

##@ Development

dev-assets: ## Builds frontend assets in development mode (supports WATCH=true)
	@$(DEV_NODE_EXEC) yarn $(if $(filter true,$(WATCH)),watch,dev)

dev-build: ## Builds Docker images (use NO_CACHE=true to disable the build cache)
	@$(COMPOSE_DEV) build $(if $(filter true,$(NO_CACHE)),--no-cache,)

dev-cache-clear: ## Clears the Symfony development cache
	@$(DEV_PHP_EXEC) php bin/console cache:clear

dev-db-client: ## Opens the MariaDB client in the database container as root
	@$(DEV_DB_EXEC) mariadb -uroot -p$(DB_ROOT_PASSWORD)

dev-db-migrate: ## Runs database migrations for the dev environment
	@$(DEV_PHP_EXEC) php bin/console doctrine:migrations:migrate --env=dev --no-interaction

dev-destroy: ## Destroys development environment: images, volumes, networks (set RMI_MODE=all to remove third-party images too)
	@$(check_dev_running) || ($(call ui_msg_error,ERROR: Development environment must be RUNNING); exit 1;)
	@$(call ui_banner,$(call ui_msg_warning,WARNING: You are about to completely remove the DEVELOPMENT environment (images, volumes, networks)))

	@$(MAKE) _confirm && \
	$(COMPOSE_DEV) down --volumes --rmi $(RMI_MODE)

dev-down: ## Stops and removes Docker containers
	@$(COMPOSE_DEV) down

dev-prepare-app: ## Prepares the development environment (use NO_CACHE=true to disable the build cache)
	@$(call ui_msg_info,Preparing development environment...)

	$(MAKE) dev-build NO_CACHE=$(or $(NO_CACHE),false)
	@$(call ui_msg_success,Docker images built)

	$(MAKE) DEV_UP_FLAGS=--wait dev-up
	@$(call ui_msg_success,Docker containers started)

	$(MAKE) composer-install
	@$(call ui_msg_success,Composer dependencies installed)

	$(MAKE) yarn-install
	@$(call ui_msg_success,Node dependencies installed)

	$(MAKE) dev-assets WATCH=false
	@$(call ui_msg_success,Assets built)

	$(MAKE) dev-db-migrate
	@$(call ui_msg_success,Dev database migrated)

	$(MAKE) test-db-create
	@$(call ui_msg_success,Test database created)

	@$(call ui_banner,$(call ui_msg_success,Development environment ready for use))

dev-up: ## Starts Docker containers
	@$(COMPOSE_DEV) up -d $(DEV_UP_FLAGS)

composer-install: ## Installs PHP dependencies inside container
	@$(DEV_PHP_EXEC) composer install

yarn-install: ## Installs Node dependencies inside container
	@$(DEV_NODE_EXEC) yarn install

##@ Test

test-db-create: ## Creates a test database
	$(call load-test-env)

	@$(DEV_DB_EXEC) mariadb -uroot -p$(DB_ROOT_PASSWORD) -e "\
		CREATE DATABASE IF NOT EXISTS $(TEST_DB_NAME); \
		GRANT ALL PRIVILEGES ON $(TEST_DB_NAME).* TO '$(DB_USER)'@'%'; \
		FLUSH PRIVILEGES; \
	"

test-run: ## Runs the full PHPUnit test suite (use TEST_PATH=path for specific paths, FILTER=name for specific tests)
	@$(DEV_PHP_EXEC) php ./vendor/bin/phpunit --testdox $(if $(FILTER),--filter '$(FILTER)',) $(TEST_PATH)

##@ Production

prod-build: ## Builds production Docker images (use NO_CACHE=true to disable the build cache)
	@$(COMPOSE_PROD) build $(if $(filter true,$(NO_CACHE)),--no-cache,)

prod-cache-clear: ## Clears the Symfony production cache
	@$(PROD_PHP_EXEC) php bin/console cache:clear --env=prod

prod-db-migrate: ## Runs production database migrations
	@$(PROD_PHP_EXEC) php bin/console doctrine:migrations:migrate --no-interaction --env=prod

prod-deploy: ## Deploys the application to production (use NO_CACHE=true to disable the build cache)
	@$(call ui_banner,$(call ui_msg_info,ATTENTION: You are about to deploy the application to production))

	@$(MAKE) _confirm
	@$(call ui_msg_info,Starting production deployment...)

	$(MAKE) prod-build NO_CACHE=$(or $(NO_CACHE),false)
	@$(call ui_msg_success,Docker production images built)

	$(MAKE) PROD_UP_FLAGS=--wait prod-up
	@$(call ui_msg_success,Docker production containers started)

	$(MAKE) prod-db-migrate
	@$(call ui_msg_success,Prod database migrated)

	@printf "\n"
	@$(call ui_banner,$(call ui_msg_success,Full production deployment was successful))

prod-destroy: ## Destroys production environment: images, volumes, networks (set RMI_MODE=all to remove third-party images too)
	@$(call ui_banner,$(call ui_msg_warning,WARNING: You are about to completely remove the PRODUCTION environment (images, volumes, networks)))

	@$(MAKE) _confirm && \
	$(COMPOSE_PROD) down --volumes --rmi $(RMI_MODE)

prod-down: ## Stops and removes production containers
	@$(call ui_banner,$(call ui_msg_warning,WARNING: You are about to take the production application OFFLINE))

	@$(MAKE) _confirm && \
	$(COMPOSE_PROD) down

prod-image-prune: ## Clean all unused production images from disk
	@$(call ui_banner,$(call ui_msg_warning,WARNING: You are about to clean all unused PRODUCTION images))

	@$(MAKE) _confirm && \
	IMAGES=$$(docker images --filter "reference=$(APP_NAME)-prod-*" --quiet); \
	if [ -n "$$IMAGES" ]; then \
		$(call ui_msg_step,Cleaning non-active production images for $(APP_NAME)...); \
		docker image rm $$IMAGES --force 2>/dev/null || true; \
		$(call ui_msg_success,Cleanup finished. Active images were preserved); \
	else \
		$(call ui_msg_error,No production images found); \
	fi

prod-logs: ## Follows production logs (all services or specific one by adding SERVICE=service_name)
	@$(COMPOSE_PROD) logs -f $(SERVICE)

prod-up: ## Starts production containers in the background
	@$(COMPOSE_PROD) up -d $(PROD_UP_FLAGS)

##@ Code quality

code-check: ## Runs PHP_CodeSniffer to detect violations of a defined coding standard (use CHECK_PATH=path to check files from specific path)
	@$(COMPOSE_DEV) exec php ./vendor/bin/phpcs $(CHECK_PATH)

code-fix: ## Runs PHP_CodeSniffer to automatically correct coding standard violations (use CHECK_PATH=path to correct files from specific path)
	@$(COMPOSE_DEV) exec php ./vendor/bin/phpcbf $(CHECK_PATH)
