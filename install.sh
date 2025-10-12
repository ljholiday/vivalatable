#!/bin/bash
# VivalaTable Installer (cPanel-safe, single-script version)
# Usage: chmod +x install.sh && ./install.sh

set -e  # stop on first error

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
ok()      { echo -e "${GREEN}[OK]${NC} $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
fail()    { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

echo "====================================="
echo " VivalaTable Installation"
echo "====================================="

# ---------------------------------------------------------------------
# 0. sanity checks
# ---------------------------------------------------------------------
if [ ! -f "config/database.php" ]; then
    fail "config/database.php not found. Copy and edit it first."
fi

if [ ! -f "config/schema.sql" ]; then
    fail "config/schema.sql missing."
fi

if ! command -v php >/dev/null 2>&1; then
    fail "PHP not found in PATH."
fi

info "Current directory: $(pwd)"
echo

# ---------------------------------------------------------------------
# 1. test database connection
# ---------------------------------------------------------------------
info "Step 1: Testing database connection..."

php <<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/bootstrap.php';

try {
    $db = VT_Database::getInstance();
    echo "[OK] Database connection successful\n";
} catch (Throwable $e) {
    echo "[ERROR] Database connection failed: {$e->getMessage()}\n";
    exit(1);
}
PHP

if [ $? -ne 0 ]; then fail "Database connection failed."; fi
ok "Database connection verified."

# ---------------------------------------------------------------------
# 2. import schema
# ---------------------------------------------------------------------
info "Step 2: Importing schema from config/schema.sql ..."

php <<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/bootstrap.php';

try {
    $db = VT_Database::getInstance();
    $schema = file_get_contents(__DIR__ . '/config/schema.sql');
    if (!$schema) throw new Exception("Unable to read schema.sql");

    $stmts = array_filter(array_map('trim', explode(';', $schema)));
    $count = 0;
    foreach ($stmts as $s) {
        if ($s === '' || str_starts_with($s, '--')) continue;
        $db->query($s);
        $count++;
    }
    echo "[OK] Imported $count SQL statements\n";
} catch (Throwable $e) {
    echo "[ERROR] Schema import failed: {$e->getMessage()}\n";
    exit(1);
}
PHP

if [ $? -ne 0 ]; then fail "Schema import failed."; fi
ok "Schema imported."

# ---------------------------------------------------------------------
# 3. permissions
# ---------------------------------------------------------------------
info "Step 3: Setting file permissions..."

find . -type d -exec chmod 755 {} \; 2>/dev/null
find . -type f -exec chmod 644 {} \; 2>/dev/null
[ -d uploads ] || mkdir uploads
chmod 775 uploads
chmod 640 config/database.php

ok "Permissions applied."

# ---------------------------------------------------------------------
# 4. verify tables
# ---------------------------------------------------------------------
info "Step 4: Verifying key tables..."

php <<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/bootstrap.php';

try {
    $db = VT_Database::getInstance();
    $expected = ['vt_users','vt_events','vt_communities'];
    $missing = [];
    foreach ($expected as $t) {
        $r = $db->query("SHOW TABLES LIKE '$t'");
        if ($r->rowCount() == 0) $missing[] = $t;
    }
    if ($missing) {
        echo "[ERROR] Missing tables: " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "[OK] All key tables present\n";
} catch (Throwable $e) {
    echo "[ERROR] Verification failed: {$e->getMessage()}\n";
    exit(1);
}
PHP

if [ $? -ne 0 ]; then fail "Table verification failed."; fi
ok "Installation verified."

# ---------------------------------------------------------------------
# done
# ---------------------------------------------------------------------
echo
ok "🎉 VivalaTable installation completed successfully."
warn "Next steps:"
echo "  • Point your web server to this directory"
echo "  • Visit your domain to complete setup"
echo "  • Remember to secure config/database.php"
echo
exit 0

