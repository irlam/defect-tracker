# Copilot Instructions Directory

This directory contains specialized instruction files for GitHub Copilot coding agent. These files use YAML frontmatter to specify which parts of the codebase they apply to.

## Instruction Files

### api.instructions.md
**Applies to**: `api/**/*.php`

Guidelines for creating and maintaining API endpoints:
- Authentication and authorization patterns
- Input validation and sanitization
- Response format standards
- HTTP status code usage
- Security checklist

### security.instructions.md
**Applies to**: All PHP files (`**/*.php`)

Comprehensive security guidelines:
- SQL injection prevention
- XSS prevention
- CSRF protection
- Password security
- File upload security
- Session security
- Error handling

### database.instructions.md
**Applies to**: All PHP files (`**/*.php`)

Database operation guidelines:
- Connection management
- Prepared statements
- Transactions
- Error handling
- Common patterns
- Performance optimization

## How It Works

GitHub Copilot automatically uses these specialized instructions when working on files that match the `applies_to` patterns defined in the YAML frontmatter.

For example:
- When editing a file in `api/`, Copilot will use both the main instructions and `api.instructions.md`
- When working with any PHP file, Copilot will apply security and database guidelines
- The main `.github/copilot-instructions.md` applies to all files

## Adding New Instruction Files

To add a new specialized instruction file:

1. Create a new `.instructions.md` file in this directory
2. Add YAML frontmatter with `applies_to` patterns:
   ```yaml
   ---
   applies_to:
     - path/pattern/**/*.php
   ---
   ```
3. Write your instructions using clear, actionable guidelines
4. Include code examples where appropriate

## References

- [GitHub Copilot custom instructions documentation](https://docs.github.com/en/copilot)
- [Best practices for Copilot coding agent](https://docs.github.com/en/copilot/tutorials/coding-agent/get-the-best-results)
