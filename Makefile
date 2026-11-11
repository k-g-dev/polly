include .env
include .env.local

.DEFAULT_GOAL := help

MAKEFLAGS += --no-print-directory

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

DEV_EXEC := $(COMPOSE_DEV) exec

DEV_PHP_EXEC := $(DEV_EXEC) $(PHP_SERVICE)
DEV_NODE_EXEC := $(DEV_EXEC) $(NODE_SERVICE)
DEV_DB_EXEC := $(DEV_EXEC) $(DB_SERVICE)

PROD_PHP_EXEC := $(COMPOSE_PROD) exec -u www-data $(PHP_SERVICE)

TEST_DB_NAME = $(DB_NAME)_test

RMI_MODE ?= local

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
COLOR_GRAY   := \033[90m
COLOR_GREEN  := \033[32m
COLOR_RED    := \033[31m
COLOR_YELLOW := \033[33m
COLOR_RESET  := \033[0m

ICON_SUCCESS := ✔
ICON_INFO    := ▶
ICON_WARNING := !
ICON_ERROR   := ✘
ICON_STEP    := ➜
ICON_POINT   := •

MARK_CHECK    := $(COLOR_GREEN)$(ICON_SUCCESS)$(COLOR_RESET)
MARK_INFO     := $(COLOR_BLUE)$(ICON_INFO)$(COLOR_RESET)
MARK_WARNING  := $(COLOR_YELLOW)$(ICON_WARNING)$(COLOR_RESET)
MARK_CROSS    := $(COLOR_RED)$(ICON_ERROR)$(COLOR_RESET)
MARK_ARROW    := $(COLOR_CYAN)$(ICON_STEP)$(COLOR_RESET)
MARK_SUBPOINT := $(COLOR_GRAY) $(ICON_POINT)$(COLOR_RESET)

UI_SEPARATOR := ================================================================================

# -----------------------------------------------------------------------------
# Helper macros
# -----------------------------------------------------------------------------

define check_dev_running
	${COMPOSE_DEV} ps --status running -q | grep -q .
endef

define load_test_env
	$(eval include .env.test.local)
	$(eval export)
endef

define _ui_msg
	printf "%b %s\n" "$(if $(MARK_$(1)),$(MARK_$(1)),>)" "$(2)"
endef

ui_msg_success    = $(call _ui_msg,CHECK,$(1))
ui_msg_info       = $(call _ui_msg,INFO,$(1))
ui_msg_warning    = $(call _ui_msg,WARNING,$(1))
ui_msg_error      = $(call _ui_msg,CROSS,$(1))
ui_msg_step       = $(call _ui_msg,ARROW,$(1))
ui_msg_subpoint   = $(call _ui_msg,SUBPOINT,$(1))

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
	composer-install composer-update \
	yarn-install \
	dev-build dev-up dev-down \
	dev-start dev-stop \
	dev-assets-serve dev-assets-stop \
	dev-logs \
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
		} \
		/^##[[:space:]]+/ { \
			printf "                           %s\n", substr($$0, 4) \
		}' \
		$(MAKEFILE_LIST)

##@ Docker Compose

compose-dev: ## Runs Docker Compose commands with development environment configuration (use CMD="command [flags]")
	@$(COMPOSE_DEV) $(CMD)

compose-prod: ## Runs Docker Compose commands with production environment configuration (use CMD="command [flags]")
	@$(COMPOSE_PROD) $(CMD)

##@ Development

dev-assets-serve: ## Serves frontend assets for development (use DETACHED=true to run in detached mode)
ifeq ($(filter true,$(DETACHED)),true)
	@NODE_COMMAND="yarn dev" $(MAKE) dev-up FLAGS=--force-recreate SERVICES=$(NODE_SERVICE)
	@$(call ui_msg_info,Frontend assets served in detached mode)
	@$(call ui_msg_subpoint,Use make dev-logs SERVICES=node to get insight into frontend assets logs)
	@$(call ui_msg_subpoint,Use make dev-assets-stop to stop serving frontend assets)
else
	@$(MAKE) dev-up FLAGS=--force-recreate SERVICES=$(NODE_SERVICE)
	@$(DEV_NODE_EXEC) yarn dev
endif

dev-assets-stop: ## Stops serving frontend assets for development
	@$(MAKE) dev-up FLAGS=--force-recreate SERVICES=$(NODE_SERVICE)

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

dev-down: ## Stops and removes development containers and networks
	@$(COMPOSE_DEV) down

dev-logs: ## Streams development logs (all services or specific ones via SERVICES=service_names)
	@$(COMPOSE_DEV) logs -f $(SERVICES)

dev-prepare-app: ## Prepares the development environment (use NO_CACHE=true to disable the build cache)
	@$(call ui_msg_info,Preparing development environment...)

	$(MAKE) dev-build NO_CACHE=$(or $(NO_CACHE),false)
	@$(call ui_msg_success,Docker images built)

	$(MAKE) dev-up FLAGS=--wait INTERNAL=true
	@$(call ui_msg_success,Docker containers started)

	$(MAKE) composer-install
	@$(call ui_msg_success,Composer dependencies installed)

	$(MAKE) yarn-install
	@$(call ui_msg_success,Node dependencies installed)

	$(MAKE) dev-assets-serve DETACHED=true
	@$(call ui_msg_success,Frontend assets served)

	$(MAKE) dev-db-migrate
	@$(call ui_msg_success,Dev database migrated)

	$(MAKE) test-db-create
	@$(call ui_msg_success,Test database created)

	@$(call ui_banner,$(call ui_msg_success,Development environment ready for use))

dev-up: ## Builds and starts Docker containers in the background (use SERVICES=service_names to specify selected services,
##   FLAGS=options to pass extra flags)
	@$(COMPOSE_DEV) up -d $(FLAGS) $(SERVICES)

	@$(if $(filter true,$(INTERNAL))$(SERVICES),,$(MAKE) dev-assets-serve DETACHED=true)

dev-start: ## Starts stopped Docker containers without recreating (use SERVICES=service_names to specify selected services)
	@$(COMPOSE_DEV) start $(SERVICES)

dev-stop: ## Stops Docker containers (use SERVICES=service_names to specify selected services)
	@$(COMPOSE_DEV) stop $(SERVICES)

composer-install: ## Installs PHP dependencies inside container
	@$(DEV_PHP_EXEC) composer install

composer-update: ## Updates PHP dependencies inside container (optionally specify DEPENDENCY=vendor/package)
	@$(DEV_PHP_EXEC) composer update $(DEPENDENCY)

yarn-install: ## Installs Node dependencies inside container
	@$(DEV_NODE_EXEC) yarn install

##@ Test

test-db-create: ## Creates a test database
	$(call load_test_env)

	@$(DEV_DB_EXEC) mariadb -uroot -p$(DB_ROOT_PASSWORD) -e "\
		CREATE DATABASE IF NOT EXISTS $(TEST_DB_NAME); \
		GRANT ALL PRIVILEGES ON $(TEST_DB_NAME).* TO '$(DB_USER)'@'%'; \
		FLUSH PRIVILEGES; \
	"

test-run: ## Runs the full PHPUnit test suite (use TEST_PATH=path for specific paths, FILTER=name for specific tests,
##   SHOW_ISSUES=true to display all triggered issues, STOP_ON_FAILURE=true to stop after first failure).
	@$(DEV_PHP_EXEC) php ./vendor/bin/phpunit --testdox \
		$(if $(filter true,$(SHOW_ISSUES)),--display-all-issues,) \
		$(if $(filter true,$(STOP_ON_FAILURE)),--stop-on-failure,) \
		$(if $(FILTER),--filter '$(FILTER)',) \
		$(TEST_PATH)

##@ Production

prod-build: ## Builds production Docker images (set NO_CACHE=true to disable the build cache)
	@$(COMPOSE_PROD) build $(if $(filter true,$(NO_CACHE)),--no-cache,)

prod-cache-clear: ## Clears the Symfony production cache
	@$(PROD_PHP_EXEC) php bin/console cache:clear --env=prod

prod-db-migrate: ## Runs production database migrations
	@$(PROD_PHP_EXEC) php bin/console doctrine:migrations:migrate --no-interaction --env=prod

prod-deploy: ## Deploys the application to production (set NO_CACHE=true to disable the build cache)
	@$(call ui_banner,$(call ui_msg_info,ATTENTION: You are about to deploy the application to production))

	@$(MAKE) _confirm
	@$(call ui_msg_info,Starting production deployment...)

	$(MAKE) prod-build NO_CACHE=$(or $(NO_CACHE),false)
	@$(call ui_msg_success,Docker production images built)

	$(MAKE) prod-up FLAGS=--wait
	@$(call ui_msg_success,Docker production containers started)

	$(MAKE) prod-db-migrate
	@$(call ui_msg_success,Prod database migrated)

	@printf "\n"
	@$(call ui_banner,$(call ui_msg_success,Full production deployment was successful))

prod-destroy: ## Destroys production environment: images, volumes, networks (set RMI_MODE=all to remove third-party images too)
	@$(call ui_banner,$(call ui_msg_warning,WARNING: You are about to completely remove the PRODUCTION environment (images, volumes, networks)))

	@$(MAKE) _confirm && \
	$(COMPOSE_PROD) down --volumes --rmi $(RMI_MODE)

prod-down: ## Stops and removes production containers and networks
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

prod-logs: ## Streams production logs (all services or specific ones via SERVICES=service_names)
	@$(COMPOSE_PROD) logs -f $(SERVICES)

prod-up: ## Starts production containers in the background
	@$(COMPOSE_PROD) up -d $(FLAGS)

##@ Code quality

code-check: ## Runs PHP_CodeSniffer to detect violations of a defined coding standard (use CHECK_PATH=path to check files from specific path)
	@DOCKER_CLI_HINTS=0 $(DEV_PHP_EXEC) ./vendor/bin/phpcs $(CHECK_PATH)

code-fix: ## Runs PHP_CodeSniffer to automatically correct coding standard violations (use CHECK_PATH=path to correct files from specific path)
	@$(DEV_PHP_EXEC) ./vendor/bin/phpcbf $(CHECK_PATH)
