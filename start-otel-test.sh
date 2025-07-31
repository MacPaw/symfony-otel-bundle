#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🐳 Starting Symfony OpenTelemetry Bundle Test Environment${NC}"
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}❌ Docker is not running. Please start Docker first.${NC}"
    exit 1
fi

# Check if docker-compose is available
if ! command -v docker-compose > /dev/null 2>&1; then
    echo -e "${RED}❌ docker-compose is not available. Please install Docker Compose.${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Building and starting services...${NC}"
docker-compose up -d --build

# Wait for services to start
echo ""
echo -e "${YELLOW}⏳ Waiting for services to start...${NC}"
sleep 10

# Check service status
echo ""
echo -e "${BLUE}📊 Service Status:${NC}"
docker-compose ps

echo ""
echo -e "${GREEN}✅ Environment started successfully!${NC}"
echo ""
echo -e "${BLUE}🔗 Access Points:${NC}"
echo -e "  📱 Test Application: ${GREEN}http://localhost:8080${NC}"
echo -e "  📈 Grafana Dashboard: ${GREEN}http://localhost:3000${NC} (admin/admin)"
echo -e "  🔍 Tempo API: ${GREEN}http://localhost:3200${NC}"
echo ""
echo -e "${BLUE}🧪 Quick Test Commands:${NC}"
echo -e "  curl http://localhost:8080/api/test"
echo -e "  curl http://localhost:8080/api/slow"
echo -e "  curl http://localhost:8080/api/nested"
echo -e "  curl http://localhost:8080/api/error"
echo ""
echo -e "${YELLOW}📋 To view traces:${NC}"
echo -e "  1. Open Grafana: http://localhost:3000"
echo -e "  2. Navigate to Explore → Tempo"
echo -e "  3. Search for traces from 'symfony-otel-test' service"
echo ""
echo -e "${BLUE}🛑 To stop the environment:${NC}"
echo -e "  docker-compose down"
echo "" 
