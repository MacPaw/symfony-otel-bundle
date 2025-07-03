# Makefile for Symfony OpenTelemetry Bundle
# 
# Quick commands to manage the Docker testing environment
# Run 'make help' to see all available commands

.PHONY: help start stop restart build clean logs test status shell grafana tempo
.DEFAULT_GOAL := help

# Colors for output
YELLOW := \033[1;33m
GREEN := \033[0;32m
RED := \033[0;31m
BLUE := \033[0;34m
NC := \033[0m # No Color

# Docker compose files
COMPOSE_FILE := docker-compose.yml
COMPOSE_OVERRIDE := docker-compose.override.yml

## Environment Management
start: ## Start the complete testing environment
	@echo "$(BLUE)🐳 Starting Symfony OpenTelemetry Bundle Test Environment$(NC)"
	@docker-compose up -d --build
	@echo "$(GREEN)✅ Environment started successfully!$(NC)"
	@echo "$(BLUE)🔗 Access Points:$(NC)"
	@echo "  📱 Test Application: http://localhost:8080"
	@echo "  📈 Grafana Dashboard: http://localhost:3000 (admin/admin)"
	@echo "  🔍 Tempo API: http://localhost:3200"
	@echo ""
	@echo "$(YELLOW)Run 'make test' to run sample tests$(NC)"

stop: ## Stop all services
	@echo "$(YELLOW)🛑 Stopping services...$(NC)"
	@docker-compose down
	@echo "$(GREEN)✅ Services stopped$(NC)"

restart: stop start ## Restart all services

build: ## Build/rebuild all services
	@echo "$(BLUE)🔨 Building services...$(NC)"
	@docker-compose build --no-cache
	@echo "$(GREEN)✅ Build completed$(NC)"

clean: ## Stop services and remove all containers, networks, and volumes
	@echo "$(RED)🧹 Cleaning up environment...$(NC)"
	@docker-compose down -v --rmi local --remove-orphans
	@docker system prune -f
	@echo "$(GREEN)✅ Cleanup completed$(NC)"

## Service Management
php-rebuild: ## Rebuild only the PHP container
	@echo "$(BLUE)🐘 Rebuilding PHP container...$(NC)"
	@docker-compose build php-app
	@docker-compose up -d php-app
	@echo "$(GREEN)✅ PHP container rebuilt$(NC)"

php-restart: ## Restart only the PHP application
	@echo "$(YELLOW)🔄 Restarting PHP application...$(NC)"
	@docker-compose restart php-app
	@echo "$(GREEN)✅ PHP application restarted$(NC)"

tempo-restart: ## Restart only Tempo service
	@echo "$(YELLOW)🔄 Restarting Tempo...$(NC)"
	@docker-compose restart tempo
	@echo "$(GREEN)✅ Tempo restarted$(NC)"

grafana-restart: ## Restart only Grafana service
	@echo "$(YELLOW)🔄 Restarting Grafana...$(NC)"
	@docker-compose restart grafana
	@echo "$(GREEN)✅ Grafana restarted$(NC)"

## Monitoring and Logs
status: ## Show status of all services
	@echo "$(BLUE)📊 Service Status:$(NC)"
	@docker-compose ps

logs: ## Show logs from all services
	@echo "$(BLUE)📋 Showing logs from all services:$(NC)"
	@docker-compose logs -f

logs-php: ## Show logs from PHP application only
	@echo "$(BLUE)📋 PHP Application Logs:$(NC)"
	@docker-compose logs -f php-app

logs-tempo: ## Show logs from Tempo only
	@echo "$(BLUE)📋 Tempo Logs:$(NC)"
	@docker-compose logs -f tempo

logs-grafana: ## Show logs from Grafana only
	@echo "$(BLUE)📋 Grafana Logs:$(NC)"
	@docker-compose logs -f grafana

logs-otel: ## Show OpenTelemetry related logs
	@echo "$(BLUE)📋 OpenTelemetry Logs:$(NC)"
	@docker-compose logs php-app | grep -i otel

## Testing Commands
test: ## Run all test endpoints
	@echo "$(BLUE)🧪 Running OpenTelemetry Bundle Tests$(NC)"
	@echo ""
	@echo "$(YELLOW)Testing basic tracing...$(NC)"
	@curl -s http://localhost:8080/api/test | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(YELLOW)Testing slow operation...$(NC)"
	@curl -s http://localhost:8080/api/slow | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(YELLOW)Testing nested spans...$(NC)"
	@curl -s http://localhost:8080/api/nested | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(YELLOW)Testing error handling...$(NC)"
	@curl -s http://localhost:8080/api/error | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(GREEN)✅ All tests completed!$(NC)"
	@echo "$(BLUE)💡 Check Grafana at http://localhost:3000 to view traces$(NC)"

test-basic: ## Test basic API endpoint
	@echo "$(BLUE)🧪 Testing basic API endpoint...$(NC)"
	@curl -s http://localhost:8080/api/test | jq .

test-slow: ## Test slow operation endpoint
	@echo "$(BLUE)🧪 Testing slow operation endpoint...$(NC)"
	@curl -s http://localhost:8080/api/slow | jq .

test-nested: ## Test nested spans endpoint
	@echo "$(BLUE)🧪 Testing nested spans endpoint...$(NC)"
	@curl -s http://localhost:8080/api/nested | jq .

test-error: ## Test error handling endpoint
	@echo "$(BLUE)🧪 Testing error handling endpoint...$(NC)"
	@curl -s http://localhost:8080/api/error | jq .

test-distributed: ## Test with distributed tracing headers
	@echo "$(BLUE)🧪 Testing distributed tracing...$(NC)"
	@curl -s -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01" \
		http://localhost:8080/api/test | jq .

## Load Testing
load-test: ## Run simple load test
	@echo "$(BLUE)🔄 Running load test (100 requests)...$(NC)"
	@for i in {1..100}; do \
		curl -s http://localhost:8080/api/test > /dev/null & \
		if [ $$(($${i} % 10)) -eq 0 ]; then echo "Sent $${i} requests..."; fi; \
	done; \
	wait
	@echo "$(GREEN)✅ Load test completed$(NC)"

## Access Commands
shell: ## Access PHP container shell
	@echo "$(BLUE)🐚 Accessing PHP container shell...$(NC)"
	@docker-compose exec php-app /bin/sh

shell-tempo: ## Access Tempo container shell
	@echo "$(BLUE)🐚 Accessing Tempo container shell...$(NC)"
	@docker-compose exec tempo /bin/sh

## Web Access
grafana: ## Open Grafana in browser
	@echo "$(BLUE)📈 Opening Grafana Dashboard...$(NC)"
	@open http://localhost:3000 || xdg-open http://localhost:3000 || echo "Open http://localhost:3000 in your browser"

app: ## Open test application in browser
	@echo "$(BLUE)📱 Opening Test Application...$(NC)"
	@open http://localhost:8080 || xdg-open http://localhost:8080 || echo "Open http://localhost:8080 in your browser"

tempo: ## Open Tempo API in browser
	@echo "$(BLUE)🔍 Opening Tempo API...$(NC)"
	@open http://localhost:3200 || xdg-open http://localhost:3200 || echo "Open http://localhost:3200 in your browser"

## Development Commands
dev: ## Start development environment with hot reload
	@echo "$(BLUE)🔧 Starting development environment...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) -f $(COMPOSE_OVERRIDE) up -d --build
	@echo "$(GREEN)✅ Development environment started with hot reload$(NC)"

composer-install: ## Install Composer dependencies
	@echo "$(BLUE)📦 Installing Composer dependencies...$(NC)"
	@docker-compose exec php-app composer install
	@echo "$(GREEN)✅ Dependencies installed$(NC)"

composer-update: ## Update Composer dependencies
	@echo "$(BLUE)🔄 Updating Composer dependencies...$(NC)"
	@docker-compose exec php-app composer update
	@echo "$(GREEN)✅ Dependencies updated$(NC)"

phpunit: ## Run PHPUnit tests
	@echo "$(BLUE)🧪 Running PHPUnit tests...$(NC)"
	@docker-compose exec php-app vendor/bin/phpunit
	@echo "$(GREEN)✅ PHPUnit tests completed$(NC)"

phpcs: ## Run PHP_CodeSniffer
	@echo "$(BLUE)🔍 Running PHP_CodeSniffer...$(NC)"
	@docker-compose exec php-app vendor/bin/phpcs
	@echo "$(GREEN)✅ PHP_CodeSniffer completed$(NC)"

phpcs-fix: ## Fix PHP_CodeSniffer issues
	@echo "$(BLUE)🔧 Fixing PHP_CodeSniffer issues...$(NC)"
	@docker-compose exec php-app vendor/bin/phpcbf
	@echo "$(GREEN)✅ PHP_CodeSniffer fixes applied$(NC)"

phpstan: ## Run PHPStan static analysis
	@echo "$(BLUE)🔍 Running PHPStan...$(NC)"
	@docker-compose exec php-app vendor/bin/phpstan analyse
	@echo "$(GREEN)✅ PHPStan completed$(NC)"

test-all: ## Run all tests (PHPUnit, PHPCS, PHPStan)
	@echo "$(BLUE)🧪 Running all tests...$(NC)"
	@docker-compose exec php-app composer test
	@echo "$(GREEN)✅ All tests completed$(NC)"

test-fix: ## Run tests with auto-fixing
	@echo "$(BLUE)🧪 Running tests with auto-fixing...$(NC)"
	@docker-compose exec php-app composer test-fix
	@echo "$(GREEN)✅ Tests with fixes completed$(NC)"

coverage: ## Generate code coverage report
	@echo "$(BLUE)📊 Generating code coverage report...$(NC)"
	@docker-compose exec php-app mkdir -p var/coverage/html
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html var/coverage/html --coverage-text
	@echo "$(GREEN)✅ Coverage report generated$(NC)"
	@echo "$(BLUE)📁 HTML report available at: var/coverage/html/index.html$(NC)"

coverage-text: ## Generate code coverage text report
	@echo "$(BLUE)📊 Generating text coverage report...$(NC)"
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text
	@echo "$(GREEN)✅ Text coverage report completed$(NC)"

coverage-clover: ## Generate code coverage clover XML report
	@echo "$(BLUE)📊 Generating clover coverage report...$(NC)"
	@docker-compose exec php-app mkdir -p var/coverage
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
	@echo "$(GREEN)✅ Clover coverage report generated$(NC)"
	@echo "$(BLUE)📁 Clover report available at: var/coverage/clover.xml$(NC)"

coverage-all: ## Generate all coverage reports
	@echo "$(BLUE)📊 Generating all coverage reports...$(NC)"
	@docker-compose exec php-app mkdir -p var/coverage/html var/coverage/xml
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html var/coverage/html --coverage-text --coverage-clover var/coverage/clover.xml --coverage-xml var/coverage/xml
	@echo "$(GREEN)✅ All coverage reports generated$(NC)"
	@echo "$(BLUE)📁 Reports available in: var/coverage/$(NC)"

coverage-open: coverage ## Generate coverage report and open in browser
	@echo "$(BLUE)🌐 Opening coverage report in browser...$(NC)"
	@open var/coverage/html/index.html || xdg-open var/coverage/html/index.html || echo "Open var/coverage/html/index.html in your browser"

## Debugging Commands
debug-otel: ## Debug OpenTelemetry configuration
	@echo "$(BLUE)🔍 OpenTelemetry Debug Information:$(NC)"
	@echo ""
	@echo "$(YELLOW)Environment Variables:$(NC)"
	@docker-compose exec php-app env | grep OTEL
	@echo ""
	@echo "$(YELLOW)PHP OpenTelemetry Extension:$(NC)"
	@docker-compose exec php-app php -m | grep -i otel
	@echo ""
	@echo "$(YELLOW)Tempo Health Check:$(NC)"
	@curl -s http://localhost:3200/ready || echo "Tempo not ready"
	@echo ""

debug-traces: ## Check if traces are being sent
	@echo "$(BLUE)🔍 Checking trace export...$(NC)"
	@echo "Making test request..."
	@curl -s http://localhost:8080/api/test > /dev/null
	@sleep 2
	@echo "Checking Tempo for traces..."
	@curl -s "http://localhost:3200/api/search?tags=service.name%3Dsymfony-otel-test" | jq '.traces // "No traces found"'

health: ## Check health of all services
	@echo "$(BLUE)🏥 Health Check:$(NC)"
	@echo ""
	@echo "$(YELLOW)PHP Application:$(NC)"
	@curl -s http://localhost:8080/ > /dev/null && echo "✅ OK" || echo "❌ Failed"
	@echo ""
	@echo "$(YELLOW)Tempo:$(NC)"
	@curl -s http://localhost:3200/ready > /dev/null && echo "✅ OK" || echo "❌ Failed"
	@echo ""
	@echo "$(YELLOW)Grafana:$(NC)"
	@curl -s http://localhost:3000/api/health > /dev/null && echo "✅ OK" || echo "❌ Failed"

## Utility Commands
urls: ## Show all available URLs
	@echo "$(BLUE)🔗 Available URLs:$(NC)"
	@echo "  📱 Test Application: http://localhost:8080"
	@echo "  📈 Grafana Dashboard: http://localhost:3000 (admin/admin)"
	@echo "  🔍 Tempo API: http://localhost:3200"
	@echo "  📊 Tempo Metrics: http://localhost:3200/metrics"
	@echo "  🔧 OpenTelemetry Collector: http://localhost:4320"

endpoints: ## Show all test endpoints
	@echo "$(BLUE)🧪 Test Endpoints:$(NC)"
	@echo "  GET  /                - Homepage with documentation"
	@echo "  GET  /api/test        - Basic tracing example"
	@echo "  GET  /api/slow        - Slow operation (2 seconds)"
	@echo "  GET  /api/nested      - Nested spans example"
	@echo "  GET  /api/error       - Error handling example"

help: ## Show this help message
	@echo "$(BLUE)🚀 Symfony OpenTelemetry Bundle - Available Commands$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(GREEN)%-18s$(NC) %s\n", $$1, $$2 } /^##@/ { printf "\n$(YELLOW)%s$(NC)\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
	@echo ""
	@echo "$(BLUE)💡 Quick Start:$(NC)"
	@echo "  make start     # Start the environment"
	@echo "  make test      # Run all tests"
	@echo "  make coverage  # Generate coverage report"
	@echo "  make grafana   # Open Grafana dashboard"
	@echo "  make stop      # Stop the environment"
	@echo "" 

validate-workflows: ## Validate GitHub Actions workflows
	@echo "$(BLUE)🔍 Validating GitHub Actions workflows...$(NC)"
	@command -v act >/dev/null 2>&1 || { echo "$(RED)❌ 'act' not found. Install with: brew install act$(NC)"; exit 1; }
	@act --list
	@echo "$(GREEN)✅ GitHub Actions workflows are valid$(NC)"

test-workflows: ## Test GitHub Actions workflows locally (requires 'act')
	@echo "$(BLUE)🧪 Testing GitHub Actions workflows locally...$(NC)"
	@command -v act >/dev/null 2>&1 || { echo "$(RED)❌ 'act' not found. Install with: brew install act$(NC)"; exit 1; }
	@act pull_request --artifact-server-path ./artifacts
	@echo "$(GREEN)✅ Local workflow testing completed$(NC)"

lint-yaml: ## Lint YAML files
	@echo "$(BLUE)🔍 Linting YAML files...$(NC)"
	@command -v yamllint >/dev/null 2>&1 || { echo "$(RED)❌ 'yamllint' not found. Install with: pip install yamllint$(NC)"; exit 1; }
	@find .github -name "*.yml" -o -name "*.yaml" | xargs yamllint
	@echo "$(GREEN)✅ YAML files are valid$(NC)"

security-scan: ## Run local security scanning
	@echo "$(BLUE)🔒 Running local security scan...$(NC)"
	@docker run --rm -v $(PWD):/workspace aquasec/trivy fs --security-checks vuln /workspace
	@echo "$(GREEN)✅ Security scan completed$(NC)"
