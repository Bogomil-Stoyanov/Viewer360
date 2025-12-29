<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\PanoramaController;
use App\Controllers\MarkerController;

$panoramaController = new PanoramaController();
$markerController = new MarkerController();

// Get panorama ID and optional marker ID for deep linking
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$highlightMarkerId = isset($_GET['marker']) ? (int)$_GET['marker'] : null;

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
$isLoggedIn = AuthController::isLoggedIn();
$currentUserId = AuthController::getCurrentUserId();

// Get original panorama info if this is a fork
$originalPanorama = $panoramaController->getOriginalPanorama($id);
$forkCount = $panoramaController->getForkCount($id);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/markers-plugin/index.min.css">
    
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
            bottom: 40px;
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
        
        /* Edit Mode Toggle */
        .edit-mode-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        
        .edit-mode-toggle .form-check-input {
            width: 3em;
            height: 1.5em;
            cursor: pointer;
            margin-right: 0.5em;
        }
        
        .edit-mode-toggle .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
        
        .edit-mode-indicator {
            display: none;
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(25, 135, 84, 0.9);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            z-index: 1001;
            backdrop-filter: blur(5px);
        }
        
        .edit-mode-indicator.active {
            display: block;
        }
        
        /* Marker Modal */
        .modal-backdrop {
            z-index: 1050;
        }
        
        .marker-modal {
            z-index: 1060;
        }
        
        /* Custom marker styles */
        .custom-marker {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: 3px solid white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .custom-marker:hover {
            transform: scale(1.2);
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        
        .custom-marker.highlighted {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            animation: pulse 1s ease-in-out 3;
        }
        
        /* Audio marker styles */
        .custom-marker.has-audio {
            width: 28px;
            height: 28px;
            border-width: 3px;
        }
        
        .custom-marker.has-audio .audio-icon {
            color: white;
            font-size: 12px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .custom-marker.has-audio.playing {
            animation: audio-pulse 0.5s ease-in-out infinite alternate;
            box-shadow: 0 0 15px rgba(13, 110, 253, 0.6);
        }
        
        /* Portal marker styles */
        .custom-marker.is-portal {
            width: 32px;
            height: 32px;
            border-width: 3px;
            border-style: dashed;
        }
        
        .custom-marker.is-portal .portal-icon {
            color: white;
            font-size: 14px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .custom-marker.is-portal:hover {
            transform: scale(1.25);
            box-shadow: 0 0 20px rgba(25, 135, 84, 0.6);
        }

        @keyframes audio-pulse {
            from { transform: scale(1); }
            to { transform: scale(1.15); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }
        
        /* Marker tooltip */
        .marker-tooltip {
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            max-width: 250px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .marker-tooltip h6 {
            margin: 0 0 5px 0;
            font-weight: 600;
        }
        
        .marker-tooltip p {
            margin: 0;
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .marker-tooltip .marker-meta {
            margin-top: 8px;
            font-size: 0.8em;
            opacity: 0.7;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .marker-tooltip .copy-link-btn {
            padding: 2px 6px;
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .marker-tooltip .copy-link-btn:hover {
            opacity: 1;
        }
        
        /* Fork/Remix info */
        .fork-badge {
            background: rgba(111, 66, 193, 0.9);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .fork-badge a {
            color: white;
            text-decoration: underline;
        }
        
        /* Color picker */
        .color-picker {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .color-option {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: transform 0.2s, border-color 0.2s;
        }
        
        .color-option:hover {
            transform: scale(1.1);
        }
        
        .color-option.selected {
            border-color: #333;
            box-shadow: 0 0 0 2px white;
        }
        
        .color-option[data-color="blue"] { background: #0d6efd; }
        .color-option[data-color="red"] { background: #dc3545; }
        .color-option[data-color="green"] { background: #198754; }
        .color-option[data-color="yellow"] { background: #ffc107; }
        .color-option[data-color="orange"] { background: #fd7e14; }
        .color-option[data-color="purple"] { background: #6f42c1; }
        .color-option[data-color="pink"] { background: #d63384; }
        .color-option[data-color="cyan"] { background: #0dcaf0; }
        .color-option[data-color="white"] { background: #ffffff; border: 1px solid #ccc; }
        
        /* Voting UI */
        .voting-panel {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1001;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .vote-btn {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.7);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .vote-btn:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .vote-btn.active.upvote {
            color: #198754;
        }
        
        .vote-btn.active.downvote {
            color: #dc3545;
        }
        
        .vote-score {
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            min-width: 40px;
            text-align: center;
        }
        
        .vote-score.positive { color: #198754; }
        .vote-score.negative { color: #dc3545; }
        
        /* Marker Sidebar */
        .marker-sidebar {
            position: fixed;
            top: 80px;
            right: -320px;
            width: 300px;
            max-height: calc(100vh - 100px);
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            border-radius: 12px 0 0 12px;
            z-index: 1001;
            transition: right 0.3s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .marker-sidebar.open {
            right: 0;
        }
        
        .sidebar-toggle {
            position: fixed;
            top: 80px;
            right: 0;
            z-index: 1002;
            background: rgba(0,0,0,0.85);
            border: none;
            color: white;
            padding: 12px 15px;
            border-radius: 12px 0 0 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .sidebar-toggle:hover {
            background: rgba(0,0,0,0.95);
        }
        
        .sidebar-toggle.open {
            right: 300px;
        }
        
        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-header h6 {
            color: white;
            margin: 0;
        }
        
        .sidebar-body {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .marker-list-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            margin-bottom: 5px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: white;
            text-decoration: none;
        }
        
        .marker-list-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .marker-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .marker-list-item .label {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9rem;
        }
        
        .marker-list-item .arrow {
            opacity: 0.5;
            margin-left: 8px;
        }
        
        .empty-markers {
            text-align: center;
            padding: 30px 15px;
            color: rgba(255,255,255,0.5);
        }
        
        /* Export button */
        .export-btn {
            padding: 4px 12px;
            font-size: 0.85rem;
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
                <?php if ($forkCount > 0): ?>
                    <span class="ms-3"><i class="bi bi-diagram-2"></i> <?= $forkCount ?> remix<?= $forkCount > 1 ? 'es' : '' ?></span>
                <?php endif; ?>
                <?php if ($originalPanorama): ?>
                    <span class="ms-3 fork-badge">
                        <i class="bi bi-arrow-return-left"></i> Remixed from 
                        <a href="/view.php?id=<?= $originalPanorama['id'] ?>"><?= htmlspecialchars($originalPanorama['title']) ?></a>
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
                <a href="/dashboard.php" class="btn btn-viewer btn-sm">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            <?php else: ?>
                <a href="/" class="btn btn-viewer btn-sm">
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
            <a href="/login.php" class="btn btn-sm btn-outline-light ms-2">Login to Vote</a>
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
    
    <!-- Add Marker Modal -->
    <div class="modal fade marker-modal" id="addMarkerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pin-map"></i> Add Marker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addMarkerForm">
                        <input type="hidden" id="markerYaw" name="yaw">
                        <input type="hidden" id="markerPitch" name="pitch">
                        <input type="hidden" id="markerColor" name="color" value="blue">
                        <div class="mb-3">
                            <label for="markerLabel" class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="markerLabel" name="label" required maxlength="200" placeholder="Enter a title for this marker">
                        </div>
                        <div class="mb-3">
                            <label for="markerDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="markerDescription" name="description" rows="3" placeholder="Add more details about this location..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pin Color</label>
                            <div class="color-picker" id="addColorPicker">
                                <div class="color-option selected" data-color="blue" title="Blue"></div>
                                <div class="color-option" data-color="red" title="Red"></div>
                                <div class="color-option" data-color="green" title="Green"></div>
                                <div class="color-option" data-color="yellow" title="Yellow"></div>
                                <div class="color-option" data-color="orange" title="Orange"></div>
                                <div class="color-option" data-color="purple" title="Purple"></div>
                                <div class="color-option" data-color="pink" title="Pink"></div>
                                <div class="color-option" data-color="cyan" title="Cyan"></div>
                                <div class="color-option" data-color="white" title="White"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="markerAudio" class="form-label">
                                <i class="bi bi-volume-up"></i> Attach Audio (Optional)
                            </label>
                            <input type="file" class="form-control" id="markerAudio" name="audio_file" 
                                   accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg">
                            <div class="form-text">MP3, WAV, or OGG. Max 15MB.</div>
                        </div>
                        <div class="mb-3">
                            <label for="markerTargetPanorama" class="form-label">
                                <i class="bi bi-box-arrow-up-right"></i> Link to Scene (Portal Marker)
                            </label>
                            <select class="form-select" id="markerTargetPanorama" name="target_panorama_id">
                                <option value="">No link (regular marker)</option>
                            </select>
                            <div class="form-text">Create a portal that navigates to another panorama when clicked.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveMarkerBtn">
                        <i class="bi bi-check-lg"></i> Save Marker
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Marker Modal -->
    <div class="modal fade marker-modal" id="editMarkerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Marker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editMarkerForm">
                        <input type="hidden" id="editMarkerId" name="id">
                        <input type="hidden" id="editMarkerColor" name="color" value="blue">
                        <div class="mb-3">
                            <label for="editMarkerLabel" class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editMarkerLabel" name="label" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label for="editMarkerDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editMarkerDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pin Color</label>
                            <div class="color-picker" id="editColorPicker">
                                <div class="color-option" data-color="blue" title="Blue"></div>
                                <div class="color-option" data-color="red" title="Red"></div>
                                <div class="color-option" data-color="green" title="Green"></div>
                                <div class="color-option" data-color="yellow" title="Yellow"></div>
                                <div class="color-option" data-color="orange" title="Orange"></div>
                                <div class="color-option" data-color="purple" title="Purple"></div>
                                <div class="color-option" data-color="pink" title="Pink"></div>
                                <div class="color-option" data-color="cyan" title="Cyan"></div>
                                <div class="color-option" data-color="white" title="White"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-volume-up"></i> Audio Attachment
                            </label>
                            <div id="editCurrentAudio" class="mb-2 d-none">
                                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                                    <i class="bi bi-music-note-beamed text-primary"></i>
                                    <span class="flex-grow-1 text-truncate" id="editAudioFilename">audio.mp3</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="previewAudioBtn" title="Preview">
                                        <i class="bi bi-play-fill"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeAudioBtn" title="Remove audio">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="editRemoveAudio" name="remove_audio" value="0">
                            <input type="file" class="form-control" id="editMarkerAudio" name="audio_file" 
                                   accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg">
                            <div class="form-text">MP3, WAV, or OGG. Max 15MB. Upload to replace existing.</div>
                        </div>
                        <div class="mb-3">
                            <label for="editMarkerTargetPanorama" class="form-label">
                                <i class="bi bi-box-arrow-up-right"></i> Link to Scene (Portal Marker)
                            </label>
                            <select class="form-select" id="editMarkerTargetPanorama" name="target_panorama_id">
                                <option value="">No link (regular marker)</option>
                            </select>
                            <div class="form-text">Create a portal that navigates to another panorama when clicked.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-danger" id="deleteMarkerBtn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="updateMarkerBtn">
                            <i class="bi bi-check-lg"></i> Update
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
        import { Viewer } from '@photo-sphere-viewer/core';
        import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
        
        // Configuration
        const panoramaId = <?= $id ?>;
        const isOwner = <?= $isOwner ? 'true' : 'false' ?>;
        const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const highlightMarkerId = <?= $highlightMarkerId ? $highlightMarkerId : 'null' ?>;
        
        // State
        let editMode = false;
        let markersData = [];
        let currentAudio = null;  // Currently playing audio
        let currentPlayingMarkerId = null;  // ID of marker whose audio is playing
        let userPanoramas = [];  // User's panoramas for portal linking
        
        // Color mapping
        const colorMap = {
            'blue': '#0d6efd',
            'red': '#dc3545',
            'green': '#198754',
            'yellow': '#ffc107',
            'orange': '#fd7e14',
            'purple': '#6f42c1',
            'pink': '#d63384',
            'cyan': '#0dcaf0',
            'white': '#ffffff'
        };
        
        // Initialize viewer with markers plugin
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
            plugins: [
                [MarkersPlugin, {
                    markers: []
                }]
            ]
        });
        
        const markersPlugin = viewer.getPlugin(MarkersPlugin);
        
        // ========== MARKER FUNCTIONS ==========
        
        // Get CSS gradient for marker color
        function getMarkerGradient(color, isHighlighted = false) {
            if (isHighlighted) {
                return 'linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)';
            }
            const hex = colorMap[color] || colorMap['blue'];
            // Darken the color slightly for gradient end
            return `linear-gradient(135deg, ${hex} 0%, ${hex}dd 100%)`;
        }
        
        // Create marker element for Photo Sphere Viewer
        function createMarkerConfig(marker, isHighlighted = false) {
            const shareUrl = `${window.location.origin}/view.php?id=${panoramaId}&marker=${marker.id}`;
            const markerColor = marker.color || 'blue';
            const hasAudio = marker.audio_path && marker.audio_path.length > 0;
            const isPortal = marker.target_panorama_id && marker.target_panorama_id > 0;
            const audioIndicator = hasAudio ? '<i class="bi bi-volume-up"></i> ' : '';
            const portalIndicator = isPortal ? '<i class="bi bi-box-arrow-up-right"></i> ' : '';
            
            // Get target panorama info for portal markers
            let portalInfo = '';
            if (isPortal) {
                const targetPano = userPanoramas.find(p => parseInt(p.id) === parseInt(marker.target_panorama_id));
                if (targetPano) {
                    portalInfo = `<p class="text-success small mb-1"><i class="bi bi-signpost-2"></i> Leads to: ${escapeHtml(targetPano.title)}</p>`;
                } else {
                    portalInfo = `<p class="text-success small mb-1"><i class="bi bi-signpost-2"></i> Portal to another scene</p>`;
                }
            }
            
            const tooltipContent = `
                <div class="marker-tooltip">
                    <h6>${portalIndicator}${audioIndicator}${escapeHtml(marker.label)}</h6>
                    ${marker.description ? `<p>${escapeHtml(marker.description)}</p>` : ''}
                    ${portalInfo}
                    ${hasAudio && !isPortal ? '<p class="text-info small mb-1"><i class="bi bi-music-note"></i> Click marker to play/pause audio</p>' : ''}
                    ${isPortal ? '<p class="text-warning small mb-1"><i class="bi bi-arrow-right-circle"></i> Click marker to navigate</p>' : ''}
                    <div class="marker-meta">
                        <i class="bi bi-person"></i> ${escapeHtml(marker.username || 'Unknown')}
                        <button class="btn btn-sm btn-outline-light ms-2 copy-link-btn" 
                                onclick="copyMarkerLink(${marker.id}); event.stopPropagation();" 
                                title="Copy link to this marker">
                            <i class="bi bi-link-45deg"></i>
                        </button>
                    </div>
                </div>
            `;
            
            const markerGradient = getMarkerGradient(markerColor, isHighlighted);
            const borderColor = markerColor === 'white' ? '#ccc' : 'white';
            const audioClass = hasAudio ? 'has-audio' : '';
            const portalClass = isPortal ? 'is-portal' : '';
            const audioIcon = hasAudio && !isPortal ? '<i class="bi bi-volume-up audio-icon"></i>' : '';
            const portalIcon = isPortal ? '<i class="bi bi-arrow-right-circle-fill portal-icon"></i>' : '';
            
            return {
                id: `marker-${marker.id}`,
                position: { yaw: parseFloat(marker.yaw), pitch: parseFloat(marker.pitch) },
                html: `<div class="custom-marker ${audioClass} ${portalClass} ${isHighlighted ? 'highlighted' : ''}" 
                            data-marker-id="${marker.id}" 
                            data-has-audio="${hasAudio}"
                            data-audio-path="${hasAudio ? marker.audio_path : ''}"
                            data-is-portal="${isPortal}"
                            data-target-panorama="${isPortal ? marker.target_panorama_id : ''}"
                            style="background: ${markerGradient}; border-color: ${borderColor};">${audioIcon}${portalIcon}</div>`,
                anchor: 'center center',
                tooltip: {
                    content: tooltipContent,
                    position: 'top center',
                    trigger: 'click'
                },
                data: marker
            };
        }
        
        // Copy marker deep link to clipboard
        window.copyMarkerLink = function(markerId) {
            const url = `${window.location.origin}/view.php?id=${panoramaId}&marker=${markerId}`;
            navigator.clipboard.writeText(url).then(() => {
                // Show a brief success message
                const btn = document.querySelector(`.copy-link-btn`);
                if (btn) {
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check"></i>';
                    setTimeout(() => { btn.innerHTML = originalHTML; }, 1500);
                }
            }).catch(() => {
                // Fallback for older browsers
                prompt('Copy this link:', url);
            });
        };
        
        // Load markers from API
        async function loadMarkers() {
            try {
                const response = await fetch(`/api.php?action=marker/list&panorama_id=${panoramaId}`);
                const data = await response.json();
                
                if (data.success) {
                    markersData = data.markers;
                    renderMarkers();
                }
            } catch (error) {
                console.error('Failed to load markers:', error);
            }
        }
        
        // Load user's panoramas for portal linking dropdown
        async function loadUserPanoramas() {
            if (!isOwner) return;  // Only load for owner
            
            try {
                const response = await fetch(`/api.php?action=panorama/user-list&exclude_id=${panoramaId}`);
                const data = await response.json();
                
                if (data.success) {
                    userPanoramas = data.panoramas;
                    populatePortalDropdowns();
                }
            } catch (error) {
                console.error('Failed to load user panoramas:', error);
            }
        }
        
        // Populate portal dropdown menus with user's panoramas
        function populatePortalDropdowns() {
            const addSelect = document.getElementById('markerTargetPanorama');
            const editSelect = document.getElementById('editMarkerTargetPanorama');
            
            const options = userPanoramas.map(p => 
                `<option value="${p.id}">${escapeHtml(p.title)}</option>`
            ).join('');
            
            if (addSelect) {
                addSelect.innerHTML = '<option value="">No link (regular marker)</option>' + options;
            }
            if (editSelect) {
                editSelect.innerHTML = '<option value="">No link (regular marker)</option>' + options;
            }
        }
        
        // Render markers on the viewer
        function renderMarkers() {
            // Clear existing markers
            markersPlugin.clearMarkers();
            
            // Add each marker
            markersData.forEach(marker => {
                const isHighlighted = highlightMarkerId && parseInt(marker.id) === highlightMarkerId;
                markersPlugin.addMarker(createMarkerConfig(marker, isHighlighted));
            });
            
            // Update sidebar
            updateMarkerSidebar();
        }
        
        // Update the marker sidebar list
        function updateMarkerSidebar() {
            const listContainer = document.getElementById('markerList');
            const countBadge = document.getElementById('markerCount');
            
            countBadge.textContent = markersData.length;
            
            if (markersData.length === 0) {
                listContainer.innerHTML = `
                    <div class="empty-markers">
                        <i class="bi bi-pin-map" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">No markers yet</p>
                    </div>
                `;
                return;
            }
            
            listContainer.innerHTML = markersData.map(marker => {
                const markerColor = marker.color || 'blue';
                const colorHex = colorMap[markerColor] || colorMap['blue'];
                const hasAudio = marker.audio_path && marker.audio_path.length > 0;
                const isPortal = marker.target_panorama_id && marker.target_panorama_id > 0;
                const audioIcon = hasAudio ? '<i class="bi bi-volume-up text-info me-1" title="Has audio"></i>' : '';
                const portalIcon = isPortal ? '<i class="bi bi-box-arrow-up-right text-success me-1" title="Portal to another scene"></i>' : '';
                return `
                    <div class="marker-list-item ${isPortal ? 'is-portal' : ''}" data-marker-id="${marker.id}" onclick="navigateToMarker(${marker.id})">
                        <div class="marker-color-dot" style="background: ${colorHex};"></div>
                        ${portalIcon}${audioIcon}
                        <span class="label">${escapeHtml(marker.label)}</span>
                        <i class="bi bi-chevron-right arrow"></i>
                    </div>
                `;
            }).join('');
        }
        
        // Navigate/animate to a marker (from sidebar click)
        window.navigateToMarker = function(markerId) {
            animateToMarker(markerId);
            // Close sidebar on mobile
            if (window.innerWidth < 768) {
                document.getElementById('markerSidebar').classList.remove('open');
                document.getElementById('sidebarToggle').classList.remove('open');
            }
        };
        
        // Create a new marker
        async function createMarker(yaw, pitch, label, description, color, audioFile = null, targetPanoramaId = null) {
            try {
                // Use FormData for multipart/form-data (required for file uploads)
                const formData = new FormData();
                formData.append('panorama_id', panoramaId);
                formData.append('yaw', yaw);
                formData.append('pitch', pitch);
                formData.append('label', label);
                formData.append('description', description);
                formData.append('color', color);
                formData.append('type', targetPanoramaId ? 'portal' : 'text');
                
                if (audioFile) {
                    formData.append('audio_file', audioFile);
                }
                
                if (targetPanoramaId) {
                    formData.append('target_panorama_id', targetPanoramaId);
                }
                
                const response = await fetch('/api.php?action=marker/create', {
                    method: 'POST',
                    body: formData  // No Content-Type header - browser sets it with boundary
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Add to local data and render
                    data.marker.username = '<?= htmlspecialchars(addslashes(AuthController::getCurrentUsername() ?? '')) ?>';
                    markersData.push(data.marker);
                    markersPlugin.addMarker(createMarkerConfig(data.marker));
                    updateMarkerSidebar();
                    return true;
                } else {
                    alert(data.error || 'Failed to create marker');
                    return false;
                }
            } catch (error) {
                console.error('Failed to create marker:', error);
                alert('Failed to create marker. Please try again.');
                return false;
            }
        }
        
        // Update a marker
        async function updateMarker(id, label, description, color, audioFile = null, removeAudio = false, targetPanoramaId = null) {
            try {
                // Use FormData for multipart/form-data (required for file uploads)
                const formData = new FormData();
                formData.append('id', id);
                formData.append('label', label);
                formData.append('description', description);
                formData.append('color', color);
                formData.append('type', targetPanoramaId ? 'portal' : 'text');
                formData.append('remove_audio', removeAudio ? '1' : '0');
                
                if (audioFile) {
                    formData.append('audio_file', audioFile);
                }
                
                if (targetPanoramaId) {
                    formData.append('target_panorama_id', targetPanoramaId);
                }
                
                const response = await fetch('/api.php?action=marker/update', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update local data
                    const markerIndex = markersData.findIndex(m => parseInt(m.id) === id);
                    if (markerIndex !== -1) {
                        markersData[markerIndex].label = label;
                        markersData[markerIndex].description = description;
                        markersData[markerIndex].color = color;
                        markersData[markerIndex].audio_path = data.marker.audio_path;
                        markersData[markerIndex].target_panorama_id = data.marker.target_panorama_id;
                        markersData[markerIndex].type = data.marker.type;
                        
                        // Update the marker in the viewer
                        markersPlugin.updateMarker(createMarkerConfig(markersData[markerIndex]));
                        updateMarkerSidebar();
                    }
                    return true;
                } else {
                    alert(data.error || 'Failed to update marker');
                    return false;
                }
            } catch (error) {
                console.error('Failed to update marker:', error);
                alert('Failed to update marker. Please try again.');
                return false;
            }
        }
        
        // Delete a marker
        async function deleteMarker(id) {
            try {
                const response = await fetch('/api.php?action=marker/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove from local data
                    markersData = markersData.filter(m => parseInt(m.id) !== id);
                    markersPlugin.removeMarker(`marker-${id}`);
                    return true;
                } else {
                    alert(data.error || 'Failed to delete marker');
                    return false;
                }
            } catch (error) {
                console.error('Failed to delete marker:', error);
                alert('Failed to delete marker. Please try again.');
                return false;
            }
        }
        
        // ========== DEEP LINKING ==========
        
        function animateToMarker(markerId) {
            const marker = markersData.find(m => parseInt(m.id) === markerId);
            if (marker) {
                // Animate camera to the marker position
                viewer.animate({
                    yaw: parseFloat(marker.yaw),
                    pitch: parseFloat(marker.pitch),
                    zoom: 50,
                    speed: '2rpm'
                }).then(() => {
                    // Highlight effect is already applied via the highlighted class
                    console.log('Animated to marker:', markerId);
                });
            }
        }
        
        // ========== FORK/SAVE TO COLLECTION ==========
        
        async function forkPanorama() {
            try {
                const response = await fetch('/api.php?action=panorama/fork', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ panorama_id: panoramaId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    // Redirect to the new panorama
                    window.location.href = `/view.php?id=${data.panorama_id}`;
                } else {
                    alert(data.error || 'Failed to save to collection');
                }
            } catch (error) {
                console.error('Failed to fork panorama:', error);
                alert('Failed to save to collection. Please try again.');
            }
        }
        
        // ========== HELPER FUNCTIONS ==========
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // ========== AUDIO PLAYBACK FUNCTIONS ==========
        
        // Stop currently playing audio
        function stopCurrentAudio() {
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.currentTime = 0;
                currentAudio = null;
                
                // Remove playing class from marker
                if (currentPlayingMarkerId) {
                    const markerEl = document.querySelector(`[data-marker-id="${currentPlayingMarkerId}"]`);
                    if (markerEl) {
                        markerEl.classList.remove('playing');
                    }
                }
                currentPlayingMarkerId = null;
            }
        }
        
        // Play or toggle audio for a marker
        function toggleMarkerAudio(markerId, audioPath) {
            // If same marker is playing, stop it
            if (currentPlayingMarkerId === markerId && currentAudio) {
                stopCurrentAudio();
                return;
            }
            
            // Stop any currently playing audio first
            stopCurrentAudio();
            
            // Create and play new audio
            currentAudio = new Audio('/' + audioPath);
            currentPlayingMarkerId = markerId;
            
            // Add playing class to marker
            const markerEl = document.querySelector(`[data-marker-id="${markerId}"]`);
            if (markerEl) {
                markerEl.classList.add('playing');
            }
            
            // Handle audio end
            currentAudio.addEventListener('ended', () => {
                if (markerEl) {
                    markerEl.classList.remove('playing');
                }
                currentPlayingMarkerId = null;
                currentAudio = null;
            });
            
            // Handle audio error
            currentAudio.addEventListener('error', () => {
                console.error('Failed to load audio:', audioPath);
                stopCurrentAudio();
            });
            
            currentAudio.play().catch(err => {
                console.error('Failed to play audio:', err);
                stopCurrentAudio();
            });
        }
        
        // ========== EVENT HANDLERS ==========
        
        // Hide loading overlay and initialize when ready
        viewer.addEventListener('ready', () => {
            document.getElementById('loadingOverlay').classList.add('hidden');
            
            // Load user's panoramas for portal linking (before loading markers)
            loadUserPanoramas().then(() => {
                // Load markers
                loadMarkers().then(() => {
                    // Handle deep linking - animate to marker if specified
                    if (highlightMarkerId) {
                        setTimeout(() => animateToMarker(highlightMarkerId), 500);
                    }
                });
            });
        });
        
        // Edit mode toggle
        const editModeToggle = document.getElementById('editModeToggle');
        const editModeIndicator = document.getElementById('editModeIndicator');
        
        if (editModeToggle) {
            editModeToggle.addEventListener('change', (e) => {
                editMode = e.target.checked;
                editModeIndicator.classList.toggle('active', editMode);
            });
        }
        
        // Click on sphere to add marker (when in edit mode)
        viewer.addEventListener('click', (e) => {
            if (!editMode || !isOwner) return;
            
            // Get position where user clicked
            const position = e.data.rightclick ? null : e.data;
            if (!position || !position.yaw || !position.pitch) return;
            
            // Open add marker modal - reset color picker
            document.getElementById('markerYaw').value = position.yaw;
            document.getElementById('markerPitch').value = position.pitch;
            document.getElementById('markerLabel').value = '';
            document.getElementById('markerDescription').value = '';
            document.getElementById('markerColor').value = 'blue';
            document.getElementById('markerAudio').value = '';
            document.getElementById('markerTargetPanorama').value = '';
            
            // Reset color picker selection
            document.querySelectorAll('#addColorPicker .color-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.color === 'blue');
            });
            
            const modal = new bootstrap.Modal(document.getElementById('addMarkerModal'));
            modal.show();
        });
        
        // Color picker click handlers
        document.querySelectorAll('#addColorPicker .color-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('#addColorPicker .color-option').forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
                document.getElementById('markerColor').value = option.dataset.color;
            });
        });
        
        document.querySelectorAll('#editColorPicker .color-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('#editColorPicker .color-option').forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
                document.getElementById('editMarkerColor').value = option.dataset.color;
            });
        });
        
        // Save marker button
        document.getElementById('saveMarkerBtn').addEventListener('click', async () => {
            const yaw = parseFloat(document.getElementById('markerYaw').value);
            const pitch = parseFloat(document.getElementById('markerPitch').value);
            const label = document.getElementById('markerLabel').value.trim();
            const description = document.getElementById('markerDescription').value.trim();
            const color = document.getElementById('markerColor').value;
            const audioInput = document.getElementById('markerAudio');
            const audioFile = audioInput.files.length > 0 ? audioInput.files[0] : null;
            const targetPanoramaSelect = document.getElementById('markerTargetPanorama');
            const targetPanoramaId = targetPanoramaSelect.value ? parseInt(targetPanoramaSelect.value) : null;
            
            if (!label) {
                alert('Please enter a label for the marker');
                return;
            }
            
            // Validate audio file size (15MB max)
            if (audioFile && audioFile.size > 15 * 1024 * 1024) {
                alert('Audio file must be less than 15MB');
                return;
            }
            
            const success = await createMarker(yaw, pitch, label, description, color, audioFile, targetPanoramaId);
            if (success) {
                bootstrap.Modal.getInstance(document.getElementById('addMarkerModal')).hide();
                // Reset audio input
                audioInput.value = '';
            }
        });
        
        // Click on marker to edit (when in edit mode and owner) OR play audio OR navigate (portal)
        markersPlugin.addEventListener('select-marker', (e) => {
            const markerData = e.marker.config.data;
            
            // If marker is a portal and NOT in edit mode, navigate to target panorama
            if (markerData && markerData.target_panorama_id && !editMode) {
                navigateToPortal(markerData.target_panorama_id);
                return;
            }
            
            // If marker has audio and NOT in edit mode, toggle audio playback
            if (markerData && markerData.audio_path && !editMode) {
                toggleMarkerAudio(markerData.id, markerData.audio_path);
                return;
            }
            
            if (editMode && isOwner && markerData) {
                // Check if current user owns this marker
                const currentUserId = <?= $currentUserId ?? 'null' ?>;
                if (parseInt(markerData.user_id) !== currentUserId) {
                    alert('You can only edit your own markers.');
                    return;
                }
                
                // Open edit modal with current data
                document.getElementById('editMarkerId').value = markerData.id;
                document.getElementById('editMarkerLabel').value = markerData.label || '';
                document.getElementById('editMarkerDescription').value = markerData.description || '';
                document.getElementById('editMarkerColor').value = markerData.color || 'blue';
                document.getElementById('editRemoveAudio').value = '0';
                document.getElementById('editMarkerAudio').value = '';
                
                // Set target panorama dropdown
                const targetPanoramaSelect = document.getElementById('editMarkerTargetPanorama');
                if (targetPanoramaSelect) {
                    targetPanoramaSelect.value = markerData.target_panorama_id || '';
                }
                
                // Show/hide current audio info
                const currentAudioDiv = document.getElementById('editCurrentAudio');
                if (markerData.audio_path) {
                    currentAudioDiv.classList.remove('d-none');
                    document.getElementById('editAudioFilename').textContent = markerData.audio_path.split('/').pop();
                    document.getElementById('editAudioFilename').dataset.path = markerData.audio_path;
                } else {
                    currentAudioDiv.classList.add('d-none');
                }
                
                // Update color picker selection
                document.querySelectorAll('#editColorPicker .color-option').forEach(opt => {
                    opt.classList.toggle('selected', opt.dataset.color === (markerData.color || 'blue'));
                });
                
                const modal = new bootstrap.Modal(document.getElementById('editMarkerModal'));
                modal.show();
            }
        });
        
        // Navigate to target panorama (portal marker click)
        function navigateToPortal(targetPanoramaId) {
            // Add fade-to-black transition effect
            const overlay = document.createElement('div');
            overlay.className = 'portal-transition-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: black;
                opacity: 0;
                z-index: 9999;
                transition: opacity 0.5s ease;
                pointer-events: none;
            `;
            document.body.appendChild(overlay);
            
            // Trigger fade to black
            requestAnimationFrame(() => {
                overlay.style.opacity = '1';
            });
            
            // Navigate after fade completes
            setTimeout(() => {
                window.location.href = `/view.php?id=${targetPanoramaId}`;
            }, 500);
        }
        
        // Update marker button
        document.getElementById('updateMarkerBtn').addEventListener('click', async () => {
            const id = parseInt(document.getElementById('editMarkerId').value);
            const label = document.getElementById('editMarkerLabel').value.trim();
            const description = document.getElementById('editMarkerDescription').value.trim();
            const color = document.getElementById('editMarkerColor').value;
            const removeAudio = document.getElementById('editRemoveAudio').value === '1';
            const audioInput = document.getElementById('editMarkerAudio');
            const audioFile = audioInput.files.length > 0 ? audioInput.files[0] : null;
            const targetPanoramaSelect = document.getElementById('editMarkerTargetPanorama');
            const targetPanoramaId = targetPanoramaSelect && targetPanoramaSelect.value ? parseInt(targetPanoramaSelect.value) : null;
            
            if (!label) {
                alert('Please enter a label for the marker');
                return;
            }
            
            // Validate audio file size (15MB max)
            if (audioFile && audioFile.size > 15 * 1024 * 1024) {
                alert('Audio file must be less than 15MB');
                return;
            }
            
            const success = await updateMarker(id, label, description, color, audioFile, removeAudio, targetPanoramaId);
            if (success) {
                bootstrap.Modal.getInstance(document.getElementById('editMarkerModal')).hide();
            }
        });
        
        // Delete marker button
        document.getElementById('deleteMarkerBtn').addEventListener('click', async () => {
            if (!confirm('Are you sure you want to delete this marker?')) return;
            
            const id = parseInt(document.getElementById('editMarkerId').value);
            const success = await deleteMarker(id);
            if (success) {
                bootstrap.Modal.getInstance(document.getElementById('editMarkerModal')).hide();
            }
        });
        
        // Save to collection button
        const saveToCollectionBtn = document.getElementById('saveToCollectionBtn');
        if (saveToCollectionBtn) {
            saveToCollectionBtn.addEventListener('click', () => {
                if (confirm('Save this panorama to your collection? You will be able to add your own markers.')) {
                    forkPanorama();
                }
            });
        }
        
        // ========== VOTING SYSTEM ==========
        
        let currentUserVote = 0;
        let currentScore = 0;
        
        async function loadVoteStatus() {
            try {
                const response = await fetch(`/api.php?action=vote/status&panorama_id=${panoramaId}`);
                const data = await response.json();
                
                if (data.success) {
                    currentScore = data.score;
                    currentUserVote = data.userVote;
                    updateVoteUI();
                }
            } catch (error) {
                console.error('Failed to load vote status:', error);
            }
        }
        
        function updateVoteUI() {
            const scoreEl = document.getElementById('voteScore');
            const upBtn = document.getElementById('upvoteBtn');
            const downBtn = document.getElementById('downvoteBtn');
            
            if (!scoreEl) return;
            
            scoreEl.textContent = currentScore;
            scoreEl.classList.remove('positive', 'negative');
            if (currentScore > 0) scoreEl.classList.add('positive');
            else if (currentScore < 0) scoreEl.classList.add('negative');
            
            if (upBtn) {
                upBtn.classList.toggle('active', currentUserVote === 1);
            }
            if (downBtn) {
                downBtn.classList.toggle('active', currentUserVote === -1);
            }
        }
        
        async function castVote(value) {
            if (!isLoggedIn) {
                window.location.href = '/login.php';
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=vote/toggle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        panorama_id: panoramaId,
                        value: value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentScore = data.score;
                    currentUserVote = data.userVote;
                    updateVoteUI();
                } else {
                    if (data.error) alert(data.error);
                }
            } catch (error) {
                console.error('Failed to vote:', error);
            }
        }
        
        // Vote button handlers
        const upvoteBtn = document.getElementById('upvoteBtn');
        const downvoteBtn = document.getElementById('downvoteBtn');
        
        if (upvoteBtn) {
            upvoteBtn.addEventListener('click', () => castVote(1));
        }
        if (downvoteBtn) {
            downvoteBtn.addEventListener('click', () => castVote(-1));
        }
        
        // ========== SIDEBAR TOGGLE ==========
        
        const sidebarToggle = document.getElementById('sidebarToggle');
        const markerSidebar = document.getElementById('markerSidebar');
        
        if (sidebarToggle && markerSidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebarToggle.classList.toggle('open');
                markerSidebar.classList.toggle('open');
            });
        }
        
        // ========== EXPORT DATA ==========
        
        const exportDataBtn = document.getElementById('exportDataBtn');
        if (exportDataBtn) {
            exportDataBtn.addEventListener('click', async () => {
                try {
                    const response = await fetch(`/api.php?action=panorama/export&panorama_id=${panoramaId}`);
                    const data = await response.json();
                    
                    if (!response.ok) {
                        alert(data.error || 'Failed to export data');
                        return;
                    }
                    
                    // Create download
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `panorama-${panoramaId}-export.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } catch (error) {
                    console.error('Failed to export:', error);
                    alert('Failed to export data. Please try again.');
                }
            });
        }
        
        // Load vote status on page load
        if (document.getElementById('votingPanel')) {
            loadVoteStatus();
        }
        
        // ========== EDIT MODAL AUDIO HANDLERS ==========
        
        // Preview audio button
        const previewAudioBtn = document.getElementById('previewAudioBtn');
        if (previewAudioBtn) {
            let previewAudio = null;
            previewAudioBtn.addEventListener('click', () => {
                const audioPath = document.getElementById('editAudioFilename').dataset.path;
                if (!audioPath) return;
                
                if (previewAudio) {
                    previewAudio.pause();
                    previewAudio = null;
                    previewAudioBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
                } else {
                    previewAudio = new Audio('/' + audioPath);
                    previewAudioBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
                    previewAudio.play();
                    previewAudio.addEventListener('ended', () => {
                        previewAudioBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
                        previewAudio = null;
                    });
                }
            });
        }
        
        // Remove audio button
        const removeAudioBtn = document.getElementById('removeAudioBtn');
        if (removeAudioBtn) {
            removeAudioBtn.addEventListener('click', () => {
                if (confirm('Remove audio from this marker?')) {
                    document.getElementById('editRemoveAudio').value = '1';
                    document.getElementById('editCurrentAudio').classList.add('d-none');
                }
            });
        }
        
        // Audio file input validation
        document.getElementById('markerAudio')?.addEventListener('change', function() {
            if (this.files.length > 0 && this.files[0].size > 15 * 1024 * 1024) {
                alert('Audio file must be less than 15MB');
                this.value = '';
            }
        });
        
        document.getElementById('editMarkerAudio')?.addEventListener('change', function() {
            if (this.files.length > 0 && this.files[0].size > 15 * 1024 * 1024) {
                alert('Audio file must be less than 15MB');
                this.value = '';
            }
        });
    </script>
</body>
</html>
