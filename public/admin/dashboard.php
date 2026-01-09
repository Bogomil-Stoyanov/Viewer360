<?php
/**
 * Admin Dashboard
 * Protected route - only accessible by admin users
 */

require_once __DIR__ . '/../autoload.php';

use App\Controllers\AuthController;
use App\Controllers\AdminController;

// Require admin access
AuthController::requireAdmin();

$adminController = new AdminController();

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'toggle_ban':
            $userId = (int)($_POST['user_id'] ?? 0);
            echo json_encode($adminController->toggleUserBan($userId));
            exit;
            
        case 'delete_panorama':
            $panoramaId = (int)($_POST['panorama_id'] ?? 0);
            echo json_encode($adminController->forceDeletePanorama($panoramaId));
            exit;
            
        case 'delete_marker':
            $markerId = (int)($_POST['marker_id'] ?? 0);
            echo json_encode($adminController->forceDeleteMarker($markerId));
            exit;
            
        case 'cleanup_orphans':
            echo json_encode($adminController->cleanupOrphanFiles());
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}

// Get data for display
$stats = $adminController->getStats();
$markerStats = $adminController->getMarkerStats();
$users = $adminController->getAllUsers();

// Filter panoramas by user if specified
$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$panoramas = $adminController->getAllPanoramas($filterUserId);

// Filter markers by panorama if specified
$filterPanoramaId = isset($_GET['panorama_id']) ? (int)$_GET['panorama_id'] : null;
$markers = $adminController->getAllMarkers($filterPanoramaId);

$pageTitle = 'Admin Dashboard - Viewer360';
$currentPage = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Main CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    <!-- Bootstrap Icons (icon font only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            background: #343a40;
            min-height: 100vh;
            padding: 20px;
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            border-radius: 8px;
            margin-bottom: 5px;
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .admin-content {
            padding: 30px;
        }
        
        .stat-card {
            background: #0d6efd;
            border-radius: 15px;
            padding: 25px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card.users {
            background: #198754;
        }
        
        .stat-card.panoramas {
            background: #fd7e14;
        }
        
        .stat-card.storage {
            background: #6f42c1;
        }
        
        .stat-card.markers {
            background: #0dcaf0;
        }
        
        .stat-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            opacity: 0.9;
            margin: 0;
        }
        
        .card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            color: #212529;
        }
        
        .table {
            color: #212529;
        }
        
        .table thead th {
            border-bottom-color: #dee2e6;
            color: #6c757d;
            font-weight: 500;
        }
        
        .table tbody td {
            border-bottom-color: #f1f1f1;
            vertical-align: middle;
        }
        
        .thumbnail-small {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .badge-admin {
            background: #6f42c1;
        }
        
        .badge-banned {
            background: #dc3545;
        }
        
        .btn-ban {
            padding: 5px 12px;
            font-size: 0.85rem;
        }
        
        .nav-tabs {
            border-bottom-color: #dee2e6;
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link:hover {
            color: #212529;
            border-color: transparent;
        }
        
        .nav-tabs .nav-link.active {
            background: transparent;
            color: #0d6efd;
            border-bottom-color: #0d6efd;
        }
        
        .filter-badge {
            background: #0d6efd;
            color: white;
        }
        
        .page-title {
            color: #212529;
        }
        
        .text-white-override {
            color: #212529 !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 admin-sidebar">
                <h4 class="text-white mb-4">
                    <i class="bi bi-shield-lock"></i> Admin
                </h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="/admin/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="/dashboard.php">
                        <i class="bi bi-arrow-left"></i> Back to Site
                    </a>
                    <a class="nav-link" href="/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 admin-content">
                <h2 class="page-title mb-4">Dashboard Overview</h2>
                
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card users">
                            <h2><?= number_format($stats['total_users']) ?></h2>
                            <p><i class="bi bi-people"></i> Total Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card panoramas">
                            <h2><?= number_format($stats['total_panoramas']) ?></h2>
                            <p><i class="bi bi-images"></i> Total Panoramas</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card markers">
                            <h2><?= number_format($markerStats['total_markers']) ?></h2>
                            <p><i class="bi bi-pin-map"></i> Total Markers</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card storage">
                            <h2><?= $stats['storage_formatted'] ?></h2>
                            <p><i class="bi bi-hdd"></i> Storage Used</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-tab-target="#users" type="button">
                            <i class="bi bi-people"></i> User Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="content-tab" data-tab-target="#content" type="button">
                            <i class="bi bi-images"></i> Content Moderation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="markers-tab" data-tab-target="#markers" type="button">
                            <i class="bi bi-pin-map"></i> Marker Moderation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="storage-tab" data-tab-target="#storage" type="button">
                            <i class="bi bi-hdd"></i> Storage Cleanup
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="adminTabContent">
                    <!-- Users Tab -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-people"></i> All Users</h5>
                                <span class="badge bg-secondary"><?= count($users) ?> users</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Panoramas</th>
                                                <th>Joined</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                            <tr id="user-row-<?= $user['id'] ?>">
                                                <td><?= $user['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <span class="badge badge-admin">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">User</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['panorama_count'] > 0): ?>
                                                        <a href="?user_id=<?= $user['id'] ?>#content" class="text-decoration-none">
                                                            <?= $user['panorama_count'] ?> <i class="bi bi-arrow-right-circle"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        0
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge <?= $user['is_banned'] ? 'badge-banned' : 'bg-success' ?>" id="status-badge-<?= $user['id'] ?>">
                                                        <?= $user['is_banned'] ? 'Banned' : 'Active' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['role'] !== 'admin'): ?>
                                                        <button class="btn btn-sm <?= $user['is_banned'] ? 'btn-success' : 'btn-warning' ?> btn-ban"
                                                                onclick="toggleBan(<?= $user['id'] ?>)"
                                                                id="ban-btn-<?= $user['id'] ?>">
                                                            <i class="bi bi-<?= $user['is_banned'] ? 'unlock' : 'lock' ?>"></i>
                                                            <?= $user['is_banned'] ? 'Unban' : 'Ban' ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Tab -->
                    <div class="tab-pane fade" id="content" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-images"></i> All Panoramas
                                    <?php if ($filterUserId): ?>
                                        <span class="badge filter-badge ms-2">
                                            Filtered by user #<?= $filterUserId ?>
                                            <a href="?" class="text-white ms-1"><i class="bi bi-x"></i></a>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <span class="badge bg-secondary"><?= count($panoramas) ?> panoramas</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Thumbnail</th>
                                                <th>Title</th>
                                                <th>Owner</th>
                                                <th>Visibility</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="panoramas-table">
                                            <?php foreach ($panoramas as $panorama): ?>
                                            <tr id="panorama-row-<?= $panorama['id'] ?>">
                                                <td><?= $panorama['id'] ?></td>
                                                <td>
                                                    <img src="/<?= htmlspecialchars($panorama['file_path']) ?>" 
                                                         class="thumbnail-small" 
                                                         alt="<?= htmlspecialchars($panorama['title']) ?>">
                                                </td>
                                                <td>
                                                    <a href="/view.php?id=<?= $panorama['id'] ?>" target="_blank" class="text-primary">
                                                        <?= htmlspecialchars($panorama['title']) ?>
                                                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="?user_id=<?= $panorama['user_id'] ?>#content" class="text-decoration-none">
                                                        <?= htmlspecialchars($panorama['username']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($panorama['is_public']): ?>
                                                        <span class="badge bg-success">Public</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Private</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($panorama['created_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="deletePanorama(<?= $panorama['id'] ?>, '<?= htmlspecialchars(addslashes($panorama['title'])) ?>')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($panoramas)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    No panoramas found.
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Storage Tab -->
                    <div class="tab-pane fade" id="storage" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-hdd"></i> Storage Cleanup</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Orphan File Cleanup</strong><br>
                                    This utility scans the uploads folder and removes files that are not referenced in the database.
                                    This can happen when database entries are deleted but files remain on disk.
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-dark">Current storage usage: <strong><?= $stats['storage_formatted'] ?></strong></p>
                                </div>
                                
                                <button class="btn btn-warning btn-lg" onclick="runCleanup()" id="cleanupBtn">
                                    <i class="bi bi-trash"></i> Run Storage Cleanup
                                </button>
                                
                                <div id="cleanupResults" class="mt-4" style="display: none;">
                                    <div class="alert alert-success">
                                        <h5><i class="bi bi-check-circle"></i> Cleanup Complete</h5>
                                        <p id="cleanupMessage"></p>
                                    </div>
                                    <div id="orphanFilesList"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Markers Tab -->
                    <div class="tab-pane fade" id="markers" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-pin-map"></i> All Markers
                                    <?php if ($filterPanoramaId): ?>
                                        <span class="badge filter-badge ms-2">
                                            Filtered by panorama #<?= $filterPanoramaId ?>
                                            <a href="?#markers" class="text-white ms-1"><i class="bi bi-x"></i></a>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <div>
                                    <span class="badge bg-info me-2" title="Audio markers">
                                        <i class="bi bi-volume-up"></i> <?= $markerStats['audio_markers'] ?>
                                    </span>
                                    <span class="badge bg-success me-2" title="Portal markers">
                                        <i class="bi bi-box-arrow-up-right"></i> <?= $markerStats['portal_markers'] ?>
                                    </span>
                                    <span class="badge bg-secondary"><?= count($markers) ?> markers</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Color</th>
                                                <th>Label</th>
                                                <th>Type</th>
                                                <th>Panorama</th>
                                                <th>Creator</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="markers-table">
                                            <?php foreach ($markers as $marker): ?>
                                            <tr id="marker-row-<?= $marker['id'] ?>">
                                                <td><?= $marker['id'] ?></td>
                                                <td>
                                                    <span class="marker-color-dot" style="display: inline-block; width: 16px; height: 16px; border-radius: 50%; background: <?= htmlspecialchars(\App\Controllers\MarkerController::COLORS[$marker['color']] ?? '#0d6efd') ?>; border: 2px solid #fff; box-shadow: 0 0 3px rgba(0,0,0,0.3);"></span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($marker['label']) ?></strong>
                                                    <?php if (!empty($marker['description'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars(substr($marker['description'], 0, 50)) ?><?= strlen($marker['description']) > 50 ? '...' : '' ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($marker['type'] === 'portal'): ?>
                                                        <span class="badge bg-success"><i class="bi bi-box-arrow-up-right"></i> Portal</span>
                                                    <?php elseif (!empty($marker['audio_path'])): ?>
                                                        <span class="badge bg-info"><i class="bi bi-volume-up"></i> Audio</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><i class="bi bi-chat-text"></i> Text</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="/view.php?id=<?= $marker['panorama_id'] ?>&marker=<?= $marker['id'] ?>" target="_blank" class="text-primary">
                                                        <?= htmlspecialchars($marker['panorama_title']) ?>
                                                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                    </a>
                                                    <br>
                                                    <a href="?panorama_id=<?= $marker['panorama_id'] ?>#markers" class="small text-muted">
                                                        Filter by panorama
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="?user_id=<?= $marker['user_id'] ?>#users" class="text-decoration-none">
                                                        <?= htmlspecialchars($marker['username']) ?>
                                                    </a>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($marker['created_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteMarker(<?= $marker['id'] ?>, '<?= htmlspecialchars(addslashes($marker['label'])) ?>')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($markers)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    No markers found.
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab functionality script -->
    <script>
        // Tab functionality (custom implementation)
        function initTabs() {
            const tabButtons = document.querySelectorAll('[data-tab-target]');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-tab-target');
                    
                    // Remove active from all buttons
                    tabButtons.forEach(b => b.classList.remove('active'));
                    // Add active to clicked button
                    this.classList.add('active');
                    
                    // Hide all panes
                    tabPanes.forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    
                    // Show target pane
                    const targetPane = document.querySelector(target);
                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                });
            });
        }
        
        // Toggle user ban
        async function toggleBan(userId) {
            if (!confirm('Are you sure you want to change this user\'s ban status?')) return;
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_ban');
                formData.append('user_id', userId);
                
                const response = await fetch('/admin/dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const badge = document.getElementById(`status-badge-${userId}`);
                    const btn = document.getElementById(`ban-btn-${userId}`);
                    
                    if (data.is_banned) {
                        badge.className = 'badge badge-banned';
                        badge.textContent = 'Banned';
                        btn.className = 'btn btn-sm btn-success btn-ban';
                        btn.innerHTML = '<i class="bi bi-unlock"></i> Unban';
                    } else {
                        badge.className = 'badge bg-success';
                        badge.textContent = 'Active';
                        btn.className = 'btn btn-sm btn-warning btn-ban';
                        btn.innerHTML = '<i class="bi bi-lock"></i> Ban';
                    }
                    
                    alert(data.message);
                } else {
                    alert(data.error || 'Failed to update user status');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        }
        
        // Delete panorama
        async function deletePanorama(panoramaId, title) {
            if (!confirm(`Are you sure you want to permanently delete "${title}"?\n\nThis will also delete the image file from the server.`)) return;
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'delete_panorama');
                formData.append('panorama_id', panoramaId);
                
                const response = await fetch('/admin/dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById(`panorama-row-${panoramaId}`).remove();
                    alert(data.message + (data.file_deleted ? ' (File removed from disk)' : ' (File shared with other panoramas)'));
                } else {
                    alert(data.error || 'Failed to delete panorama');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        }
        
        // Delete marker
        async function deleteMarker(markerId, label) {
            if (!confirm(`Are you sure you want to permanently delete the marker "${label}"?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'delete_marker');
                formData.append('marker_id', markerId);
                
                const response = await fetch('/admin/dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById(`marker-row-${markerId}`).remove();
                    alert(data.message + (data.audio_deleted ? ' (Audio file also removed)' : ''));
                } else {
                    alert(data.error || 'Failed to delete marker');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        }
        
        // Run orphan cleanup
        async function runCleanup() {
            if (!confirm('This will scan the uploads folder and delete any files not in the database. Continue?')) return;
            
            const btn = document.getElementById('cleanupBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scanning...';
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'cleanup_orphans');
                
                const response = await fetch('/admin/dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('cleanupResults').style.display = 'block';
                    document.getElementById('cleanupMessage').textContent = 
                        `Deleted ${data.deleted_count} orphan file(s). Freed ${data.freed_space_formatted} of space.`;
                    
                    if (data.orphan_files.length > 0) {
                        let html = '<h6 class="text-dark">Deleted files:</h6><ul class="text-dark">';
                        data.orphan_files.forEach(f => {
                            html += `<li>${f.name} (${f.size_formatted})</li>`;
                        });
                        html += '</ul>';
                        document.getElementById('orphanFilesList').innerHTML = html;
                    } else {
                        document.getElementById('orphanFilesList').innerHTML = '<p class="text-muted">No orphan files found.</p>';
                    }
                } else {
                    alert(data.error || 'Cleanup failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash"></i> Run Storage Cleanup';
            }
        }
        
        // Handle hash navigation for tabs
        document.addEventListener('DOMContentLoaded', function() {
            initTabs();
            
            if (window.location.hash) {
                const tabId = window.location.hash.substring(1);
                const tabTrigger = document.querySelector(`button[data-tab-target="#${tabId}"]`);
                if (tabTrigger) {
                    tabTrigger.click();
                }
            }
        });
    </script>
</body>
</html>
