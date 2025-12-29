<?php
/**
 * Admin Promotion Script
 * Run this script to promote a specific user to admin role
 * 
 * Usage: php promote_admin.php [user_id]
 * Default: Promotes user with ID 1
 */

require_once __DIR__ . '/autoload.php';

use App\Database;

$userId = isset($argv[1]) ? (int)$argv[1] : 1;

echo "=================================\n";
echo "Admin Promotion Script\n";
echo "=================================\n\n";

try {
    $db = Database::getInstance();
    
    // First, check if role column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Error: 'role' column does not exist. Please run migrate.php first.\n";
        exit(1);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Error: User with ID {$userId} not found.\n";
        exit(1);
    }
    
    echo "Found user:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Username: {$user['username']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Current Role: " . ($user['role'] ?? 'user') . "\n\n";
    
    if ($user['role'] === 'admin') {
        echo "✓ User is already an admin.\n";
        exit(0);
    }
    
    // Promote to admin
    $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $stmt->execute([$userId]);
    
    echo "✅ User '{$user['username']}' has been promoted to admin!\n\n";
    echo "You can now log in and access /admin/dashboard.php\n";
    
} catch (\PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
