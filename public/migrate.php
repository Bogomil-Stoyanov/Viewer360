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
                color VARCHAR(20) DEFAULT 'blue',
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
    
    // Check if color column exists in markers table
    $stmt = $db->query("SHOW COLUMNS FROM markers LIKE 'color'");
    if ($stmt->rowCount() == 0) {
        echo "Adding color column to markers table...\n";
        $db->exec("ALTER TABLE markers ADD COLUMN color VARCHAR(20) DEFAULT 'blue' AFTER type");
        echo "✓ Added color column\n";
    } else {
        echo "✓ color column already exists\n";
    }
    
    // Check if votes table exists
    $stmt = $db->query("SHOW TABLES LIKE 'votes'");
    if ($stmt->rowCount() == 0) {
        echo "Creating votes table...\n";
        $db->exec("
            CREATE TABLE votes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                panorama_id INT NOT NULL,
                value TINYINT NOT NULL COMMENT '1 for upvote, -1 for downvote',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (panorama_id) REFERENCES panoramas(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_panorama_vote (user_id, panorama_id),
                INDEX idx_panorama_id (panorama_id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Created votes table\n";
    } else {
        echo "✓ votes table already exists\n";
    }
    
    // Check if role column exists in users table
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        echo "Adding role column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER password_hash");
        echo "✓ Added role column\n";
    } else {
        echo "✓ role column already exists\n";
    }
    
    // Check if is_banned column exists in users table
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
    if ($stmt->rowCount() == 0) {
        echo "Adding is_banned column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT FALSE AFTER role");
        echo "✓ Added is_banned column\n";
    } else {
        echo "✓ is_banned column already exists\n";
    }
    
    // Check if audio_path column exists in markers table
    $stmt = $db->query("SHOW COLUMNS FROM markers LIKE 'audio_path'");
    if ($stmt->rowCount() == 0) {
        echo "Adding audio_path column to markers table...\n";
        $db->exec("ALTER TABLE markers ADD COLUMN audio_path VARCHAR(255) DEFAULT NULL AFTER description");
        echo "✓ Added audio_path column\n";
    } else {
        echo "✓ audio_path column already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
