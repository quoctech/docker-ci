.PHONY: up down build install shell db logs restart prod-up prod-down prod-build prod-logs

# ==============================================================================
# DEVELOPMENT
# ==============================================================================

# Start all containers
up:
	docker compose up -d

# Stop all containers
down:
	docker compose down

# Build/rebuild containers
build:
	docker compose build --no-cache

# Install CodeIgniter 4 via Composer
install:
	docker compose exec app composer create-project codeigniter4/appstarter .

# Enter PHP container shell
shell:
	docker compose exec app bash

# Enter MariaDB shell
db:
	docker compose exec db mariadb -u ci4_user -pci4_pass ci4_db

# View logs
logs:
	docker compose logs -f

# Restart all containers
restart:
	docker compose restart

# Run composer commands
composer:
	docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS))

# Run CI4 spark commands
spark:
	docker compose exec app php spark $(filter-out $@,$(MAKECMDGOALS))

# ==============================================================================
# PRODUCTION
# ==============================================================================

# Build production images
prod-build:
	docker compose -f docker-compose.prod.yml --env-file .env.prod build --no-cache

# Start production
prod-up:
	docker compose -f docker-compose.prod.yml --env-file .env.prod up -d

# Stop production
prod-down:
	docker compose -f docker-compose.prod.yml --env-file .env.prod down

# Production logs
prod-logs:
	docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f

# Production status
prod-status:
	docker compose -f docker-compose.prod.yml --env-file .env.prod ps
