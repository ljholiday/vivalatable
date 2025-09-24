#!/bin/bash

# VivalaTable Local Development Setup
# Run this script to set up the local development environment

echo "🚀 Setting up VivalaTable local development environment..."

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP not found. Please install PHP 8.0+ first."
    echo "   macOS: brew install php"
    echo "   Ubuntu: sudo apt install php php-mysql php-curl"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [[ $(echo "$PHP_VERSION < 8.0" | bc -l) -eq 1 ]]; then
    echo "❌ PHP $PHP_VERSION found. PHP 8.0+ required."
    exit 1
fi

echo "✅ PHP $PHP_VERSION found"

# Create database configuration if it doesn't exist
if [ ! -f "config/database.php" ]; then
    echo "📝 Creating database configuration..."
    cp config/database.example.php config/database.php
    echo "⚠️  Please edit config/database.php with your database credentials"
else
    echo "✅ Database configuration exists"
fi

# Set proper permissions
chmod 755 assets/uploads
chmod 644 config/database.php

echo ""
echo "🎉 Setup complete!"
echo ""
echo "Next steps:"
echo "1. Edit config/database.php with your database credentials"
echo "2. Create database and import schema:"
echo "   mysql -u username -p database_name < migrations/schema.sql"
echo "3. Start development server:"
echo "   php -S localhost:8000"
echo "4. Visit: http://localhost:8000"
echo ""