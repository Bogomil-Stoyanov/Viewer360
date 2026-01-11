<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\PanoramaController;

AuthController::requireLogin();

$panoramaController = new PanoramaController();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isPublic = isset($_POST['is_public']);

    $result = $panoramaController->upload($_FILES['panorama'] ?? [], $title, $description, $isPublic);

    if ($result['success']) {
        $success = $result['message'];
    } else {
        $errors = $result['errors'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['panorama_id'] ?? 0);
    $result = $panoramaController->delete($id);

    if ($result['success']) {
        $success = $result['message'];
    } else {
        $errors = $result['errors'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)($_POST['panorama_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isPublic = isset($_POST['is_public']);

    $result = $panoramaController->update($id, $title, $description, $isPublic);

    if ($result['success']) {
        $success = $result['message'];
    } else {
        $errors = $result['errors'];
    }
}

$userId = AuthController::getCurrentUserId();
$panoramas = $panoramaController->getUserPanoramas($userId);

$pageTitle = 'Dashboard - Viewer360';
$currentPage = 'dashboard';

include __DIR__ . '/../views/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="bi bi-speedometer2"></i> Dashboard</h1>
            <p class="text-muted">Welcome back, <?= htmlspecialchars(AuthController::getCurrentUsername()) ?>!</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" onclick="this.closest('.alert').style.display='none'"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" onclick="this.closest('.alert').style.display='none'"></button>
        </div>
    <?php endif; ?>

    <!-- Upload Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-cloud-upload"></i> Upload New Panorama</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="/dashboard.php" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               maxlength="200" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="panorama" class="form-label">Panorama Image *</label>
                        <input type="file" class="form-control" id="panorama" name="panorama" 
                               accept=".jpg,.jpeg,.png" required>
                        <div class="form-text">JPG or PNG, max 50MB</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" 
                              rows="2" maxlength="1000"></textarea>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_public" 
                               name="is_public" checked>
                        <label class="form-check-label" for="is_public">
                            Make this panorama public (anyone can view)
                        </label>
                    </div>
                </div>

                <div class="progress mb-3 d-none" id="uploadProgress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="uploadBtn">
                    <i class="bi bi-upload"></i> Upload Panorama
                </button>
            </form>
        </div>
    </div>

    <!-- Panoramas List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-images"></i> My Panoramas</h5>
        </div>
        <div class="card-body">
            <?php if (empty($panoramas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-image display-1"></i>
                    <p class="mt-3">You haven't uploaded any panoramas yet.</p>
                    <p>Upload your first panoramic image above!</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($panoramas as $panorama): ?>
                        <div class="col">
                            <div class="card h-100">
                                <img src="/<?= htmlspecialchars($panorama['file_path']) ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($panorama['title']) ?>"
                                     style="height: 150px; object-fit: cover;">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($panorama['title']) ?></h5>
                                    <?php if (!empty($panorama['original_panorama_id'])): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-purple" style="background-color: #6f42c1;">
                                                <i class="bi bi-arrow-return-left"></i> Remixed from 
                                                <?= htmlspecialchars($panorama['original_username'] ?? 'Unknown') ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($panorama['description']): ?>
                                        <p class="card-text text-muted small">
                                            <?= htmlspecialchars(substr($panorama['description'], 0, 100)) ?>
                                            <?= strlen($panorama['description']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center">
                                        <?php if ($panorama['is_public']): ?>
                                            <span class="badge bg-success me-2">
                                                <i class="bi bi-globe"></i> Public
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary me-2">
                                                <i class="bi bi-lock"></i> Private
                                            </span>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($panorama['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="btn-group w-100">
                                        <a href="/view.php?id=<?= $panorama['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                onclick="openEditModal(<?= $panorama['id'] ?>, '<?= htmlspecialchars(addslashes($panorama['title'])) ?>', '<?= htmlspecialchars(addslashes($panorama['description'] ?? '')) ?>', <?= $panorama['is_public'] ? 'true' : 'false' ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="confirmDelete(<?= $panorama['id'] ?>, '<?= htmlspecialchars(addslashes($panorama['title'])) ?>')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<span id="deleteTitle"></span>"?</p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/dashboard.php" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="panorama_id" id="deletePanoramaId">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Panorama Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Panorama</h5>
                <button type="button" class="btn-close" data-dismiss="modal"></button>
            </div>
            <form method="POST" action="/dashboard.php" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="panorama_id" id="editPanoramaId">
                    
                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTitle" name="title" 
                               maxlength="200" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" 
                                  rows="3" maxlength="1000"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editIsPublic" name="is_public">
                            <label class="form-check-label" for="editIsPublic">
                                <i class="bi bi-globe"></i> Make this panorama public (anyone can view)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    document.getElementById('deletePanoramaId').value = id;
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteModal').classList.add('show');
}

function openEditModal(id, title, description, isPublic) {
    document.getElementById('editPanoramaId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editDescription').value = description;
    document.getElementById('editIsPublic').checked = isPublic;
    document.getElementById('editModal').classList.add('show');
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

document.getElementById('panorama').addEventListener('change', function() {
    const maxSize = 50 * 1024 * 1024; // 50MB
    if (this.files[0] && this.files[0].size > maxSize) {
        alert('File size exceeds 50MB limit. Please choose a smaller file.');
        this.value = '';
    }
});

document.querySelectorAll('.alert .btn-close').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.alert').style.display = 'none';
    });
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
