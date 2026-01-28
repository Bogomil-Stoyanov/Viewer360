#!/bin/bash
set -e

# Directory paths
UPLOADS_DIR="/var/www/html/public/uploads"
SAMPLE_DIR="/var/www/html/sample-uploads"
INIT_FLAG="/var/www/html/.initialized"

# Check if uploads directory is empty (new volume) and sample files exist
if [ -d "$SAMPLE_DIR" ] && [ "$(ls -A $SAMPLE_DIR 2>/dev/null)" ]; then
    # Check if uploads is empty or only has .gitkeep
    if [ ! "$(ls -A $UPLOADS_DIR 2>/dev/null | grep -v '.gitkeep')" ]; then
        echo "📁 Initializing uploads directory with sample files..."
        cp -r "$SAMPLE_DIR"/* "$UPLOADS_DIR"/
        chown -R www-data:www-data "$UPLOADS_DIR"
        chmod -R 775 "$UPLOADS_DIR"
        echo "✅ Sample files copied to uploads directory"
    else
        echo "📁 Uploads directory already has files, skipping sample copy"
    fi
fi

# Ensure proper permissions on uploads directory
chown -R www-data:www-data "$UPLOADS_DIR"
chmod -R 775 "$UPLOADS_DIR"

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if php -r "
        \$host = getenv('DB_HOST') ?: 'localhost';
        \$user = getenv('DB_USER') ?: 'viewer360_user';
        \$pass = getenv('DB_PASS') ?: 'viewer360_pass';
        \$db = getenv('DB_NAME') ?: 'viewer360';
        try {
            new PDO(\"mysql:host=\$host;dbname=\$db\", \$user, \$pass);
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "✅ Database is ready!"
        break
    fi
    attempt=$((attempt + 1))
    echo "   Attempt $attempt/$max_attempts - Database not ready, waiting..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "❌ Database connection failed after $max_attempts attempts"
    echo "   Continuing anyway - migrations may fail"
fi

# Run migrations (always safe to run - they check for existing tables)
echo "🔄 Running database migrations..."
php /var/www/html/public/migrate.php || echo "⚠️  Migration had issues (may be okay if already migrated)"

# Run seeder only on first initialization
if [ ! -f "$INIT_FLAG" ]; then
    echo "🌱 First run detected - running database seeder..."
    php /var/www/html/public/seed.php && echo "✅ Database seeded successfully" || echo "⚠️  Seeding skipped (database may already have data)"
    
    # Create initialization flag
    touch "$INIT_FLAG"
    echo "✅ Initialization complete!"
else
    echo "📋 Already initialized, skipping seeder"
fi

echo ""
echo "🚀 Starting Apache..."
echo "   Application available at http://localhost:8080"
echo ""

# Execute the main command (Apache)
exec "$@"
