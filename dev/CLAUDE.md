# VivalaTable Claude Configuration

- "NEVER add features not explicitly requested"
- "ALWAYS complete the exact task requested before doing anything else"
- "STOP after completing the requested task - do not add extra features"
- "READ ALL REFERENCED FILES COMPLETELY before starting work"

## NO EMOJIS

Do not use any emojis.
There will be no emojis anywhere in our codebase.
When the opportunity arises, remove existing emojis.
When removing emojis be sure to remove any extraneous html.
Do not put emojis in this professional web application.

## Communication Style

- Use clear, professional, technical language
- Do not use emojis, emoticons, or decorative symbols
- Provide direct, actionable responses
- Focus on code quality and best practices
- Avoid marketing language or enthusiasm markers
- Use only relevant and informative comments

## LAMP Stack Development Standards

### Core Principles

- Follow PHP best practices and security standards
- Use PDO prepared statements for all database operations
- Sanitize all inputs with appropriate functions
- Escape all outputs with proper escaping functions
- Use proper session management and CSRF protection
- Implement proper capability checks for admin functionality

### Security Requirements

- Always use prepared statements for database queries
- Sanitize with `vt_sanitize_text()`, `vt_sanitize_textarea()`, `intval()` as appropriate
- Validate file uploads and restrict file types
- Never trust user input or URL parameters
- Use token-based authentication for guest systems

### Database Operations

- Use `Database::getInstance()` singleton pattern
- Use `Database::table()` for prefixed table names
- Follow database transaction patterns for complex operations
- Document schema in `migrations/schema.sql`
- Handle errors gracefully with proper logging
- Use appropriate data types and indexes

### CSS and Styling Guidelines

#### Naming Convention

- All CSS classes must begin with `.pm-` prefix
- Use semantic, descriptive names: `.pm-card`, `.pm-form-row`, `.pm-section-header`
- Avoid generic names: `.button`, `.card`, `.header`, `.wrapper`
- Maintain single stylesheet: `assets/css/partyminder.css`
- No inline styles

### Template Architecture

#### Layout System

Use base template with content injection:
- `templates/base/page.php` - Main layout template
- Content pages use `vt_load_template()` function
- Keep templates modular and reusable

### File Organization

```
vivalatable.com/
├── assets/
│   ├── css/partyminder.css
│   └── uploads/
├── classes/
├── config/
├── includes/
├── migrations/
├── public/
├── templates/
└── index.php
```

## PHP Code Quality

### Standards

- Use type hints where appropriate (PHP 7.4+)
- Handle errors with proper error checking and logging
- Use meaningful variable and function names
- Keep functions focused on single responsibilities
- Add proper docblocks for all functions and classes

### VivalaTable Integration

- Use `vt_` prefix for all global functions
- Register scripts and styles properly
- Use VivalaTable base URL functions
- Implement proper authentication checks
- Use VivalaTable logging functions

## Form Handling

### Requirements

- Use proper CSRF token verification
- Validate all form inputs server-side
- Provide user feedback for success/error states
- Handle file uploads securely
- Use VivalaTable sanitization functions

## Database Schema

### Migration Pattern

- All changes go in `migrations/schema.sql`
- Use `vt_` table prefix consistently
- Document foreign key relationships
- Include proper indexes for performance
- Use appropriate character sets (utf8mb4)

## Guest System Architecture

### Token-Based Authentication

- Use 32-character secure tokens
- Store in `guest_invitations` table
- Link to events via `event_id`
- Support guest-to-user conversion
- Maintain email-based identification

## Testing Requirements

### Before Any Deployment

- Test with Local by Flywheel setup
- Verify database connection
- Check for PHP errors in logs
- Test user authentication flows
- Verify guest invitation system
- Test responsive design

## Performance Considerations

- Minimize database queries using proper query design
- Use database transactions for complex operations
- Implement proper caching where appropriate
- Optimize images and assets
- Consider database indexing for frequently queried columns

## Maintenance

### Regular Tasks

- Monitor error logs and fix issues promptly
- Keep database schema documentation current
- Review and optimize database queries
- Clean up unused code and assets