<?php

require_once __DIR__ . '/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Controllers\AuthController;
use App\Database;

$stmt = Database::query(
    "SELECT p.id, p.file_path, p.title, p.description, p.created_at,
            u.username,
            COALESCE((SELECT SUM(value) FROM votes WHERE panorama_id = p.id), 0) as vote_score,
            (SELECT COUNT(*) FROM markers WHERE panorama_id = p.id) as marker_count
     FROM panoramas p
     JOIN users u ON p.user_id = u.id
     WHERE p.is_public = 1
     ORDER BY p.created_at DESC"
);

$panoramas = $stmt->fetchAll();

$pageTitle = 'Explore - Viewer360';
$currentPage = 'explore';

include __DIR__ . '/../views/header.php';
?>

<style>
    .panorama-card {
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    
    .panorama-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    .panorama-thumbnail {
        height: 200px;
        background-color: #1a1a2e;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    
    .panorama-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }
    
    .panorama-card:hover .panorama-thumbnail img {
        transform: scale(1.05);
    }
    
    .panorama-thumbnail .icon-360 {
        font-size: 4rem;
        color: rgba(255,255,255,0.3);
        position: absolute;
        z-index: 1;
    }
    
    .panorama-thumbnail .overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.7) 100%);
        z-index: 2;
    }
    
    .vote-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 3;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        backdrop-filter: blur(5px);
    }
    
    .vote-badge.positive {
        color: #198754;
    }
    
    .vote-badge.negative {
        color: #dc3545;
    }
    
    .marker-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 3;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        backdrop-filter: blur(5px);
    }
    
    .card-title {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 5rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="bi bi-compass"></i> Explore</h1>
            <p class="text-muted">Discover amazing 360° panoramas from the community</p>
        </div>
    </div>

    <?php if (empty($panoramas)): ?>
        <div class="empty-state">
            <i class="bi bi-globe"></i>
            <h3>No panoramas yet</h3>
            <p class="text-muted">Be the first to share a 360° panorama!</p>
            <?php if (!AuthController::isLoggedIn()): ?>
                <a href="/register.php" class="btn btn-primary mt-3">
                    <i class="bi bi-person-plus"></i> Create Account
                </a>
            <?php else: ?>
                <a href="/dashboard.php" class="btn btn-primary mt-3">
                    <i class="bi bi-upload"></i> Upload Panorama
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($panoramas as $panorama): ?>
                <div class="col">
                    <a href="/view.php?id=<?= $panorama['id'] ?>" class="text-decoration-none">
                        <div class="card panorama-card h-100">
                            <div class="panorama-thumbnail">
                                <i class="bi bi-badge-vr icon-360"></i>
                                <img src="/<?= htmlspecialchars($panorama['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($panorama['title']) ?>"
                                     loading="lazy">
                                <div class="overlay"></div>
                                
                                <?php 
                                $score = (int)$panorama['vote_score'];
                                $scoreClass = $score > 0 ? 'positive' : ($score < 0 ? 'negative' : '');
                                ?>
                                <span class="vote-badge <?= $scoreClass ?>">
                                    <i class="bi bi-arrow-up-short"></i> <?= $score ?>
                                </span>
                                
                                <?php if ((int)$panorama['marker_count'] > 0): ?>
                                    <span class="marker-badge">
                                        <i class="bi bi-pin-map"></i> <?= $panorama['marker_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($panorama['title']) ?></h5>
                                <p class="card-text text-muted small mb-0">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($panorama['username']) ?>
                                    <span class="ms-2">
                                        <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($panorama['created_at'])) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
