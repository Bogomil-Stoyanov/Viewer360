<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\PanoramaController;
use App\Controllers\MarkerController;
use App\Config;

$panoramaController = new PanoramaController();
$markerController = new MarkerController();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$highlightMarkerId = isset($_GET['marker']) ? (int)$_GET['marker'] : null;

if ($id <= 0) {
    header('HTTP/1.0 404 Not Found');
    $error = 'Panorama not found.';
    $pageTitle = 'Not Found - Viewer360';
    include __DIR__ . '/../views/header.php';
    echo '<div class="container py-5 text-center"><h1>404</h1><p>' . htmlspecialchars($error) . '</p><a href="' . Config::url() . '" class="btn btn-primary">Go Home</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

$panorama = $panoramaController->getPanorama($id);

if (!$panorama) {
    header('HTTP/1.0 404 Not Found');
    $error = 'Panorama not found.';
    $pageTitle = 'Not Found - Viewer360';
    include __DIR__ . '/../views/header.php';
    echo '<div class="container py-5 text-center"><h1>404</h1><p>' . htmlspecialchars($error) . '</p><a href="' . Config::url() . '" class="btn btn-primary">Go Home</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

if (!$panoramaController->canView($panorama)) {
    header('HTTP/1.0 403 Forbidden');
    $error = 'You do not have permission to view this panorama.';
    $pageTitle = 'Access Denied - Viewer360';
    include __DIR__ . '/../views/header.php';
    echo '<div class="container py-5 text-center"><h1>403</h1><p>' . htmlspecialchars($error) . '</p><a href="' . Config::url('login.php') . '" class="btn btn-primary">Login</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($panorama['title']) . ' - Viewer360';
$currentPage = 'view';
$isOwner = AuthController::getCurrentUserId() === (int)$panorama['user_id'];
$isLoggedIn = AuthController::isLoggedIn();
$currentUserId = AuthController::getCurrentUserId();

$originalPanorama = $panoramaController->getOriginalPanorama($id);
$forkCount = $panoramaController->getForkCount($id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Main CSS -->
    <link href="<?= Config::url('assets/css/main.css') ?>" rel="stylesheet">
    <!-- Bootstrap Icons (icon font only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Photo Sphere Viewer CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core/index.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/markers-plugin/index.min.css">
    
    <!-- Viewer CSS -->
    <link rel="stylesheet" href="<?= Config::url('assets/css/viewer.css') ?>">
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
                <?php if ($forkCount > 0): ?>
                    <span class="ms-3"><i class="bi bi-diagram-2"></i> <?= $forkCount ?> remix<?= $forkCount > 1 ? 'es' : '' ?></span>
                <?php endif; ?>
                <?php if ($originalPanorama): ?>
                    <span class="ms-3 fork-badge">
                        <i class="bi bi-arrow-return-left"></i> Remixed from 
                        <a href="<?= Config::url('view.php?id=' . $originalPanorama['id']) ?>"><?= htmlspecialchars($originalPanorama['title']) ?></a>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($isOwner): ?>
                <!-- Export Data button for owners -->
                <button class="btn btn-viewer btn-sm export-btn" id="exportDataBtn" title="Download panorama data as JSON">
                    <i class="bi bi-download"></i> Export
                </button>
                
                <!-- Edit Mode Toggle for owners -->
                <div class="edit-mode-toggle">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="editModeToggle">
                        <label class="form-check-label text-white" for="editModeToggle">Edit Mode</label>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($isLoggedIn && !$isOwner && $panorama['is_public']): ?>
                <!-- Save to Collection button for non-owners on public panoramas -->
                <button class="btn btn-viewer btn-sm" id="saveToCollectionBtn">
                    <i class="bi bi-bookmark-plus"></i> Save to My Collection
                </button>
            <?php endif; ?>
            
            <?php if ($isLoggedIn): ?>
                <a href="<?= Config::url('dashboard.php') ?>" class="btn btn-viewer btn-sm">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            <?php else: ?>
                <a href="<?= Config::url() ?>" class="btn btn-viewer btn-sm">
                    <i class="bi bi-house"></i> Home
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Mode Indicator -->
    <div class="edit-mode-indicator" id="editModeIndicator">
        <i class="bi bi-pencil"></i> Click anywhere to add a marker
    </div>

    <!-- 360 Viewer Container -->
    <div id="viewer"></div>
    
    <!-- Voting Panel (visible for public panoramas) -->
    <?php if ($panorama['is_public']): ?>
    <div class="voting-panel" id="votingPanel">
        <button class="vote-btn upvote" id="upvoteBtn" title="Upvote" <?= !$isLoggedIn ? 'disabled' : '' ?>>
            <i class="bi bi-arrow-up-circle-fill"></i>
        </button>
        <span class="vote-score" id="voteScore">0</span>
        <button class="vote-btn downvote" id="downvoteBtn" title="Downvote" <?= !$isLoggedIn ? 'disabled' : '' ?>>
            <i class="bi bi-arrow-down-circle-fill"></i>
        </button>
        <?php if (!$isLoggedIn): ?>
            <a href="<?= Config::url('login.php') ?>" class="btn btn-sm btn-outline-light ms-2">Login to Vote</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Marker Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" title="Show markers list">
        <i class="bi bi-list-ul"></i>
    </button>
    
    <!-- Marker Sidebar -->
    <div class="marker-sidebar" id="markerSidebar">
        <div class="sidebar-header">
            <h6><i class="bi bi-pin-map"></i> Markers</h6>
            <span class="badge bg-primary" id="markerCount">0</span>
        </div>
        <div class="sidebar-body" id="markerList">
            <div class="empty-markers">
                <i class="bi bi-pin-map" style="font-size: 2rem; opacity: 0.3;"></i>
                <p class="mt-2 mb-0">No markers yet</p>
            </div>
        </div>
    </div>

    <!-- Viewer Footer (Description) -->
    <?php if (!empty($panorama['description'])): ?>
    <div class="viewer-overlay viewer-footer">
        <p class="text-white mb-0">
            <i class="bi bi-info-circle"></i> <?= htmlspecialchars($panorama['description']) ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php include __DIR__ . '/../views/viewer/modals.php'; ?>
    
    <script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }
    
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });
    
    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').classList.remove('show');
        });
    });
    
    window.modalUtils = {
        open: openModal,
        close: closeModal
    };
    </script>

    <!-- Photo Sphere Viewer JS -->
    <script type="importmap">
    {
        "imports": {
            "@photo-sphere-viewer/core": "https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core/index.module.min.js",
            "@photo-sphere-viewer/markers-plugin": "https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/markers-plugin/index.module.min.js",
            "three": "https://cdn.jsdelivr.net/npm/three/build/three.module.min.js"
        }
    }
    </script>
    
    <script type="module">
        import { initViewer } from '/assets/js/viewer.js';
        
        window.viewerModule = initViewer({
            panoramaId: <?= $id ?>,
            panoramaPath: '<?= htmlspecialchars($panorama['file_path']) ?>',
            panoramaTitle: '<?= htmlspecialchars(addslashes($panorama['title'])) ?>',
            isOwner: <?= $isOwner ? 'true' : 'false' ?>,
            isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
            highlightMarkerId: <?= $highlightMarkerId ? $highlightMarkerId : 'null' ?>,
            currentUserId: <?= $currentUserId ?? 'null' ?>,
            currentUsername: '<?= htmlspecialchars(addslashes(AuthController::getCurrentUsername() ?? '')) ?>'
        });
    </script>
</body>
</html>
