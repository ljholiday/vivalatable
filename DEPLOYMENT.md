# VivalaTable Deployment Guide

## Quick Deployment Steps

1. **Clone to server:**
   ```bash
   git clone <repository-url> vivalatable
   cd vivalatable
   ```

2. **Configure database:**
   ```bash
   cp config/database.example.php config/database.php
   # Edit config/database.php with your database credentials
   ```

3. **Set permissions:**
   ```bash
   chmod 755 assets/uploads
   chmod 644 config/database.php
   ```

4. **Import database:**
   ```bash
   mysql -u username -p database_name < migrations/schema.sql
   ```

5. **Configure web server to point to this directory as document root**

## Web Server Configuration

### Apache
```apache
DocumentRoot /path/to/vivalatable
<Directory /path/to/vivalatable>
    AllowOverride All
    Require all granted
</Directory>
```

### Nginx
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/vivalatable;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Environment Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)

## Post-Deployment

1. Test database connection: Visit `/api/test`
2. Create admin user: Visit `/register`
3. Test functionality: Create event, community, conversation

## Security Notes

- `config/database.php` is excluded from git
- Upload directory has proper permissions
- All user inputs are sanitized
- Database uses prepared statements

## Directory Structure After Deployment

```
vivalatable/
├── assets/          # CSS, JS, images, uploads
├── classes/         # PHP classes
├── config/          # Configuration files
├── dev/            # Development documentation
├── includes/       # Core functions and bootstrap
├── migrations/     # Database schema
├── public/         # Public pages
├── templates/      # Template files
├── .gitignore      # Git ignore rules
├── index.php       # Main entry point
└── README.md       # Project documentation
```