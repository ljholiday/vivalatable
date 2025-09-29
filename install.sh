#!/bin/bash

# VivalaTable Production Installation Script
# Run this after pulling the repository to production
# Usage: chmod +x install.sh && ./install.sh

set -e  # Exit on any error

echo "🚀 VivalaTable Production Installation"
echo "======================================"
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print status messages
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "config/schema.sql" ]; then
    print_error "config/schema.sql not found. Are you in the VivalaTable root directory?"
    exit 1
fi

if [ ! -f "config/database.php" ]; then
    print_error "config/database.php not found. Please ensure database configuration exists."
    exit 1
fi

print_status "Starting VivalaTable installation..."

# Step 1: Database Setup
echo
print_status "Step 1: Setting up database..."

# Check if PHP is available
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed or not in PATH"
    exit 1
fi

# Test database connection first
print_status "Testing database connection..."
php -r "
require_once 'includes/bootstrap.php';
try {
    \$db = VT_Database::getInstance();
    if (\$db) {
        echo 'Database connection successful' . PHP_EOL;
    } else {
        echo 'Database connection failed' . PHP_EOL;
        exit(1);
    }
} catch (Exception \$e) {
    echo 'Database connection error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    print_success "Database connection verified"
else
    print_error "Database connection failed. Please check config/database.php"
    exit 1
fi

# Import schema
print_status "Creating database tables from schema..."
php -r "
require_once 'includes/bootstrap.php';
try {
    \$db = VT_Database::getInstance();
    \$schema = file_get_contents('config/schema.sql');

    // Split by semicolon and execute each statement
    \$statements = array_filter(array_map('trim', explode(';', \$schema)));

    foreach (\$statements as \$statement) {
        if (!empty(\$statement) && !preg_match('/^--/', \$statement)) {
            \$db->query(\$statement);
        }
    }

    echo 'Schema imported successfully' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Schema import error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    print_success "Database schema imported"
else
    print_error "Failed to import database schema"
    exit 1
fi

# Run migrations
print_status "Running database migrations..."
php migrate.php

if [ $? -eq 0 ]; then
    print_success "Migrations completed"
else
    print_warning "Migrations encountered issues (may be expected if already applied)"
fi

# Step 2: File Permissions
echo
print_status "Step 2: Setting file permissions..."

# Set ownership (assuming www-data, adjust if needed)
if [ -d "/var/www" ] && [ "$(whoami)" = "root" ]; then
    print_status "Setting ownership to www-data..."
    chown -R www-data:www-data .
fi

# Set secure permissions
print_status "Setting secure file permissions..."

# General files and directories
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x install.sh

# Uploads directory (already has correct permissions but ensure it exists)
if [ ! -d "uploads" ]; then
    mkdir -p uploads
    print_status "Created uploads directory"
fi

# Verify uploads permissions
UPLOADS_PERMS=$(stat -c %a uploads 2>/dev/null || stat -f %A uploads 2>/dev/null || echo "unknown")
if [ "$UPLOADS_PERMS" = "775" ] || [ "$UPLOADS_PERMS" = "755" ]; then
    print_success "Uploads directory has secure permissions ($UPLOADS_PERMS)"
else
    print_status "Setting uploads directory permissions to 775..."
    chmod 775 uploads
fi

# Protect sensitive files
if [ -f "config/database.php" ]; then
    chmod 640 config/database.php
    print_success "Protected config/database.php"
fi

print_success "File permissions configured"

# Step 3: Production Verification
echo
print_status "Step 3: Verifying installation..."

# Test bootstrap loading
print_status "Testing application bootstrap..."
php -r "
require_once 'includes/bootstrap.php';
echo 'Bootstrap loaded successfully' . PHP_EOL;
"

if [ $? -eq 0 ]; then
    print_success "Application bootstrap working"
else
    print_error "Bootstrap failed to load"
    exit 1
fi

# Verify database tables exist
print_status "Verifying database tables..."
php -r "
require_once 'includes/bootstrap.php';
try {
    \$db = VT_Database::getInstance();

    // Check for key tables
    \$tables = ['vt_events', 'vt_users', 'vt_communities', 'vt_conversations'];
    \$missing = [];

    foreach (\$tables as \$table) {
        \$result = \$db->query(\"SHOW TABLES LIKE '\$table'\");
        if (\$result->rowCount() == 0) {
            \$missing[] = \$table;
        }
    }

    if (empty(\$missing)) {
        echo 'All required tables exist' . PHP_EOL;
    } else {
        echo 'Missing tables: ' . implode(', ', \$missing) . PHP_EOL;
        exit(1);
    }
} catch (Exception \$e) {
    echo 'Table verification error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    print_success "Database tables verified"
else
    print_error "Database table verification failed"
    exit 1
fi

# Final status
echo
print_success "🎉 VivalaTable installation completed successfully!"
echo
echo "Next steps:"
echo "1. Configure your web server (Apache/Nginx) to point to this directory"
echo "2. Set up SSL certificate for HTTPS"
echo "3. Test the application by visiting your domain"
echo "4. Create your first admin user account"
echo
print_warning "Don't forget to:"
print_warning "- Set up automated backups"
print_warning "- Configure log rotation"
print_warning "- Monitor application logs"
echo

exit 0