<?php
/**
 * Database Migration Script
 * Run this to add the markers table and original_panorama_id column
 * 
 * Usage: php migrate.php
 */

require_once __DIR__ . '/autoload.php';

use App\Database;

echo "Starting database migration...\n\n";

try {
    $db = Database::getInstance();
    
    // Check if original_panorama_id column exists
    $stmt = $db->query("SHOW COLUMNS FROM panoramas LIKE 'original_panorama_id'");
    if ($stmt->rowCount() == 0) {
        echo "Adding original_panorama_id column to panoramas table...\n";
        $db->exec("ALTER TABLE panoramas ADD COLUMN original_panorama_id INT DEFAULT NULL AFTER is_public");
        $db->exec("ALTER TABLE panoramas ADD CONSTRAINT fk_original_panorama FOREIGN KEY (original_panorama_id) REFERENCES panoramas(id) ON DELETE SET NULL");
        $db->exec("ALTER TABLE panoramas ADD INDEX idx_original_panorama_id (original_panorama_id)");
        echo "✓ Added original_panorama_id column\n";
    } else {
        echo "✓ original_panorama_id column already exists\n";
    }
    
    // Check if markers table exists
    $stmt = $db->query("SHOW TABLES LIKE 'markers'");
    if ($stmt->rowCount() == 0) {
        echo "Creating markers table...\n";
        $db->exec("
            CREATE TABLE markers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                panorama_id INT NOT NULL,
                user_id INT NOT NULL,
                yaw DOUBLE NOT NULL,
                pitch DOUBLE NOT NULL,
                type VARCHAR(50) DEFAULT 'text',
                label VARCHAR(200) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (panorama_id) REFERENCES panoramas(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_panorama_id (panorama_id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Created markers table\n";
    } else {
        echo "✓ markers table already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
