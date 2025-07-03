# Security Policy

## Supported Versions

We actively support the following versions with security updates:

| Version | Supported |
|---------|-----------|
| 1.x.x   | ✅ Yes     |
| < 1.0   | ❌ No      |

## Reporting a Vulnerability

We take the security of the Symfony OpenTelemetry Bundle seriously. If you discover a security vulnerability, please
follow these guidelines:

### 🔒 Private Reporting

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, please report security vulnerabilities via email to:

- **Email**: [t.ziabukhin@macpaw.com](mailto:t.ziabukhin@macpaw.com)
- **Subject**: `[SECURITY] Symfony OpenTelemetry Bundle - [Brief Description]`

### 📋 What to Include

When reporting a vulnerability, please include:

1. **Description**: A clear description of the vulnerability
2. **Impact**: What an attacker could achieve with this vulnerability
3. **Steps to Reproduce**: Detailed steps to reproduce the issue
4. **Affected Versions**: Which versions are affected
5. **Suggested Fix**: If you have suggestions for fixing the issue
6. **Your Contact Information**: For follow-up questions

### 🔄 Response Process

1. **Acknowledgment**: We will acknowledge receipt of your report within 48 hours
2. **Investigation**: We will investigate and assess the vulnerability
3. **Timeline**: We will provide an estimated timeline for fixes
4. **Resolution**: We will work on a fix and coordinate disclosure
5. **Credit**: We will credit you in the security advisory (unless you prefer anonymity)

### 📅 Timeline

- **Initial Response**: Within 48 hours
- **Status Updates**: Every 72 hours during investigation
- **Fix Development**: Typically 1-2 weeks depending on complexity
- **Public Disclosure**: After fix is released and users have time to update

### 🏆 Responsible Disclosure

We follow responsible disclosure principles:

- We will not pursue legal action against researchers who:
    - Act in good faith
    - Do not access or modify user data without permission
    - Do not disrupt our services
    - Report vulnerabilities promptly

- We ask that you:
    - Give us reasonable time to fix vulnerabilities before public disclosure
    - Do not exploit vulnerabilities for personal gain
    - Do not share vulnerability details with others until we've addressed them

### 🛡️ Security Best Practices

When using this bundle:

1. **Keep Updated**: Always use the latest version
2. **Secure Configuration**: Review your OpenTelemetry configuration for sensitive data
3. **Environment Variables**: Use environment variables for sensitive configuration
4. **Access Control**: Limit access to tracing data and endpoints
5. **Transport Security**: Use TLS/SSL for trace data transmission

### 🔗 Related Security Resources

- [OpenTelemetry Security Documentation](https://opentelemetry.io/docs/reference/specification/security/)
- [Symfony Security Best Practices](https://symfony.com/doc/current/security.html)
- [OWASP Security Guidelines](https://owasp.org/) 
