# Makefile for Symfony OpenTelemetry Bundle
#
# Quick commands to manage the Docker testing environment
# Run 'make help' to see all available commands

.PHONY: help start stop restart build clean logs test status shell grafana tempo
.DEFAULT_GOAL := help

# Load environment variables from .env if present
ifneq (,$(wildcard .env))
  include .env
  export
endif

# Default ports (can be overridden by .env)
APP_PORT ?= 8080
GRAFANA_PORT ?= 3000
TEMPO_PORT ?= 3200
OTLP_GRPC_PORT ?= 4317
OTLP_HTTP_PORT ?= 4318
OTEL_COLLECTOR_GRPC_EXTERNAL ?= 14317
OTEL_COLLECTOR_HTTP_EXTERNAL ?= 14318

# Colors for output
YELLOW := \033[1;33m
GREEN := \033[0;32m
RED := \033[0;31m
BLUE := \033[0;34m
NC := \033[0m # No Color

# Docker compose files
COMPOSE_FILE := docker-compose.yml
COMPOSE_OVERRIDE := docker-compose.override.yml

##@ 🐳 Environment Management
up: ## 🚀 Start the complete testing environment
	@echo "$(BLUE)🐳 Starting Symfony OpenTelemetry Bundle Test Environment$(NC)"
	@docker-compose up -d --build
	@echo $(APP_PORT)
	@echo "$(GREEN)✅ Environment started successfully!$(NC)"
	@echo "$(BLUE)🔗 Access Points:$(NC)"
	@echo "  📱 Test Application: http://localhost:$(APP_PORT)"
	@echo "  📈 Grafana Dashboard: http://localhost:$(GRAFANA_PORT) (admin/admin)"
	@echo "  🔍 Tempo API: http://localhost:$(TEMPO_PORT)"
	@echo ""
	@echo "$(YELLOW)Run 'make app-tracing-test' to run sample tests$(NC)"

down: ## 🛑 Stop all services
	@echo "$(YELLOW)🛑 Stopping services...$(NC)"
	@docker-compose down
	@echo "$(GREEN)✅ Services stopped$(NC)"

restart: up down ## 🔄 Restart all services

build: ## 🔨 Build/rebuild all services
	@echo "$(BLUE)🔨 Building services...$(NC)"
	@docker-compose build --no-cache
	@echo "$(GREEN)✅ Build completed$(NC)"

clean: ## 🧹 Stop services and remove all containers, networks, and volumes
	@echo "$(RED)🧹 Cleaning up environment...$(NC)"
	@docker-compose down -v --rmi local --remove-orphans
	@docker system prune -f
	@echo "$(GREEN)✅ Cleanup completed$(NC)"

clear-data: down ## 🗑️ Clear all spans data from Tempo and Grafana (keeps containers)
	@echo "$(YELLOW)🗑️  Clearing all spans data from Tempo and Grafana...$(NC)"
	@echo "$(BLUE)Removing data volumes...$(NC)"
	@docker volume rm -f symfony-otel-bundle_tempo-data symfony-otel-bundle_grafana-data 2>/dev/null || true
	@echo "$(BLUE)Restarting services with clean data...$(NC)"
	@docker-compose up -d
	@echo "$(GREEN)✅ All spans data cleared! Tempo and Grafana restarted with clean state$(NC)"
	@echo "$(BLUE)💡 You can now run tests to generate fresh trace data$(NC)"

clear-spans: clear-data ## 🗑️ Alias for clear-data command

clear-tempo: down ## 🗑️ Clear only Tempo spans data
	@echo "$(YELLOW)🗑️  Clearing Tempo spans data...$(NC)"
	@echo "$(BLUE)Removing Tempo data volume...$(NC)"
	@docker volume rm -f symfony-otel-bundle_tempo-data 2>/dev/null
	@echo "$(BLUE)Restarting Tempo with clean data...$(NC)"
	@docker-compose up -d
	@echo "$(GREEN)✅ Tempo spans data cleared! Service restarted with clean state$(NC)"

reset-all: ## 🔄 Complete reset - clear all data, rebuild, and restart everything
	@echo "$(RED)🔄 Performing complete environment reset...$(NC)"
	@echo "$(BLUE)Step 1: Stopping all services...$(NC)"
	@docker-compose down
	@echo "$(BLUE)Step 2: Removing all data volumes...$(NC)"
	@docker volume rm -f symfony-otel-bundle_tempo-data symfony-otel-bundle_grafana-data 2>/dev/null || true
	@echo "$(BLUE)Step 3: Rebuilding and starting services...$(NC)"
	@docker-compose up -d --build
	@echo "$(GREEN)✅ Complete reset finished! Environment ready with clean state$(NC)"
	@echo "$(BLUE)💡 All trace data cleared and services rebuilt$(NC)"

##@ ⚙️ Service Management
php-rebuild: ## 🔨 Rebuild only the PHP container
	@echo "$(BLUE)🐘 Rebuilding PHP container...$(NC)"
	@docker-compose build php-app
	@docker-compose up -d php-app
	@echo "$(GREEN)✅ PHP container rebuilt$(NC)"

php-restart: ## 🔄 Restart only the PHP application
	@echo "$(YELLOW)🔄 Restarting PHP application...$(NC)"
	@docker-compose restart php-app
	@echo "$(GREEN)✅ PHP application restarted$(NC)"

tempo-restart: ## 🔄 Restart only Tempo service
	@echo "$(YELLOW)🔄 Restarting Tempo...$(NC)"
	@docker-compose restart tempo
	@echo "$(GREEN)✅ Tempo restarted$(NC)"

grafana-restart: ## 🔄 Restart only Grafana service
	@echo "$(YELLOW)🔄 Restarting Grafana...$(NC)"
	@docker-compose restart grafana
	@echo "$(GREEN)✅ Grafana restarted$(NC)"

##@ 📊 Monitoring and Logs
status: ## 📊 Show status of all services
	@echo "$(BLUE)📊 Service Status:$(NC)"
	@docker-compose ps

logs: ## 📋 Show logs from all services
	@echo "$(BLUE)📋 Showing logs from all services:$(NC)"
	@docker-compose logs -f

logs-php: ## 📋 Show logs from PHP application only
	@echo "$(BLUE)📋 PHP Application Logs:$(NC)"
	@docker-compose logs -f php-app

logs-tempo: ## 📋 Show logs from Tempo only
	@echo "$(BLUE)📋 Tempo Logs:$(NC)"
	@docker-compose logs -f tempo

logs-grafana: ## 📋 Show logs from Grafana only
	@echo "$(BLUE)📋 Grafana Logs:$(NC)"
	@docker-compose logs -f grafana

logs-otel: ## 📋 Show OpenTelemetry related logs
	@echo "$(BLUE)📋 OpenTelemetry Logs:$(NC)"
	@docker-compose logs php-app | grep -i otel

##@ 🧪 Testing Commands
app-tracing-test: ## 🧪 Run all test endpoints
	@echo "$(BLUE)🧪 Running OpenTelemetry Bundle Tests$(NC)"
	@echo ""
	@echo "$(YELLOW)Testing basic tracing...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/test | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(YELLOW)Testing slow operation...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/slow | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(YELLOW)Testing nested spans...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/nested | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(YELLOW)Testing error handling...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/error | jq -r '.message // "Response: " + tostring'
	@echo ""
	@echo "$(GREEN)✅ All tests completed!$(NC)"
	@echo "$(BLUE)💡 Check Grafana at http://localhost:$(GRAFANA_PORT) to view traces$(NC)"

##@ 🚀 Benchmarking
phpbench: ## 🚀 Run PhpBench benchmarks for this bundle (inside php container)
	@echo "$(BLUE)🚀 Running PhpBench benchmarks...$(NC)"
	@docker-compose exec php-app ./vendor/bin/phpbench run benchmarks --config=benchmarks/phpbench.json --report=aggregate

phpbench-verbose: ## 🔍 Run PhpBench with verbose output (debugging)
	@echo "$(BLUE)🔍 Running PhpBench (verbose)...$(NC)"
	@docker-compose exec php-app ./vendor/bin/phpbench run benchmarks --config=benchmarks/phpbench.json --report=aggregate -v

test-basic: ## 🧪 Test basic API endpoint
	@echo "$(BLUE)🧪 Testing basic API endpoint...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/test | jq .

test-slow: ## 🧪 Test slow operation endpoint
	@echo "$(BLUE)🧪 Testing slow operation endpoint...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/slow | jq .

test-nested: ## 🧪 Test nested spans endpoint
	@echo "$(BLUE)🧪 Testing nested spans endpoint...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/nested | jq .

test-error: ## 🧪 Test error handling endpoint
	@echo "$(BLUE)🧪 Testing error handling endpoint...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/error | jq .

test-exception: ## 🧪 Test exception handling endpoint
	@echo "$(BLUE)🧪 Testing exception handling endpoint...$(NC)"
	@curl -s http://localhost:$(APP_PORT)/api/exception-test | jq .

test-distributed: ## 🧪 Test with distributed tracing headers
	@echo "$(BLUE)🧪 Testing distributed tracing...$(NC)"
	@curl -s -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01" \
		http://localhost:$(APP_PORT)/api/test | jq .

##@ ⚡ Load Testing
load-test: ## ⚡ Run simple load test
	@echo "$(BLUE)🔄 Running load test (100 requests)...$(NC)"
	@for i in {1..100}; do \
		curl -s http://localhost:$(APP_PORT)/api/test > /dev/null & \
		if [ $$(($${i} % 10)) -eq 0 ]; then echo "Sent $${i} requests..."; fi; \
	done; \
	wait
	@echo "$(GREEN)✅ Load test completed$(NC)"

k6-smoke: ## ⚡ Run k6 smoke test (quick sanity check)
	@echo "$(BLUE)🧪 Running k6 smoke test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/smoke-test.js
	@echo "$(GREEN)✅ Smoke test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-basic: ## ⚡ Run k6 basic load test
	@echo "$(BLUE)🔄 Running k6 basic load test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/basic-test.js
	@echo "$(GREEN)✅ Basic load test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-slow: ## ⚡ Run k6 slow endpoint test
	@echo "$(BLUE)🐌 Running k6 slow endpoint test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/slow-endpoint-test.js
	@echo "$(GREEN)✅ Slow endpoint test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-nested: ## ⚡ Run k6 nested spans test
	@echo "$(BLUE)🔗 Running k6 nested spans test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/nested-spans-test.js
	@echo "$(GREEN)✅ Nested spans test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-pdo: ## ⚡ Run k6 PDO instrumentation test
	@echo "$(BLUE)💾 Running k6 PDO test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/pdo-test.js
	@echo "$(GREEN)✅ PDO test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-cqrs: ## ⚡ Run k6 CQRS pattern test
	@echo "$(BLUE)📋 Running k6 CQRS test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/cqrs-test.js
	@echo "$(GREEN)✅ CQRS test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-comprehensive: ## ⚡ Run k6 comprehensive mixed workload test
	@echo "$(BLUE)🎯 Running k6 comprehensive test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/comprehensive-test.js
	@echo "$(GREEN)✅ Comprehensive test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-baseline: ## ⚡ Run k6 baseline test (app without OpenTelemetry)
	@echo "$(BLUE)📊 Running k6 baseline test (without OTel)...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/baseline-test.js
	@echo "$(GREEN)✅ Baseline test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-comparison: ## ⚡ Run k6 comparison test (OTel vs baseline performance)
	@echo "$(BLUE)⚖️  Running k6 comparison test...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/comparison-test.js
	@echo "$(GREEN)✅ Comparison test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-stress: ## ⚡ Run k6 stress test (~31 minutes, up to 300 VUs)
	@echo "$(YELLOW)⚠️  Warning: This will take approximately 31 minutes$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@echo "$(BLUE)💪 Running k6 stress test...$(NC)"
	@docker-compose run --rm k6 run /scripts/stress-test.js
	@echo "$(GREEN)✅ Stress test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-all-scenarios: ## ⚡ Run all k6 test scenarios in a single comprehensive test (~15 minutes)
	@echo "$(BLUE)🎯 Running all k6 scenarios in sequence...$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/all-scenarios-test.js
	@echo "$(GREEN)✅ All scenarios test completed!$(NC)"
	@echo "$(BLUE)💡 Check Grafana at http://localhost:$(GRAFANA_PORT) to view traces$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

k6-custom: ## ⚡ Run custom k6 test (usage: make k6-custom TEST=script.js)
	@if [ -z "$(TEST)" ]; then \
		echo "$(RED)❌ Error: TEST parameter required$(NC)"; \
		echo "$(YELLOW)Usage: make k6-custom TEST=script.js$(NC)"; \
		exit 1; \
	fi
	@echo "$(BLUE)🔧 Running custom k6 test: $(TEST)$(NC)"
	@echo "$(YELLOW)📊 Dashboard: http://localhost:$(K6_DASHBOARD_PORT:-5665)$(NC)"
	@docker-compose run --rm k6 run /scripts/$(TEST)
	@echo "$(GREEN)✅ Custom test completed$(NC)"
	@echo "$(BLUE)📄 HTML Report: loadTesting/reports/html-report.html$(NC)"

##@ 🐚 Access Commands
bash: ## 🐚 Access PHP container shell
	@echo "$(BLUE)🐚 Accessing PHP container shell...$(NC)"
	@docker-compose exec php-app /bin/bash

bash-tempo: ## 🐚 Access Tempo container shell
	@echo "$(BLUE)🐚 Accessing Tempo container shell...$(NC)"
	@docker-compose exec tempo /bin/bash

##@ 🌐 Web Access
grafana: ## 📈 Open Grafana in browser
	@echo "$(BLUE)📈 Opening Grafana Dashboard...$(NC)"
	@open http://localhost:$(GRAFANA_PORT) || xdg-open http://localhost:$(GRAFANA_PORT) || echo "Open http://localhost:$(GRAFANA_PORT) in your browser"

app: ## 📱 Open test application in browser
	@echo "$(BLUE)📱 Opening Test Application...$(NC)"
	@open http://localhost:$(APP_PORT) || xdg-open http://localhost:$(APP_PORT) || echo "Open http://localhost:$(APP_PORT) in your browser"

tempo: ## 🔍 Open Tempo API in browser
	@echo "$(BLUE)🔍 Opening Tempo API...$(NC)"
	@open http://localhost:$(TEMPO_PORT) || xdg-open http://localhost:$(TEMPO_PORT) || echo "Open http://localhost:$(TEMPO_PORT) in your browser"

##@ 💻 Development Commands
dev: ## 🔧 Start development environment with hot reload
	@echo "$(BLUE)🔧 Starting development environment...$(NC)"
	@docker-compose -f $(COMPOSE_FILE) -f $(COMPOSE_OVERRIDE) up -d --build
	@echo "$(GREEN)✅ Development environment started with hot reload$(NC)"

composer-install: ## 📦 Install Composer dependencies
	@echo "$(BLUE)📦 Installing Composer dependencies...$(NC)"
	@docker-compose exec php-app composer install
	@echo "$(GREEN)✅ Dependencies installed$(NC)"

composer-update: ## 🔄 Update Composer dependencies
	@echo "$(BLUE)🔄 Updating Composer dependencies...$(NC)"
	@docker-compose exec php-app composer update
	@echo "$(GREEN)✅ Dependencies updated$(NC)"

test: ## 🧪 Run PHPUnit tests
	@echo "$(BLUE)🧪 Running PHPUnit tests...$(NC)"
	@docker-compose exec php-app vendor/bin/phpunit
	@echo "$(GREEN)✅ PHPUnit tests completed$(NC)"

phpcs: ## 🔍 Run PHP_CodeSniffer
	@echo "$(BLUE)🔍 Running PHP_CodeSniffer...$(NC)"
	@docker-compose exec php-app vendor/bin/phpcs
	@echo "$(GREEN)✅ PHP_CodeSniffer completed$(NC)"

phpcs-fix: ## 🔧 Fix PHP_CodeSniffer issues
	@echo "$(BLUE)🔧 Fixing PHP_CodeSniffer issues...$(NC)"
	@docker-compose exec php-app vendor/bin/phpcbf
	@echo "$(GREEN)✅ PHP_CodeSniffer fixes applied$(NC)"

phpstan: ## 🔍 Run PHPStan static analysis
	@echo "$(BLUE)🔍 Running PHPStan...$(NC)"
	@docker-compose exec php-app vendor/bin/phpstan analyse
	@echo "$(GREEN)✅ PHPStan completed$(NC)"

test-all: ## 🧪 Run all tests (PHPUnit, PHPCS, PHPStan)
	@echo "$(BLUE)🧪 Running all tests...$(NC)"
	@docker-compose exec php-app composer test
	@echo "$(GREEN)✅ All tests completed$(NC)"

test-fix: ## 🔧 Run tests with auto-fixing
	@echo "$(BLUE)🧪 Running tests with auto-fixing...$(NC)"
	@docker-compose exec php-app composer test-fix
	@echo "$(GREEN)✅ Tests with fixes completed$(NC)"

coverage: ## 📊 Generate code coverage report
	@echo "$(BLUE)📊 Generating code coverage report...$(NC)"
	@docker-compose exec php-app mkdir -p var/coverage/html
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html var/coverage/html --coverage-text
	@echo "$(GREEN)✅ Coverage report generated$(NC)"
	@echo "$(BLUE)📁 HTML report available at: var/coverage/html/index.html$(NC)"

coverage-text: ## 📊 Generate code coverage text report
	@echo "$(BLUE)📊 Generating text coverage report...$(NC)"
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text
	@echo "$(GREEN)✅ Text coverage report completed$(NC)"

coverage-clover: ## 📊 Generate code coverage clover XML report
	@echo "$(BLUE)📊 Generating clover coverage report...$(NC)"
	@docker-compose exec php-app mkdir -p var/coverage
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
	@echo "$(GREEN)✅ Clover coverage report generated$(NC)"
	@echo "$(BLUE)📁 Clover report available at: var/coverage/clover.xml$(NC)"

coverage-all: ## 📊 Generate all coverage reports
	@echo "$(BLUE)📊 Generating all coverage reports...$(NC)"
	@docker-compose exec php-app mkdir -p var/coverage/html var/coverage/xml
	@docker-compose exec php-app php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html var/coverage/html --coverage-text --coverage-clover var/coverage/clover.xml --coverage-xml var/coverage/xml
	@echo "$(GREEN)✅ All coverage reports generated$(NC)"
	@echo "$(BLUE)📁 Reports available in: var/coverage/$(NC)"

coverage-open: coverage ## 🌐 Generate coverage report and open in browser
	@echo "$(BLUE)🌐 Opening coverage report in browser...$(NC)"
	@open var/coverage/html/index.html || xdg-open var/coverage/html/index.html || echo "Open var/coverage/html/index.html in your browser"

##@ 🐛 Debugging Commands
debug-otel: ## 🔍 Debug OpenTelemetry configuration
	@echo "$(BLUE)🔍 OpenTelemetry Debug Information:$(NC)"
	@echo ""
	@echo "$(YELLOW)Environment Variables:$(NC)"
	@docker-compose exec php-app env | grep OTEL
	@echo ""
	@echo "$(YELLOW)PHP OpenTelemetry Extension:$(NC)"
	@docker-compose exec php-app php -m | grep -i otel
	@echo ""
	@echo "$(YELLOW)Tempo Health Check:$(NC)"
	@curl -s http://localhost:$(TEMPO_PORT)/ready || echo "Tempo not ready"
	@echo ""

debug-traces: ## 🔍 Check if traces are being sent
	@echo "$(BLUE)🔍 Checking trace export...$(NC)"
	@echo "Making test request..."
	@curl -s http://localhost:$(APP_PORT)/api/test > /dev/null
	@sleep 2
	@echo "Checking Tempo for traces..."
	@curl -s "http://localhost:$(TEMPO_PORT)/api/search?tags=service.name%3Dsymfony-otel-test" | jq '.traces // "No traces found"'

health: ## 🏥 Check health of all services
	@echo "$(BLUE)🏥 Health Check:$(NC)"
	@echo ""
	@echo "$(YELLOW)PHP Application:$(NC)"
	@curl -s http://localhost:$(APP_PORT)/ > /dev/null && echo "✅ OK" || echo "❌ Failed"
	@echo ""
	@echo "$(YELLOW)Tempo:$(NC)"
	@curl -s http://localhost:$(TEMPO_PORT)/ready > /dev/null && echo "✅ OK" || echo "❌ Failed"
	@echo ""
	@echo "$(YELLOW)Grafana:$(NC)"
	@curl -s http://localhost:$(GRAFANA_PORT)/api/health > /dev/null && echo "✅ OK" || echo "❌ Failed"

##@ 🛠️ Utility Commands
urls: ## 🔗 Show all available URLs
	@echo "$(BLUE)🔗 Available URLs:$(NC)"
	@echo "  📱 Test Application: http://localhost:$(APP_PORT)"
	@echo "  📈 Grafana Dashboard: http://localhost:$(GRAFANA_PORT) (admin/admin)"
	@echo "  🔍 Tempo API: http://localhost:$(TEMPO_PORT)"
	@echo "  📊 Tempo Metrics: http://localhost:$(TEMPO_PORT)/metrics"
	@echo "  🔧 OpenTelemetry Collector: http://localhost:4320"

endpoints: ## 🧪 Show all test endpoints
	@echo "$(BLUE)🧪 Test Endpoints:$(NC)"
	@echo "  GET  /                - Homepage with documentation"
	@echo "  GET  /api/test        - Basic tracing example"
	@echo "  GET  /api/slow        - Slow operation (2 seconds)"
	@echo "  GET  /api/nested      - Nested spans example"
	@echo "  GET  /api/error       - Error handling example"
	@echo "  GET  /api/exception-test - Exception handling test"

data-commands: ## 🗂️ Show data management commands
	@echo "$(BLUE)🗂️  Data Management Commands:$(NC)"
	@echo "  make clear-data     - Clear all spans from Tempo & Grafana"
	@echo "  make clear-tempo    - Clear only Tempo spans data"
	@echo "  make clear-spans    - Alias for clear-data"
	@echo "  make reset-all      - Complete reset with rebuild"
	@echo "  make clean          - Remove everything (containers, volumes, images)"
	@echo "  make data-status    - Show current data volume status"
	@echo ""
	@echo "$(YELLOW)💡 Tip: Use 'clear-data' for a quick fresh start during testing$(NC)"

data-status: ## 📊 Show current data volume status and trace count
	@echo "$(BLUE)📊 Data Volume Status:$(NC)"
	@echo ""
	@echo "$(YELLOW)Docker Volumes:$(NC)"
	@docker volume ls | grep symfony-otel-bundle || echo "No volumes found"
	@echo ""
	@echo "$(YELLOW)Tempo Health:$(NC)"
	@curl -s http://localhost:3200/ready > /dev/null && echo "✅ Tempo is ready" || echo "❌ Tempo not accessible"
	@echo ""
	@echo "$(YELLOW)Recent Traces:$(NC)"
	@curl -s "http://localhost:3200/api/search?limit=5" 2>/dev/null | jq -r '.traces[]?.traceID // "No traces found"' | head -5 || echo "No traces or Tempo not accessible"
	@echo ""
	@echo "$(YELLOW)Grafana Health:$(NC)"
	@curl -s http://localhost:3000/api/health > /dev/null && echo "✅ Grafana is ready" || echo "❌ Grafana not accessible"

##@ ❓ Help
help: ## ❓ Show this help message with command groups
	@echo "$(BLUE)🚀 Symfony OpenTelemetry Bundle - Available Commands$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; group = ""} /^##@/ { group = substr($$0, 5); next } /^[a-zA-Z_-]+:.*?##/ { if (group != "") { if (!printed[group]) { printf "\n$(YELLOW)%s$(NC)\n", group; printed[group] = 1 } } printf "  $(GREEN)%-25s$(NC) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""
	@echo "$(BLUE)💡 Quick Start:$(NC)"
	@echo "  make up          # Start the environment"
	@echo "  make test        # Run phpunit tests"
	@echo "  make clear-data  # Clear all spans data (fresh start)"
	@echo "  make coverage    # Generate coverage report"
	@echo "  make grafana     # Open Grafana dashboard"
	@echo "  make down        # Stop the environment"
	@echo ""

##@ ✅ CI/Quality
validate-workflows: ## ✅ Validate GitHub Actions workflows
	@echo "$(BLUE)🔍 Validating GitHub Actions workflows...$(NC)"
	@command -v act >/dev/null 2>&1 || { echo "$(RED)❌ 'act' not found. Install with: brew install act$(NC)"; exit 1; }
	@act --list
	@echo "$(GREEN)✅ GitHub Actions workflows are valid$(NC)"

test-workflows: ## 🧪 Test GitHub Actions workflows locally (requires 'act')
	@echo "$(BLUE)🧪 Testing GitHub Actions workflows locally...$(NC)"
	@command -v act >/dev/null 2>&1 || { echo "$(RED)❌ 'act' not found. Install with: brew install act$(NC)"; exit 1; }
	@act pull_request --artifact-server-path ./artifacts
	@echo "$(GREEN)✅ Local workflow testing completed$(NC)"

lint-yaml: ## 🔍 Lint YAML files
	@echo "$(BLUE)🔍 Linting YAML files...$(NC)"
	@command -v yamllint >/dev/null 2>&1 || { echo "$(RED)❌ 'yamllint' not found. Install with: pip install yamllint$(NC)"; exit 1; }
	@find .github -name "*.yml" -o -name "*.yaml" | xargs yamllint
	@echo "$(GREEN)✅ YAML files are valid$(NC)"

security-scan: ## 🔒 Run local security scanning
	@echo "$(BLUE)🔒 Running local security scan...$(NC)"
	@docker run --rm -v $(PWD):/workspace aquasec/trivy fs --security-checks vuln /workspace
	@echo "$(GREEN)✅ Security scan completed$(NC)"

fix-whitespace: ## 🧹 Fix trailing whitespace in all files
	@echo "$(BLUE)🧹 Fixing trailing whitespace...$(NC)"
	@find src tests -name "*.php" -exec sed -i 's/[[:space:]]*$$//' {} \; 2>/dev/null || \
	 find src tests -name "*.php" -exec sed -i '' 's/[[:space:]]*$$//' {} \;
	@echo "$(GREEN)✅ Trailing whitespace fixed$(NC)"

setup-hooks: ## 🪝 Install git hooks for code quality
	@echo "$(BLUE)🪝 Setting up git hooks...$(NC)"
	@git config core.hooksPath .githooks
	@chmod +x .githooks/pre-commit
	@echo "$(GREEN)✅ Git hooks installed$(NC)"
	@echo "$(YELLOW)💡 Code style will be automatically checked and fixed on commit$(NC)"
