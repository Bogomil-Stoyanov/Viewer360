<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\PanoramaController;

$panoramaController = new PanoramaController();

// Get panorama ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('HTTP/1.0 404 Not Found');
    $error = 'Panorama not found.';
    $pageTitle = 'Not Found - Viewer360';
    include __DIR__ . '/../views/header.php';
    echo '<div class="container py-5 text-center"><h1>404</h1><p>' . htmlspecialchars($error) . '</p><a href="/" class="btn btn-primary">Go Home</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

// Fetch panorama
$panorama = $panoramaController->getPanorama($id);

if (!$panorama) {
    header('HTTP/1.0 404 Not Found');
    $error = 'Panorama not found.';
    $pageTitle = 'Not Found - Viewer360';
    include __DIR__ . '/../views/header.php';
    echo '<div class="container py-5 text-center"><h1>404</h1><p>' . htmlspecialchars($error) . '</p><a href="/" class="btn btn-primary">Go Home</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

// Check if user can view this panorama
if (!$panoramaController->canView($panorama)) {
    header('HTTP/1.0 403 Forbidden');
    $error = 'You do not have permission to view this panorama.';
    $pageTitle = 'Access Denied - Viewer360';
    include __DIR__ . '/../views/header.php';
    echo '<div class="container py-5 text-center"><h1>403</h1><p>' . htmlspecialchars($error) . '</p><a href="/login.php" class="btn btn-primary">Login</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($panorama['title']) . ' - Viewer360';
$currentPage = 'view';
$isOwner = AuthController::getCurrentUserId() === (int)$panorama['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Photo Sphere Viewer CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core/index.min.css">
    
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        
        #viewer {
            width: 100%;
            height: 100vh;
        }
        
        .viewer-overlay {
            position: absolute;
            z-index: 1000;
        }
        
        .viewer-header {
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);
            padding: 15px 20px;
        }
        
        .viewer-footer {
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            padding: 15px 20px;
        }
        
        .viewer-title {
            color: white;
            margin: 0;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        .viewer-meta {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }
        
        .btn-viewer {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(5px);
        }
        
        .btn-viewer:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #1a1a2e;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            transition: opacity 0.5s ease;
        }
        
        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255,255,255,0.1);
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner mb-3"></div>
        <p class="text-white">Loading panorama...</p>
    </div>

    <!-- Viewer Header -->
    <div class="viewer-overlay viewer-header d-flex justify-content-between align-items-start">
        <div>
            <h4 class="viewer-title"><?= htmlspecialchars($panorama['title']) ?></h4>
            <div class="viewer-meta">
                <span><i class="bi bi-person"></i> <?= htmlspecialchars($panorama['username']) ?></span>
                <span class="ms-3"><i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($panorama['created_at'])) ?></span>
                <?php if ($panorama['is_public']): ?>
                    <span class="ms-3"><i class="bi bi-globe"></i> Public</span>
                <?php else: ?>
                    <span class="ms-3"><i class="bi bi-lock"></i> Private</span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <?php if (AuthController::isLoggedIn()): ?>
                <a href="/dashboard.php" class="btn btn-viewer btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            <?php else: ?>
                <a href="/" class="btn btn-viewer btn-sm">
                    <i class="bi bi-house"></i> Home
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 360 Viewer Container -->
    <div id="viewer"></div>

    <!-- Viewer Footer (Description) -->
    <?php if (!empty($panorama['description'])): ?>
    <div class="viewer-overlay viewer-footer">
        <p class="text-white mb-0">
            <i class="bi bi-info-circle"></i> <?= htmlspecialchars($panorama['description']) ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Photo Sphere Viewer JS -->
    <script type="importmap">
    {
        "imports": {
            "@photo-sphere-viewer/core": "https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core/index.module.min.js",
            "three": "https://cdn.jsdelivr.net/npm/three/build/three.module.min.js"
        }
    }
    </script>
    
    <script type="module">
        import { Viewer } from '@photo-sphere-viewer/core';
        
        const viewer = new Viewer({
            container: document.querySelector('#viewer'),
            panorama: '/<?= htmlspecialchars($panorama['file_path']) ?>',
            caption: '<?= htmlspecialchars(addslashes($panorama['title'])) ?>',
            loadingTxt: 'Loading...',
            defaultYaw: 0,
            defaultPitch: 0,
            navbar: [
                'autorotate',
                'zoom',
                'move',
                'fullscreen'
            ],
            plugins: []
        });
        
        // Hide loading overlay when panorama is ready
        viewer.addEventListener('ready', () => {
            document.getElementById('loadingOverlay').classList.add('hidden');
        });
        
        // Auto-rotate
        viewer.addEventListener('ready', () => {
            // Optionally start auto-rotation
            // viewer.startAutoRotate();
        });
    </script>
</body>
</html>
