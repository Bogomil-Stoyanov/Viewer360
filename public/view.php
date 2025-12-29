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
        }
        
        .custom-marker:hover {
            transform: scale(1.2);
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        
        .custom-marker.highlighted {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            animation: pulse 1s ease-in-out 3;
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
            const tooltipContent = `
                <div class="marker-tooltip">
                    <h6>${escapeHtml(marker.label)}</h6>
                    ${marker.description ? `<p>${escapeHtml(marker.description)}</p>` : ''}
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
            
            return {
                id: `marker-${marker.id}`,
                position: { yaw: parseFloat(marker.yaw), pitch: parseFloat(marker.pitch) },
                html: `<div class="custom-marker ${isHighlighted ? 'highlighted' : ''}" 
                            data-marker-id="${marker.id}" 
                            style="background: ${markerGradient}; border-color: ${borderColor};"></div>`,
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
        
        // Render markers on the viewer
        function renderMarkers() {
            // Clear existing markers
            markersPlugin.clearMarkers();
            
            // Add each marker
            markersData.forEach(marker => {
                const isHighlighted = highlightMarkerId && parseInt(marker.id) === highlightMarkerId;
                markersPlugin.addMarker(createMarkerConfig(marker, isHighlighted));
            });
        }
        
        // Create a new marker
        async function createMarker(yaw, pitch, label, description, color) {
            try {
                const response = await fetch('/api.php?action=marker/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        panorama_id: panoramaId,
                        yaw: yaw,
                        pitch: pitch,
                        label: label,
                        description: description,
                        color: color,
                        type: 'text'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Add to local data and render
                    data.marker.username = '<?= htmlspecialchars(addslashes(AuthController::getCurrentUsername() ?? '')) ?>';
                    markersData.push(data.marker);
                    markersPlugin.addMarker(createMarkerConfig(data.marker));
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
        async function updateMarker(id, label, description, color) {
            try {
                const response = await fetch('/api.php?action=marker/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        label: label,
                        description: description,
                        color: color,
                        type: 'text'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update local data
                    const markerIndex = markersData.findIndex(m => parseInt(m.id) === id);
                    if (markerIndex !== -1) {
                        markersData[markerIndex].label = label;
                        markersData[markerIndex].description = description;
                        markersData[markerIndex].color = color;
                        
                        // Update the marker in the viewer
                        markersPlugin.updateMarker(createMarkerConfig(markersData[markerIndex]));
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
        
        // ========== EVENT HANDLERS ==========
        
        // Hide loading overlay and initialize when ready
        viewer.addEventListener('ready', () => {
            document.getElementById('loadingOverlay').classList.add('hidden');
            
            // Load markers
            loadMarkers().then(() => {
                // Handle deep linking - animate to marker if specified
                if (highlightMarkerId) {
                    setTimeout(() => animateToMarker(highlightMarkerId), 500);
                }
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
            
            if (!label) {
                alert('Please enter a label for the marker');
                return;
            }
            
            const success = await createMarker(yaw, pitch, label, description, color);
            if (success) {
                bootstrap.Modal.getInstance(document.getElementById('addMarkerModal')).hide();
            }
        });
        
        // Click on marker to edit (when in edit mode and owner)
        markersPlugin.addEventListener('select-marker', (e) => {
            const markerData = e.marker.config.data;
            
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
                
                // Update color picker selection
                document.querySelectorAll('#editColorPicker .color-option').forEach(opt => {
                    opt.classList.toggle('selected', opt.dataset.color === (markerData.color || 'blue'));
                });
                
                const modal = new bootstrap.Modal(document.getElementById('editMarkerModal'));
                modal.show();
            }
        });
        
        // Update marker button
        document.getElementById('updateMarkerBtn').addEventListener('click', async () => {
            const id = parseInt(document.getElementById('editMarkerId').value);
            const label = document.getElementById('editMarkerLabel').value.trim();
            const description = document.getElementById('editMarkerDescription').value.trim();
            const color = document.getElementById('editMarkerColor').value;
            
            if (!label) {
                alert('Please enter a label for the marker');
                return;
            }
            
            const success = await updateMarker(id, label, description, color);
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
    </script>
</body>
</html>
