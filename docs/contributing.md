# Contributing Guide

## Overview

Thank you for your interest in contributing to the Symfony OpenTelemetry Bundle! This guide will help you get started with development, testing, and contributing to the project.

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Docker and Docker Compose
- Git

### Local Development Environment

1. **Clone the repository**
   ```bash
   git clone https://github.com/macpaw/symfony-otel-bundle.git
   cd symfony-otel-bundle
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Start the test environment**
   ```bash
   make up
   ```

4. **Verify setup**
   ```bash
   make health
   make test
   ```

### Development Workflow

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Follow the coding standards
   - Add tests for new functionality
   - Update documentation as needed

3. **Run tests**
   ```bash
   make test
   ```

4. **Submit a pull request**
   - Provide a clear description of changes
   - Include test results
   - Reference any related issues

## Code Standards

### PHP Standards

- Follow PSR-12 coding standards
- Use strict types declaration
- Use type hints for all parameters and return types
- Write self-documenting code with clear method names

### Testing Requirements

- All new features must include tests
- Maintain high code coverage
- Follow the existing test patterns
- Test both success and error scenarios

### Documentation Standards

- Update relevant documentation for new features
- Follow the existing documentation structure
- Include code examples where appropriate
- Keep documentation clear and concise

## Issue Reporting

### How to Ask Questions

When asking questions or reporting issues, please provide:

1. **Clear description** of the problem or question
2. **Environment details** (PHP version, Symfony version, etc.)
3. **Steps to reproduce** the issue
4. **Expected vs actual behavior**
5. **Relevant code examples**
6. **Error messages or logs**

### Issue Templates

Use the appropriate issue template when creating issues:

- **Bug Report** - For bugs and issues
- **Feature Request** - For new features

### Before Reporting Issues

1. **Check existing issues** - Search for similar problems
2. **Review documentation** - Check if the answer is already documented
3. **Test with latest version** - Ensure you're using the latest release
4. **Provide minimal example** - Create a minimal reproduction case

## Pull Request Process

### Before Submitting

1. **Run all tests** - Ensure all tests pass
2. **Check code quality** - Run static analysis tools
3. **Update documentation** - Include relevant documentation changes
4. **Test manually** - Verify functionality works as expected

### Pull Request Guidelines

1. **Clear title** - Use descriptive, concise titles
2. **Detailed description** - Explain what and why, not how
3. **Reference issues** - Link to related issues
4. **Include tests** - Add tests for new functionality
5. **Update documentation** - Keep docs in sync with code

### Review Process

1. **Automated checks** - CI/CD pipeline runs tests
2. **Code review** - Maintainers review the changes
3. **Feedback** - Address any review comments
4. **Merge** - Changes are merged after approval

## Development Tools

### Available Commands

```bash
# Code quality
make phpcs          # Run PHP CodeSniffer
make phpcs-fix      # Fix coding standards
make phpstan        # Run PHPStan static analysis

# Testing
make phpunit        # Run PHPUnit tests
make coverage       # Run tests with coverage
make infection      # Run mutation testing

# Environment
make up             # Start test environment
make down           # Stop test environment
make test           # Run all tests
make health         # Check service health
```

### Integration Testing

Use the provided Docker environment for integration testing:

```bash
# Start environment
make up

# Run integration tests
make test

# Check traces in Grafana
make grafana
```
## Acknowledgments

Thank you to all contributors who help improve this project! Your contributions make the Symfony OpenTelemetry Bundle better for everyone.

## Support

If you need help with contributing:

1. **Check this guide** - Review the contributing guidelines
2. **Search issues** - Look for similar questions or problems
3. **Ask questions** - Use GitHub Discussions or Issues
4. **Be patient** - Maintainers are volunteers with limited time

Remember: Every contribution, no matter how small, helps improve the project for everyone! 
