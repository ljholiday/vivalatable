# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

VivalaTable is a PHP-based community and event management platform. It's a custom MVC framework application focused on security, modularity, and clean code separation.

## Key Commands

### Installation & Setup
```bash
# Install from scratch
./install.sh

# Run database migrations
php migrate.php

# Reset database (development)
mysql -u root -p -e "DROP DATABASE IF EXISTS vivalatable; CREATE DATABASE vivalatable;"
```

### Development Workflow
```bash
# Check file permissions
ls -la uploads/

# Reset file permissions if needed
sudo chown -R www-data:www-data /path/to/vivalatable
sudo chmod -R 755 /path/to/vivalatable
sudo chmod 775 uploads/
```

### Testing Database Connection
```bash
# Test database connection through PHP
php -r "require_once 'includes/bootstrap.php'; echo 'Database connected successfully';"
```

## Architecture & Structure

### Core Philosophy
- **Strict Language Separation**: PHP for logic, HTML for structure, CSS for presentation, JS for behavior
- **Security-First**: All input validated, output escaped, CSRF protection, prepared statements
- **Custom MVC Framework**: Native PHP implementation with modern PHP 8.1+ features

### Directory Structure
```
vivalatable/
├── includes/         # Core PHP classes and business logic
├── templates/        # HTML templates and views
│   ├── base/        # Base template layouts
│   ├── partials/    # Reusable template components
│   └── emails/      # Email templates
├── assets/          # Static files
│   ├── css/         # Stylesheets (.vt- prefix naming)
│   └── js/          # JavaScript files
├── config/          # Configuration files
├── uploads/         # User-uploaded files (775 permissions)
└── dev/             # Development standards (XML files)
```

### Core Classes Architecture
The application follows a class-based architecture with these key components:

- **VT_Database**: Singleton database connection manager
- **VT_Auth**: Authentication and session management
- **VT_Security**: CSRF protection and security utilities
- **VT_Router**: URL routing and request handling
- **Manager Classes**: Business logic (Event, Community, Conversation, etc.)
- **AJAX Handlers**: Dedicated classes for AJAX endpoints
- **VT_Sanitize**: Input validation and sanitization

### Template System
- Base templates in `templates/base/` for layout structure
- Content templates for each page/feature
- Partials for reusable components
- Email templates in `templates/emails/`

## Development Standards

### Code Organization Rules
1. **No inline JavaScript/CSS** - Externalize to `assets/js/` and `assets/css/`
2. **Language Separation** - Keep PHP, HTML, CSS, and JS in separate files
3. **Single Responsibility** - Each file serves one purpose
4. **Security Boundaries** - PHP is the trust boundary for all data

### PHP Standards (from dev/php.xml)
- Use camelCase for methods and functions
- Follow PSR-12 coding standards exactly
- Modern PHP 8.1+ features with strict typing
- Use `match` expressions over `switch` when possible
- Dependency injection where appropriate

### Security Requirements (from dev/security.xml)
- Validate all input server-side with whitelist approach
- Use `password_hash()` and `password_verify()` for authentication
- CSRF tokens in all state-changing forms
- Escape output with `htmlspecialchars()` or context-appropriate functions
- Rate limiting on sensitive endpoints
- Secure session management with HTTP-only cookies

### CSS Standards
- All CSS must use `.vt-` prefix naming convention
- External stylesheets only, no inline styles
- BEM methodology where appropriate

### Database Standards
- Use prepared statements exclusively
- PDO with proper error handling and fetch modes
- Migration system via `migrate.php`

## Key Configuration

### Database Configuration
Copy `config/database.php.sample` to `config/database.php` with proper credentials:
```php
return [
    'host' => 'localhost',
    'dbname' => 'vivalatable',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

### Required File Permissions
- Directories: 755
- Files: 644
- Uploads directory: 775 (web server writable)
- Config files: 640 (restricted)

## Common Development Patterns

### Adding New Features
1. Create business logic class in `includes/class-[feature]-manager.php`
2. Create AJAX handler if needed in `includes/class-[feature]-ajax-handler.php`
3. Add template in `templates/[feature]-content.php`
4. Add JavaScript in `assets/js/[feature].js`
5. Add styles in `assets/css/style.css` with `.vt-` prefix

### Database Schema Changes
Run migrations through `migrate.php` - never modify database directly in production.

### Authentication Flow
- VT_Auth handles all authentication
- Session management with secure cookies
- Username/display_name convention established
- Password reset with secure tokens

## Security Notes
- All forms must include CSRF tokens
- Input validation happens server-side in PHP
- User roles and permissions controlled via VT_Auth
- File uploads restricted and validated
- Rate limiting implemented on sensitive endpoints