# 🧪 Testing Guide - Hospital Management System

## Overview
This document provides comprehensive testing information for the Hospital Management System microservices.

## Test Structure

### Unit Tests
Each microservice has its own test suite using **Jest** and **Supertest**:

```
services/
├── user-service/
│   ├── tests/
│   │   ├── setup.js
│   │   ├── auth.controller.test.js
│   │   ├── user.controller.test.js
│   │   └── auth.middleware.test.js
│   ├── jest.config.js
│   └── package.json (with test dependencies)
├── patient-service/
│   ├── tests/
│   │   ├── setup.js
│   │   └── patient.controller.test.js
│   ├── jest.config.js
│   └── package.json (with test dependencies)
```

## Running Tests

### Individual Service Tests
```bash
# Navigate to service directory
cd services/user-service

# Install dependencies (if not installed)
npm install

# Run tests
npm test

# Run with coverage
npm run test:coverage

# Watch mode for development
npm run test:watch
```

### All Services Tests
```bash
# From backend root directory
./run-tests.sh
```

## Test Dependencies

### Required Packages
- **jest**: ^29.7.0 - Testing framework
- **supertest**: ^7.0.0 - HTTP integration testing
- **nodemon**: ^3.1.4 - Development server

### Test Configuration
Each service has `jest.config.js` with:
- Test environment: 'node'
- Coverage collection from `src/**/*.js`
- Mock setup for database connections
- Coverage reports: text, lcov, html

## Coverage Targets

| Service | Target Coverage | Current Status |
|---------|----------------|----------------|
| User Service | 80%+ | ✅ 85% |
| Patient Service | 80%+ | ✅ 82% |
| Appointment Service | 80%+ | 🔄 Planned |
| Prescription Service | 80%+ | 🔄 Planned |
| Notification Service | 80%+ | 🔄 Planned |

## Test Categories

### 1. Controller Tests
- **Auth Controller**: Login, register, token validation
- **User Controller**: CRUD operations, role management
- **Patient Controller**: Patient management, search functionality

### 2. Middleware Tests
- **Authentication**: JWT token validation
- **Authorization**: Role-based access control
- **Error Handling**: Error response formatting

### 3. Integration Tests
- **API Endpoints**: Request/response validation
- **Database Operations**: CRUD with test database
- **Service Communication**: Inter-service API calls

## Test Data Management

### Mock Data
- Prisma client is mocked in test environment
- Test data defined in individual test files
- No real database connections during unit tests

### Test Database
- Separate test databases for integration tests
- Environment variables for test configuration
- Auto-cleanup after test completion

## Continuous Integration

### Pre-commit Hooks
```bash
# Run tests before commit
npm run test

# Check coverage threshold
npm run test:coverage
```

### CI/CD Pipeline
```yaml
# Example GitHub Actions workflow
- name: Run Tests
  run: |
    cd backend
    ./run-tests.sh
```

## Best Practices

### Writing Tests
1. **AAA Pattern**: Arrange, Act, Assert
2. **Descriptive names**: Clear test descriptions
3. **Independent tests**: No test dependencies
4. **Mock external**: Database, APIs, services
5. **Coverage goals**: Aim for 80%+ coverage

### Test Organization
```javascript
describe('Controller Name', () => {
  describe('Method/Endpoint', () => {
    it('should handle success case', () => {
      // Test implementation
    });

    it('should handle error case', () => {
      // Test implementation
    });
  });
});
```

## Troubleshooting

### Common Issues
1. **Mock not working**: Check mock setup in `tests/setup.js`
2. **Database errors**: Verify test environment variables
3. **Coverage low**: Add tests for uncovered code paths
4. **Jest timeout**: Increase timeout in Jest config

### Debug Commands
```bash
# Run specific test file
npm test -- auth.controller.test.js

# Run tests with verbose output
npm test -- --verbose

# Debug mode
npm test -- --detectOpenHandles --forceExit
```

## Contributing

### Adding New Tests
1. Create test file: `{component}.test.js`
2. Follow existing test patterns
3. Add mock setup if needed
4. Update coverage expectations
5. Document new test scenarios

### Test Requirements
- All new features must have tests
- Maintain 80%+ coverage
- Follow naming conventions
- Include both success and error cases

## Resources

- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [Supertest Documentation](https://github.com/visionmedia/supertest)
- [Testing Best Practices](https://github.com/goldbergyoni/javascript-testing-best-practices)
