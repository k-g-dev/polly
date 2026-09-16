# POLLY

An application for conducting surveys.

## For developers

This project is built using **Symfony** and containerized with **Docker Compose** using multi-stage architecture.

### Command execution

#### Makefile

For convenience, the most frequently used commands are configured for use in the `Makefile`.

Run `make help` to display a list of all available commands.

#### Manual Docker Compose usage

For operations not covered by the `Makefile` or when direct execution is preferred, `docker compose` commands can be executed manually.

Make sure to include:

- The project name with the appropriate environment suffix (`-p <project-name>` e.g., `polly-dev` or `polly-prod`)
- The correct environment files (`--env-file <file-1> --env-file <file-2>`)
- All required compose files (`-f <file-1> -f <file-2>`)

For reference, please check the `Makefile` to see how `COMPOSE_DEV` and `COMPOSE_PROD` are constructed:

**`COMPOSE_DEV` - for the development environment**
```bash
docker compose -p polly-dev --env-file .env --env-file .env.local <command>
```

**`COMPOSE_PROD` - for the production environment**
```bash
docker compose -p polly-prod --env-file .env --env-file .env.prod -f compose.yaml -f compose.prod.yaml <command>
```

### Development environment

The development environment mounts the project source code as a local volume for live-reloads.

#### Environment configuration

**Environment variables**

Create your local environment files:

```bash
cp .env.local.example .env.local
cp .env.test.local.example .env.test.local
```

Adjust the values in `.env.local` and `.env.test.local` before starting the development environment.

**Docker environment**

Create configuration file for the local development environment and adjust it as needed:
```bash
cp compose.override.yaml.example compose.override.yaml
```

#### 1. Initial setup

Run the preparation script to build the containers, install dependencies, set up the database, and run the database migrations:
```bash
make dev-prepare-app
```

Once the command completes, the development environment should be up and ready for use.

If the default configuration has been applied, the following are available at these addresses:
- Polly App
  http://localhost:8080
- phpMyAdmin
  http://localhost:8081
- Mailpit
  http://localhost:8025

#### 2. Daily operations
* **Start environment** (in background):
  ```bash
  make dev-up
  ```
* **Stop environment**:
  ```bash
  make dev-down
  ```
* **Install dependencies** (internally inside container context):
  ```bash
  make composer-install
  make yarn-install
  ```
* **Wipe out development data** (removes containers, networks, volumes, and local images):
  ```bash
  make dev-destroy
  ```

### Production environment

#### Environment configuration

**Environment variables**

Create a `.env.prod` file for the production environment and fill in the values:

```bash
cp .env.prod.example .env.prod
```

#### 1. Build the production environment locally

To build and start the production environment and run the database migrations, execute:
```bash
make prod-deploy
```

The application will be available at http://localhost

#### 2. Common operations
* **Start environment** (in background):
  ```bash
  make prod-up
  ```
* **Stop environment**:
  ```bash
  make prod-down
  ```
* **Destroy production environment** (removes containers, networks, volumes, and local images):
  ```bash
  make prod-destroy
  ```

## For users

### Configuration

#### Create an admin account

To create a user with the chosen roles run:

```bash
php bin/console app:user:create
```
