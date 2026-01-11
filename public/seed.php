<?php

require_once __DIR__ . '/autoload.php';

use App\Database;
use App\Config;

$isCliMode = php_sapi_name() === 'cli';

if (!$isCliMode) {
    $stmt = Database::query("SELECT COUNT(*) as count FROM users");
    $userCount = (int)$stmt->fetch()['count'];
    
    if ($userCount > 0) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Seed - Viewer360</title></head><body>';
        echo '<h1>⚠️ Database Already Has Data</h1>';
        echo '<p>Found ' . $userCount . ' existing users. Seeding aborted to prevent data conflicts.</p>';
        echo '<p>To force re-seed, run from CLI: <code>php seed.php --force</code></p>';
        echo '<p><a href="/">← Back to Home</a></p>';
        echo '</body></html>';
        exit;
    }
}

$forceMode = $isCliMode && in_array('--force', $argv ?? []);

echo $isCliMode ? "\n" : '<pre>';
echo "===========================================\n";
echo "  Viewer360 Database Seeder\n";
echo "===========================================\n\n";

try {
    $pdo = Database::getInstance();
    
    if ($forceMode) {
        echo "🗑️  Force mode: Clearing existing data...\n";
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE votes");
        $pdo->exec("TRUNCATE TABLE markers");
        $pdo->exec("TRUNCATE TABLE panoramas");
        $pdo->exec("TRUNCATE TABLE users");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "   ✓ Tables cleared\n\n";
    }

    echo "👤 Creating users...\n";
    
    $users = [
        [
            'username' => 'admin',
            'email' => 'admin@viewer360.local',
            'password' => 'admin123',
            'role' => 'admin',
            'is_banned' => false
        ],
        [
            'username' => 'superadmin',
            'email' => 'superadmin@viewer360.local',
            'password' => 'super123',
            'role' => 'admin',
            'is_banned' => false
        ],
        [
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'user',
            'is_banned' => false
        ],
        [
            'username' => 'jane_smith',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'user',
            'is_banned' => false
        ],
        [
            'username' => 'photo_lover',
            'email' => 'photo@example.com',
            'password' => 'password123',
            'role' => 'user',
            'is_banned' => false
        ],
        [
            'username' => 'traveler42',
            'email' => 'traveler@example.com',
            'password' => 'password123',
            'role' => 'user',
            'is_banned' => false
        ],
        [
            'username' => 'banned_user',
            'email' => 'banned@example.com',
            'password' => 'password123',
            'role' => 'user',
            'is_banned' => true
        ],
    ];

    $userIds = [];
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password_hash, role, is_banned) VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($users as $user) {
        $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $user['username'],
            $user['email'],
            $passwordHash,
            $user['role'],
            $user['is_banned'] ? 1 : 0
        ]);
        $userIds[$user['username']] = $pdo->lastInsertId();
        $roleIcon = $user['role'] === 'admin' ? '👑' : '👤';
        $banStatus = $user['is_banned'] ? ' [BANNED]' : '';
        echo "   $roleIcon {$user['username']} ({$user['email']}) - Password: {$user['password']}$banStatus\n";
    }
    echo "   ✓ Created " . count($users) . " users\n\n";

    echo "🖼️  Creating panoramas...\n";
    
    $panoramas = [
        [
            'user' => 'john_doe',
            'title' => 'Mountain Sunrise Vista',
            'description' => 'A breathtaking 360° view from the mountain peak at sunrise. The golden light illuminates the valleys below.',
            'file_path' => 'uploads/sample_mountain.jpg',
            'is_public' => true
        ],
        [
            'user' => 'john_doe',
            'title' => 'City Skyline at Night',
            'description' => 'Downtown city panorama captured during blue hour with all the lights coming on.',
            'file_path' => 'uploads/sample_city.jpg',
            'is_public' => true
        ],
        [
            'user' => 'john_doe',
            'title' => 'My Private Garden',
            'description' => 'Personal garden panorama - keeping this one private.',
            'file_path' => 'uploads/sample_garden.jpg',
            'is_public' => false
        ],
        [
            'user' => 'jane_smith',
            'title' => 'Beach Paradise',
            'description' => 'Crystal clear waters and white sandy beach. Perfect vacation spot!',
            'file_path' => 'uploads/sample_beach.jpg',
            'is_public' => true
        ],
        [
            'user' => 'jane_smith',
            'title' => 'Ancient Temple Interior',
            'description' => 'Inside a 500-year-old temple with intricate carvings on every surface.',
            'file_path' => 'uploads/sample_temple.jpg',
            'is_public' => true
        ],
        [
            'user' => 'photo_lover',
            'title' => 'Forest Trail in Autumn',
            'description' => 'Walking through a colorful autumn forest. The leaves create a golden canopy.',
            'file_path' => 'uploads/sample_forest.jpg',
            'is_public' => true
        ],
        [
            'user' => 'photo_lover',
            'title' => 'Modern Art Museum',
            'description' => 'Inside the contemporary art wing with stunning architecture.',
            'file_path' => 'uploads/sample_museum.jpg',
            'is_public' => true
        ],
        [
            'user' => 'traveler42',
            'title' => 'Desert Dunes at Sunset',
            'description' => 'Sahara desert panorama with endless sand dunes stretching to the horizon.',
            'file_path' => 'uploads/sample_desert.jpg',
            'is_public' => true
        ],
        [
            'user' => 'traveler42',
            'title' => 'Northern Lights Display',
            'description' => 'Aurora borealis dancing across the night sky in Iceland.',
            'file_path' => 'uploads/sample_aurora.jpg',
            'is_public' => true
        ],
        [
            'user' => 'admin',
            'title' => 'Office Tour - Main Lobby',
            'description' => 'Welcome to Viewer360 headquarters! Start your virtual tour here.',
            'file_path' => 'uploads/sample_office_lobby.jpg',
            'is_public' => true
        ],
        [
            'user' => 'admin',
            'title' => 'Office Tour - Conference Room',
            'description' => 'Our main conference room with panoramic city views.',
            'file_path' => 'uploads/sample_office_conf.jpg',
            'is_public' => true
        ],
    ];

    $panoramaIds = [];
    $stmt = $pdo->prepare(
        "INSERT INTO panoramas (user_id, file_path, title, description, is_public) VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($panoramas as $pano) {
        $userId = $userIds[$pano['user']];
        $stmt->execute([
            $userId,
            $pano['file_path'],
            $pano['title'],
            $pano['description'],
            $pano['is_public'] ? 1 : 0
        ]);
        $panoId = $pdo->lastInsertId();
        $panoramaIds[$pano['title']] = $panoId;
        $visibility = $pano['is_public'] ? '🌍 Public' : '🔒 Private';
        echo "   📷 [{$pano['user']}] {$pano['title']} - $visibility\n";
    }
    echo "   ✓ Created " . count($panoramas) . " panoramas\n\n";

    echo "📍 Creating markers...\n";
    
    $colors = ['blue', 'red', 'green', 'yellow', 'orange', 'purple', 'pink', 'cyan', 'white'];
    
    $markers = [
        [
            'panorama' => 'Mountain Sunrise Vista',
            'user' => 'john_doe',
            'yaw' => 0.5,
            'pitch' => 0.1,
            'type' => 'text',
            'color' => 'yellow',
            'label' => 'Rising Sun',
            'description' => 'Watch the sun peek over the eastern ridge. Best viewed between 6-7 AM.'
        ],
        [
            'panorama' => 'Mountain Sunrise Vista',
            'user' => 'john_doe',
            'yaw' => -1.2,
            'pitch' => -0.3,
            'type' => 'text',
            'color' => 'green',
            'label' => 'Valley Below',
            'description' => 'The village in the valley is about 2000 feet below this viewpoint.'
        ],
        [
            'panorama' => 'Mountain Sunrise Vista',
            'user' => 'john_doe',
            'yaw' => 2.1,
            'pitch' => 0.0,
            'type' => 'text',
            'color' => 'blue',
            'label' => 'Hiking Trail',
            'description' => 'This trail leads to the summit - approximately 3 hours from here.'
        ],
        
        [
            'panorama' => 'City Skyline at Night',
            'user' => 'john_doe',
            'yaw' => 0.0,
            'pitch' => 0.2,
            'type' => 'text',
            'color' => 'purple',
            'label' => 'Central Tower',
            'description' => 'The tallest building in the city at 450 meters. Has an observation deck on floor 100.'
        ],
        [
            'panorama' => 'City Skyline at Night',
            'user' => 'john_doe',
            'yaw' => 1.5,
            'pitch' => -0.1,
            'type' => 'text',
            'color' => 'cyan',
            'label' => 'River District',
            'description' => 'The historic waterfront area with restaurants and nightlife.'
        ],
        
        [
            'panorama' => 'Beach Paradise',
            'user' => 'jane_smith',
            'yaw' => 0.0,
            'pitch' => 0.0,
            'type' => 'text',
            'color' => 'blue',
            'label' => 'Snorkeling Spot',
            'description' => 'Best snorkeling area - coral reef starts about 50 meters out.'
        ],
        [
            'panorama' => 'Beach Paradise',
            'user' => 'jane_smith',
            'yaw' => -2.5,
            'pitch' => 0.1,
            'type' => 'text',
            'color' => 'orange',
            'label' => 'Beach Bar',
            'description' => 'Fresh coconuts and tropical drinks available here!'
        ],
        [
            'panorama' => 'Beach Paradise',
            'user' => 'jane_smith',
            'yaw' => 1.8,
            'pitch' => -0.2,
            'type' => 'text',
            'color' => 'white',
            'label' => 'Turtle Nesting Area',
            'description' => 'Sea turtles nest here between May and October. Please keep distance.'
        ],
        
        [
            'panorama' => 'Ancient Temple Interior',
            'user' => 'jane_smith',
            'yaw' => 0.3,
            'pitch' => 0.4,
            'type' => 'text',
            'color' => 'red',
            'label' => 'Sacred Altar',
            'description' => 'The main altar where ceremonies have been held for 500 years.'
        ],
        [
            'panorama' => 'Ancient Temple Interior',
            'user' => 'jane_smith',
            'yaw' => -1.0,
            'pitch' => 0.3,
            'type' => 'text',
            'color' => 'yellow',
            'label' => 'Ancient Murals',
            'description' => 'These murals depict the founding legend of the temple.'
        ],
        [
            'panorama' => 'Ancient Temple Interior',
            'user' => 'jane_smith',
            'yaw' => 2.8,
            'pitch' => -0.1,
            'type' => 'text',
            'color' => 'green',
            'label' => 'Exit to Gardens',
            'description' => 'Through this door lies the meditation garden.'
        ],
        
        [
            'panorama' => 'Forest Trail in Autumn',
            'user' => 'photo_lover',
            'yaw' => 0.0,
            'pitch' => 0.5,
            'type' => 'text',
            'color' => 'orange',
            'label' => 'Golden Canopy',
            'description' => 'Peak autumn colors usually occur in mid-October.'
        ],
        [
            'panorama' => 'Forest Trail in Autumn',
            'user' => 'photo_lover',
            'yaw' => -0.8,
            'pitch' => -0.3,
            'type' => 'text',
            'color' => 'green',
            'label' => 'Wild Mushrooms',
            'description' => 'Various edible mushrooms grow here, but only pick if you\'re an expert!'
        ],
        
        [
            'panorama' => 'Desert Dunes at Sunset',
            'user' => 'traveler42',
            'yaw' => 0.2,
            'pitch' => 0.1,
            'type' => 'text',
            'color' => 'red',
            'label' => 'Setting Sun',
            'description' => 'The dunes turn deep orange and red as the sun sets.'
        ],
        [
            'panorama' => 'Desert Dunes at Sunset',
            'user' => 'traveler42',
            'yaw' => -1.5,
            'pitch' => -0.2,
            'type' => 'text',
            'color' => 'yellow',
            'label' => 'Camel Caravan Route',
            'description' => 'Traditional trading route used for centuries.'
        ],
        
        [
            'panorama' => 'Northern Lights Display',
            'user' => 'traveler42',
            'yaw' => 0.0,
            'pitch' => 0.6,
            'type' => 'text',
            'color' => 'cyan',
            'label' => 'Aurora Peak',
            'description' => 'The brightest part of the aurora display. Colors shift between green and purple.'
        ],
        [
            'panorama' => 'Northern Lights Display',
            'user' => 'traveler42',
            'yaw' => 2.0,
            'pitch' => 0.3,
            'type' => 'text',
            'color' => 'purple',
            'label' => 'Corona Formation',
            'description' => 'Rare overhead aurora pattern visible on strong display nights.'
        ],
        
        [
            'panorama' => 'Office Tour - Main Lobby',
            'user' => 'admin',
            'yaw' => 1.2,
            'pitch' => 0.0,
            'type' => 'portal',
            'color' => 'blue',
            'label' => 'To Conference Room',
            'description' => 'Click to enter the main conference room.',
            'target_panorama' => 'Office Tour - Conference Room'
        ],
        [
            'panorama' => 'Office Tour - Main Lobby',
            'user' => 'admin',
            'yaw' => -0.5,
            'pitch' => 0.1,
            'type' => 'text',
            'color' => 'green',
            'label' => 'Reception Desk',
            'description' => 'Welcome! Please check in with our receptionist.'
        ],
        [
            'panorama' => 'Office Tour - Conference Room',
            'user' => 'admin',
            'yaw' => -2.0,
            'pitch' => 0.0,
            'type' => 'portal',
            'color' => 'blue',
            'label' => 'Back to Lobby',
            'description' => 'Return to the main lobby.',
            'target_panorama' => 'Office Tour - Main Lobby'
        ],
        [
            'panorama' => 'Office Tour - Conference Room',
            'user' => 'admin',
            'yaw' => 0.5,
            'pitch' => 0.2,
            'type' => 'text',
            'color' => 'cyan',
            'label' => 'Presentation Screen',
            'description' => '4K display for presentations and video conferences.'
        ],
        [
            'panorama' => 'Office Tour - Conference Room',
            'user' => 'admin',
            'yaw' => 2.5,
            'pitch' => 0.0,
            'type' => 'text',
            'color' => 'orange',
            'label' => 'City View',
            'description' => 'Floor-to-ceiling windows with panoramic city views.'
        ],
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO markers (panorama_id, user_id, yaw, pitch, type, color, label, description, target_panorama_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $markerCount = 0;
    foreach ($markers as $marker) {
        $panoramaId = $panoramaIds[$marker['panorama']] ?? null;
        $userId = $userIds[$marker['user']] ?? null;
        $targetPanoramaId = isset($marker['target_panorama']) ? ($panoramaIds[$marker['target_panorama']] ?? null) : null;
        
        if ($panoramaId && $userId) {
            $stmt->execute([
                $panoramaId,
                $userId,
                $marker['yaw'],
                $marker['pitch'],
                $marker['type'],
                $marker['color'],
                $marker['label'],
                $marker['description'],
                $targetPanoramaId
            ]);
            $markerCount++;
            $typeIcon = $marker['type'] === 'portal' ? '🚪' : '📌';
            $colorDot = "●";
            echo "   $typeIcon [{$marker['color']}] {$marker['label']} → {$marker['panorama']}\n";
        }
    }
    echo "   ✓ Created $markerCount markers\n\n";

    echo "👍 Creating votes...\n";
    
    $votes = [
        ['user' => 'jane_smith', 'panorama' => 'Mountain Sunrise Vista', 'value' => 1],
        ['user' => 'photo_lover', 'panorama' => 'Mountain Sunrise Vista', 'value' => 1],
        ['user' => 'traveler42', 'panorama' => 'Mountain Sunrise Vista', 'value' => 1],
        ['user' => 'admin', 'panorama' => 'Mountain Sunrise Vista', 'value' => 1],
        
        ['user' => 'john_doe', 'panorama' => 'Beach Paradise', 'value' => 1],
        ['user' => 'photo_lover', 'panorama' => 'Beach Paradise', 'value' => 1],
        ['user' => 'traveler42', 'panorama' => 'Beach Paradise', 'value' => 1],
        
        ['user' => 'john_doe', 'panorama' => 'Ancient Temple Interior', 'value' => 1],
        ['user' => 'traveler42', 'panorama' => 'Ancient Temple Interior', 'value' => 1],
        
        ['user' => 'john_doe', 'panorama' => 'Forest Trail in Autumn', 'value' => 1],
        ['user' => 'jane_smith', 'panorama' => 'Forest Trail in Autumn', 'value' => 1],
        
        ['user' => 'john_doe', 'panorama' => 'Desert Dunes at Sunset', 'value' => 1],
        ['user' => 'jane_smith', 'panorama' => 'Desert Dunes at Sunset', 'value' => -1],
        ['user' => 'photo_lover', 'panorama' => 'Desert Dunes at Sunset', 'value' => 1],
        
        ['user' => 'john_doe', 'panorama' => 'Northern Lights Display', 'value' => 1],
        ['user' => 'jane_smith', 'panorama' => 'Northern Lights Display', 'value' => 1],
        ['user' => 'photo_lover', 'panorama' => 'Northern Lights Display', 'value' => 1],
        ['user' => 'admin', 'panorama' => 'Northern Lights Display', 'value' => 1],
        
        ['user' => 'john_doe', 'panorama' => 'City Skyline at Night', 'value' => 1],
        ['user' => 'jane_smith', 'panorama' => 'City Skyline at Night', 'value' => 1],
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO votes (user_id, panorama_id, value) VALUES (?, ?, ?)"
    );

    $voteCount = 0;
    foreach ($votes as $vote) {
        $userId = $userIds[$vote['user']] ?? null;
        $panoramaId = $panoramaIds[$vote['panorama']] ?? null;
        
        if ($userId && $panoramaId) {
            $stmt->execute([$userId, $panoramaId, $vote['value']]);
            $voteCount++;
            $voteIcon = $vote['value'] > 0 ? '👍' : '👎';
            echo "   $voteIcon {$vote['user']} → {$vote['panorama']}\n";
        }
    }
    echo "   ✓ Created $voteCount votes\n\n";


    echo "===========================================\n";
    echo "  ✅ SEEDING COMPLETE!\n";
    echo "===========================================\n\n";
    
    echo "📊 Summary:\n";
    echo "   • Users:     " . count($users) . " (including " . count(array_filter($users, fn($u) => $u['role'] === 'admin')) . " admins)\n";
    echo "   • Panoramas: " . count($panoramas) . "\n";
    echo "   • Markers:   $markerCount\n";
    echo "   • Votes:     $voteCount\n\n";
    
    echo "🔐 Test Credentials:\n";
    echo "   ┌─────────────────────────────────────────────────────┐\n";
    echo "   │ Admin:  admin@viewer360.local     / admin123        │\n";
    echo "   │ User:   john@example.com          / password123     │\n";
    echo "   │ User:   jane@example.com          / password123     │\n";
    echo "   └─────────────────────────────────────────────────────┘\n\n";
    
    echo "📸 Sample images from Unsplash (free license):\n";
    echo "   • Mountain: Kalen Emsley\n";
    echo "   • City: Roberto Nickson\n";
    echo "   • Beach: Sean Oulashin\n";
    echo "   • Forest: Sebastian Unrau\n";
    echo "   • Desert: Keith Hardy\n";
    echo "   • Temple: Su San Lee\n";
    echo "   • Museum: Michael Dziedzic\n";
    echo "   • Aurora: Jonatan Pie\n";
    echo "   • Garden: Eddie Kopp\n";
    echo "   • Office: Nastuh Abootalebi & Proxyclick\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    if ($isCliMode) {
        exit(1);
    }
}

echo $isCliMode ? "\n" : '</pre>';
