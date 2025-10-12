#!/usr/bin/env bash
set -e

echo "====================================="
echo " VivalaTable Installation"
echo "====================================="

# ----------------------------------------------------------------------
# Detect project root (where this script lives)
# ----------------------------------------------------------------------
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
echo "[INFO] Current directory: $PROJECT_DIR"

# ----------------------------------------------------------------------
# Step 1: Test database connection
# ----------------------------------------------------------------------
echo
echo "[INFO] Step 1: Testing database connection..."

php -d display_errors=1 -r "
require_once '$PROJECT_DIR/src/bootstrap.php';
use App\Database\Database;

try {
    \$db = new Database();
    echo \"[OK] Database connection successful\n\";
} catch (Throwable \$e) {
    echo \"[ERROR] Database connection failed: {\$e->getMessage()}\n\";
    exit(1);
}
"

# ----------------------------------------------------------------------
# Step 2: Create tables (from config/schema.sql)
# ----------------------------------------------------------------------
SCHEMA_FILE=\"$PROJECT_DIR/config/schema.sql\"

if [ -f \"$SCHEMA_FILE\" ]; then
    echo
    echo \"[INFO] Step 2: Creating database tables from config/schema.sql...\"
    php -d display_errors=1 -r "
    require_once '$PROJECT_DIR/src/bootstrap.php';
    use App\Database\Database;

    \$db = new Database();
    \$pdo = \$db->pdo();
    \$sql = file_get_contents('$SCHEMA_FILE');
    try {
        \$pdo->exec(\$sql);
        echo \"[OK] Tables created successfully\n\";
    } catch (Throwable \$e) {
        echo \"[ERROR] Failed to create tables: {\$e->getMessage()}\n\";
        exit(1);
    }
    "
else
    echo
    echo \"[WARN] No schema.sql found at: $SCHEMA_FILE\"
fi

# ----------------------------------------------------------------------
# Step 3: Seed data (optional config/seed.sql)
# ----------------------------------------------------------------------
SEED_FILE=\"$PROJECT_DIR/config/seed.sql\"

if [ -f \"$SEED_FILE\" ]; then
    echo
    echo \"[INFO] Step 3: Seeding initial data from config/seed.sql...\"
    php -d display_errors=1 -r "
    require_once '$PROJECT_DIR/src/bootstrap.php';
    use App\Database\Database;

    \$db = new Database();
    \$pdo = \$db->pdo();
    \$sql = file_get_contents('$SEED_FILE');
    try {
        \$pdo->exec(\$sql);
        echo \"[OK] Seed data inserted\n\";
    } catch (Throwable \$e) {
        echo \"[ERROR] Failed to seed data: {\$e->getMessage()}\n\";
        exit(1);
    }
    "
else
    echo
    echo \"[INFO] No seed.sql file found. Skipping data seeding.\"
fi

# ----------------------------------------------------------------------
# Done
# ----------------------------------------------------------------------
echo
echo \"[DONE] VivalaTable installation completed.\"

