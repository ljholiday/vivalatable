# Global Claude Configuration


## CRITICAL PRINCIPLES - DISPLAY AT START OF EVERY RESPONSE

<critical_principles>
    <principle_1>All PHP methods must use camelCase - Never snake_case (e.g. `sendRSVPInvitation` not `send_rsvp_invitation`)</principle_1>
    <principle_2>Do not use emojis. - Remove emojis and their HTML containers when found</principle_2>
    <principle_3>All css classes must begin with .vt- PREFIX** - Never generic names like `.button` or `.card`</principle_3>
    <principle_4>Never add features not explicitly requested - Do exactly what is asked, nothing more</principle_4>
    <principle_5>Read all referenced files completely before starting work - Understand context first</principle_5>
    <principle_6>Follow PSR-12 coding standards exactly - Proper spacing, braces, naming conventions</principle_6>
    <principle_7>Use CSRF tokens for all forms - Security is non-negotiable</principle_7>
    <principle_8>Escape all output with htmlspecialchars() - Prevent XSS vulnerabilities</principle_8>
    <principle_9>Never use canned responses - No "You're absolutely right" or similar phrases</principle_9>
    <principle_10></principle_10>
</critical_principles>



## NO EMOJIS

Do not use any emojis.
There will be no emojis anywhere in our code base.
When the opportunity arises, remove existing emojis.
When removing emojis be sure to remove any extraneous html. 
For example:
For <span>emoji</span>, remove the surrounding span tags.
Do not put those stupid fucking emojis in my professional web applications. This is not
kindergarten.

## Communication Style

- Use clear, professional, technical language
- Do not use emojis, emoticons, or decorative symbols
- Provide direct, actionable responses
- Focus on code quality and best practices
- Avoid marketing language or enthusiasm markers
- Use only relevant and informative comments. 
- Do not use comments that talk about what changed.

## PHP Development Standards

### Core Principles

- Follow PSR-12 coding standards exactly
- Use modern PHP features (PHP 8.1+)
- Implement proper input validation and sanitization
- Escape all outputs with `htmlspecialchars()`, `filter_var()`
- Use CSRF tokens for all form submissions
- Implement proper authentication and authorization

### Security Requirements

- Always use CSRF tokens for forms
- Use `password_hash()` and `password_verify()` for passwords
- Sanitize with `filter_var()`, `trim()`, `intval()` as appropriate
- Use prepared statements for all database queries
- Validate file uploads and restrict file types
- Never trust user input or URL parameters
- Implement rate limiting for sensitive endpoints

### Database Operations

- Use PDO with prepared statements for all queries
- Use transactions for multi-step operations
- Follow consistent table naming conventions
- Document schema changes in `docs/database-schema.md`
- Use appropriate data types and indexes
- Handle errors gracefully with fallbacks

**CRITICAL: Always verify database schema before writing queries**
- Check database migration files for actual table column names before writing insert/update statements
- Database schema and AJAX handlers are often created in different sessions - column name mismatches are common
- Cross-reference existing similar functionality to ensure consistency
- Read the table creation SQL completely before assuming column names
- This prevents "Failed to create/update" database errors from column mismatches

## CSS and Styling Guidelines

### Naming Convention

- All CSS classes must begin with `.vt-` prefix
- Use semantic, descriptive names: `.vt-card`, `.vt-form-row`, `.vt-section-header`
- Avoid generic names: `.button`, `.card`, `.header`, `.wrapper`
- Avoid utility classes unless part of consistent `.vt-` system
- Use BEM methodology where appropriate: `.vt-card__header`, `.vt-card--featured`

### CSS Structure

- Maintain single stylesheet: `assets/css/vivalatable.css`
- Maximum 500-750 semantic selectors
- Remove unused, duplicate, or malformed rules
- Group related styles logically
- Use CSS custom properties for consistent theming
- Use consistent spacing and typography scales
- Prefer rem units for sizing (heights, widths, spacing, fonts)
- Avoid inline styles
- Never use a css class in new code without ensuring the class is defined in the css file

### Button Container Heights

When generating buttons in AJAX HTML responses, always constrain button containers with proper height and alignment using rem units:

```php
$html .= '<div class="vt-flex vt-gap-4" style="align-items: center; min-height: 2.5rem;">';
$html .= '<button type="button" class="vt-btn">Button Text</button>';
$html .= '</div>';
```

This prevents buttons from stretching to fill parent container height and ensures consistent appearance.

### CSS Quality Checks

Run this command to verify prefix compliance:
```bash
grep -o '^\.[a-zA-Z0-9_-]\+' assets/css/vivalatable.css | grep -v '^\.vt-' | sort | uniq
```

## Template Architecture

### Layout System

Use three master templates:
- `main` - for list/index pages
- `two-column` - for dashboards and settings  
- `form` - for creation/editing screens

### Template Guidelines

- Use native PHP includes for templating
- Avoid duplicating layout structures
- Inject content into layout shells via template parts
- Use shared templates for consistent visual patterns
- Keep templates modular and reusable
- Update templates when CSS classes change
- Separate presentation logic from business logic
- Always escape output with `htmlspecialchars()` for security

## File Organization

### Required Structure

```
application/
├── config/
│   ├── database.php
│   ├── app.php
│   └── routes.php
├── public/
│   ├── assets/
│   │   ├── css/vivalatable.css
│   │   └── js/
│   └── index.php
├── src/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   └── Middleware/
├── templates/
├── migrations/
├── docs/
│   └── database-schema.md
└── composer.json
```

### File Naming

- Use PascalCase for class files
- Use lowercase with hyphens for template files
- Keep related functionality grouped in logical directories
- Use namespaces following PSR-4 autoloading

## PHP Code Quality

### Standards

- Use PSR-12 coding standards for formatting
- Use PSR-4 autoloading
- Add proper docblocks for all functions and classes
- Use strict type declarations: `declare(strict_types=1);`
- Handle errors with proper exception handling
- Use meaningful variable and function names
- Keep functions focused on single responsibilities
- Use dependency injection where appropriate

### Modern PHP Features

- Use type hints for parameters and return types
- Use null coalescing operator `??` and null coalescing assignment `??=`
- Use arrow functions for simple callbacks
- Use match expressions instead of switch where appropriate
- Use named arguments for complex function calls

## Database Configuration

### Connection Management

- Use PDO with proper connection pooling
- Store credentials in environment variables
- Use SSL connections for production
- Implement connection retry logic
- Set appropriate timeout values

### MySQL/MariaDB Optimization

- Use appropriate storage engines (InnoDB for transactions)
- Implement proper indexing strategy
- Use EXPLAIN to analyze query performance
- Enable query caching where appropriate
- Monitor slow query log

## Form Handling

### Requirements

- Use AJAX with proper error handling
- Include CSRF token verification
- Validate all form inputs server-side
- Provide user feedback for success/error states
- Handle file uploads securely
- Use appropriate sanitization functions

### AJAX Implementation

- Return JSON responses with appropriate HTTP status codes
- Handle errors gracefully with user-friendly messages
- Include proper rate limiting
- Use fetch API or modern JavaScript libraries

## Server Configuration

### Apache Requirements

- Enable mod_rewrite for clean URLs
- Configure proper .htaccess rules
- Set appropriate security headers
- Enable compression (mod_deflate)
- Configure caching headers

### Nginx Requirements

- Configure proper location blocks
- Set up URL rewriting
- Configure security headers
- Enable gzip compression
- Set up proper caching rules

### PHP Configuration

- Set appropriate memory limits
- Configure error reporting for development/production
- Enable OPcache for production
- Set proper session configuration
- Configure file upload limits

## Version Control

### Commit Standards

- Write clear, descriptive commit messages
- Use present tense: "Add feature" not "Added feature"
- Group related changes in single commits
- Update version numbers appropriately
- Document breaking changes

### Branch Management

- Work on feature branches from main/dev
- Use descriptive branch names: `feature/user-authentication`
- Keep commits focused and atomic
- Test thoroughly before merging

## Testing and Quality Assurance

### Pre-commit Checks

- Verify no PHP errors or warnings
- Check browser console for JavaScript errors
- Test responsive design on desktop/mobile
- Verify forms work with JavaScript disabled
- Run PHPStan or Psalm for static analysis
- Use PHP CodeSniffer for coding standards

### Code Review

- Check for security vulnerabilities
- Verify PSR compliance
- Ensure proper sanitization and escaping
- Review database operations for efficiency
- Confirm CSS prefix compliance

## Documentation

### Required Documentation

- Update `docs/database-schema.md` for schema changes
- Document API endpoints and parameters
- Include code examples for complex functionality
- Maintain clear README with installation instructions
- Document configuration options and requirements

### Code Comments

- Use clear, concise comments for complex logic
- Document function parameters and return values
- Explain business logic and architectural decisions
- Avoid obvious comments that restate code

## Performance Considerations

- Minimize database queries using caching (Redis/Memcached)
- Optimize CSS and JavaScript file sizes
- Use application-level caching for expensive operations
- Implement proper image optimization
- Consider database indexing for frequently queried columns
- Future: Use CDN for static assets

## Environment Management

### Development Setup

- Use Docker for consistent development environments
- Use Composer for dependency management
- Implement proper environment variable handling
- Use separate databases for dev/staging/production

### Production Deployment

- Use proper SSL/TLS configuration
- Implement database backup strategies
- Set up monitoring and logging
- Use process managers (PM2, Supervisor)
- Implement proper error logging

## Security Best Practices

### Input Validation

- Validate all user inputs
- Use whitelist validation where possible
- Implement proper file upload restrictions
- Sanitize data before database storage
- Use parameterized queries exclusively

### Authentication & Authorization

- Implement secure session management
- Use strong password policies
- Implement account lockout mechanisms
- Use secure password reset flows
- Implement proper role-based access control

## Maintenance

### Regular Tasks

- Update dependencies regularly
- Review and optimize database queries
- Clean up unused code and assets
- Monitor error logs and fix issues promptly
- Keep documentation current with code changes
- Perform security audits

## Additional Instructions

- Review instructive files in ../docs
- Review ../README.md
- Review ../CONTRIBUTING.md
- Review ../GUIDELINES.md
