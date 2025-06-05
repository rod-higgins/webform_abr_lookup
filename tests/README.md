# Testing Guide for Webform ABR Lookup Module

This document provides comprehensive information about the test suite for the Webform ABR Lookup module.

## Test Structure

The test suite is organized into three main categories:

```
tests/
├── src/
│   ├── Unit/                    # Unit tests (no Drupal dependencies)
│   │   ├── AbrValidationTest.php
│   │   └── ValidationControllerTest.php
│   ├── Kernel/                  # Kernel tests (minimal Drupal bootstrap)
│   │   ├── AbrClientServiceTest.php
│   │   └── WebformElementTest.php
│   └── Functional/              # Functional tests (full Drupal environment)
│       ├── SettingsFormTest.php
│       ├── AutocompleteTest.php
│       ├── EndToEndTest.php
│       └── ApiIntegrationTest.php
└── phpunit.xml                 # PHPUnit configuration
```

## Test Categories

### Unit Tests

Unit tests focus on testing individual methods and functions in isolation:

- **AbrValidationTest.php**: Tests ABN validation algorithm, formatting, and edge cases
- **ValidationControllerTest.php**: Tests input validation, sanitization, and security measures

### Kernel Tests

Kernel tests use a minimal Drupal environment to test service integration:

- **AbrClientServiceTest.php**: Tests the ABR client service, configuration, caching, and API integration
- **WebformElementTest.php**: Tests the webform element processing, validation, and configuration

### Functional Tests

Functional tests use a full Drupal environment with browser simulation:

- **SettingsFormTest.php**: Tests the module settings form and key integration
- **AutocompleteTest.php**: Tests the autocomplete controller endpoints
- **EndToEndTest.php**: Tests complete webform integration and user workflows
- **ApiIntegrationTest.php**: Tests API endpoints with mocked ABR responses

## Running Tests

### Prerequisites

1. **Install Dependencies**:
   ```bash
   composer install
   ```

2. **Configure Test Environment**:
   Copy `phpunit.xml` to your module directory and update the environment variables:
   - `SIMPLETEST_BASE_URL`: Your local Drupal site URL
   - `SIMPLETEST_DB`: Database connection string for tests

### Running All Tests

```bash
# Run all tests
vendor/bin/phpunit tests/

# Run with coverage report
vendor/bin/phpunit --coverage-html reports/coverage tests/
```

### Running Specific Test Categories

```bash
# Unit tests only
vendor/bin/phpunit tests/src/Unit/

# Kernel tests only
vendor/bin/phpunit tests/src/Kernel/

# Functional tests only
vendor/bin/phpunit tests/src/Functional/
```

### Running Individual Test Files

```bash
# Run specific test file
vendor/bin/phpunit tests/src/Unit/AbrValidationTest.php

# Run specific test method
vendor/bin/phpunit --filter testValidAbnValidation tests/src/Unit/AbrValidationTest.php
```

### Using Drupal's Testing Framework

Alternatively, you can use Drupal's built-in testing commands:

```bash
# From Drupal root directory
php core/scripts/run-tests.sh --module webform_abr_lookup
```

## Test Coverage

### What's Tested

#### ABN/ACN Validation
- ✅ Valid ABN checksums (Telstra, Commonwealth Bank, etc.)
- ✅ Invalid ABN formats and checksums
- ✅ ABN formatting with spaces
- ✅ Edge cases (empty, null, too long/short)

#### Input Validation & Security
- ✅ XSS prevention through input sanitization
- ✅ SQL injection prevention
- ✅ Rate limiting functionality
- ✅ CSRF protection through Drupal's form API

#### API Integration
- ✅ Mock ABR API responses
- ✅ Error handling (network, XML parsing, ABR exceptions)
- ✅ Caching behavior
- ✅ Configuration through Key module

#### Webform Integration
- ✅ Element processing and rendering
- ✅ Form validation
- ✅ JavaScript/CSS attachment
- ✅ Different lookup types (ABN, ACN, business name)

#### User Interface
- ✅ Settings form functionality
- ✅ Autocomplete endpoints
- ✅ AJAX responses
- ✅ Error display

### Known Test Cases

The test suite includes specific test cases for real Australian businesses:

1. **Telstra Corporation Limited** (ABN: 51 824 753 556)
   - Tests major public company lookup
   - Validates GST registration status
   - Tests state/postcode extraction

2. **Commonwealth Bank** (ABN: 48 123 123 124, ACN: 123 123 124)
   - Tests ACN to ABN mapping
   - Validates banking sector entity

3. **Business Name Searches**
   - Tests autocomplete functionality
   - Validates search result formatting
   - Tests multiple result handling

## Mock Data

Tests use realistic mock data based on actual ABR responses:

- **XML Structure**: Matches official ABR XML schema
- **Business Data**: Uses real business names and structures
- **Error Responses**: Includes actual ABR error formats

## Environment Setup

### Test Database

Tests require a separate database to avoid conflicts:

```bash
# SQLite (recommended for CI)
export SIMPLETEST_DB="sqlite://localhost/sites/default/files/.ht.sqlite"

# MySQL
export SIMPLETEST_DB="mysql://user:pass@localhost/test_db"
```

### Test Site

Set up a test Drupal site for functional tests:

```bash
export SIMPLETEST_BASE_URL="http://localhost:8080"
```

### Key Module Configuration

Functional tests automatically create test keys, but for manual testing:

1. Install the Key module
2. Create a key with a valid ABR GUID
3. Configure the module to use the key

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit tests/
```

### Test Data Requirements

Tests require specific test data that's included in mock responses:

- Valid ABN checksums for validation testing
- Realistic business entity data
- Error response formats
- Cache expiration scenarios

## Debugging Tests

### Verbose Output

```bash
vendor/bin/phpunit --verbose tests/
```

### Debug Information

```bash
vendor/bin/phpunit --debug tests/
```

### Browser Output (Functional Tests)

Set `BROWSERTEST_OUTPUT_DIRECTORY` to capture browser screenshots:

```bash
export BROWSERTEST_OUTPUT_DIRECTORY="./test-output"
```

### Logging

Enable detailed logging in `phpunit.xml`:

```xml
<logging>
  <log type="junit" target="reports/junit.xml"/>
  <log type="coverage-html" target="reports/coverage"/>
</logging>
```

## Test Maintenance

### Adding New Tests

1. **Unit Tests**: Add to appropriate file in `tests/src/Unit/`
2. **Kernel Tests**: Create new test class extending `KernelTestBase`
3. **Functional Tests**: Create new test class extending `BrowserTestBase`

### Mock Data Updates

When ABR API changes:

1. Update XML response fixtures
2. Verify field mappings in parsing methods
3. Update expected values in assertions

### Test Dependencies

Keep test dependencies minimal:

- **Unit Tests**: No Drupal dependencies
- **Kernel Tests**: Core modules only
- **Functional Tests**: Required modules only

## Performance Considerations

### Test Execution Time

- **Unit Tests**: < 1 second each
- **Kernel Tests**: 1-5 seconds each
- **Functional Tests**: 5-30 seconds each

### Memory Usage

- Use `memory_limit = -1` for complex functional tests
- Clear caches between test methods when needed

### Database Cleanup

Tests automatically clean up:

- Temporary files
- Test entities
- Cache entries
- Configuration overrides

## Security Testing

The test suite includes security-focused tests:

- **Input Validation**: XSS, injection prevention
- **Rate Limiting**: API abuse prevention
- **Key Security**: Credential storage testing
- **CSRF Protection**: Form security validation

## Accessibility Testing

While not automated, manual accessibility testing should cover:

- Screen reader compatibility
- Keyboard navigation
- Color contrast
- Form labeling

## Browser Compatibility

Functional tests use:

- **Default**: PhantomJS/headless Chrome
- **Alternative**: Selenium WebDriver
- **Coverage**: Modern browsers (Chrome, Firefox, Safari, Edge)

## Troubleshooting

### Common Issues

1. **Database Connection**: Verify SIMPLETEST_DB setting
2. **Permission Errors**: Check file system permissions
3. **Memory Limits**: Increase PHP memory_limit
4. **Network Issues**: Mock external API calls

### Test Failures

1. **Intermittent Failures**: Usually cache or timing related
2. **Environment Issues**: Check Drupal core version compatibility
3. **Module Dependencies**: Ensure all required modules are enabled

### Performance Issues

1. **Slow Tests**: Profile database queries
2. **Memory Leaks**: Check object cleanup
3. **Cache Problems**: Clear caches between tests

## Contributing

When contributing new tests:

1. Follow existing test patterns
2. Include both positive and negative test cases
3. Add appropriate documentation
4. Ensure tests are deterministic (no random failures)
5. Mock external dependencies
6. Include edge case testing

## Resources

- [Drupal Testing Documentation](https://www.drupal.org/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [ABR Web Services Documentation](https://abr.business.gov.au/Documentation/WebServiceDocumentation)
- [Webform Module Testing Examples](https://git.drupalcode.org/project/webform/-/tree/8.x-6.x/tests)