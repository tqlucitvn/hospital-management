#!/bin/bash

echo "🧪 Running Unit Tests for Hospital Management System"
echo "=================================================="

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to run tests for a service
run_service_tests() {
    local service_name=$1
    local service_path="services/$service_name"
    
    echo -e "\n${YELLOW}📋 Testing $service_name...${NC}"
    
    if [ -d "$service_path" ]; then
        cd "$service_path"
        
        # Install test dependencies if not present
        if [ ! -d "node_modules/jest" ]; then
            echo "📦 Installing test dependencies..."
            npm install
        fi
        
        # Run tests
        if npm test; then
            echo -e "${GREEN}✅ $service_name tests passed${NC}"
        else
            echo -e "${RED}❌ $service_name tests failed${NC}"
            TEST_FAILED=1
        fi
        
        cd - > /dev/null
    else
        echo -e "${RED}❌ Service directory not found: $service_path${NC}"
        TEST_FAILED=1
    fi
}

# Initialize test failure flag
TEST_FAILED=0

# Run tests for each service
run_service_tests "user-service"
run_service_tests "patient-service"
run_service_tests "appointment-service"
run_service_tests "prescription-service"

# Summary
echo -e "\n${YELLOW}📊 Test Summary${NC}"
echo "==============="

if [ $TEST_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed successfully!${NC}"
    echo -e "${GREEN}✨ Code coverage reports available in each service's coverage/ directory${NC}"
else
    echo -e "${RED}💥 Some tests failed. Please check the output above.${NC}"
    exit 1
fi
