<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;

$auth = new AuthController();

// Redirect to dashboard if already logged in
if (AuthController::isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$pageTitle = 'Welcome - Viewer360';
$currentPage = 'home';

// Background images for the animated panorama effect
$backgrounds = [
    '/backgrounds/1.jpg',
    '/backgrounds/2.jpg',
    '/backgrounds/3.png',
    '/backgrounds/4.jpg'
];
$randomBg = $backgrounds[array_rand($backgrounds)];

include __DIR__ . '/../views/header.php';
?>

<style>
    /* Animated panoramic background */
    .panorama-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
    }
    
    .panorama-bg::before {
        content: '';
        position: absolute;
        top: -10%;
        left: -10%;
        width: 200%;
        height: 120%;
        background-image: url('<?= $randomBg ?>');
        background-size: cover;
        background-position: center;
        filter: blur(8px) brightness(0.4);
        animation: slowPan 40s ease-in-out infinite alternate;
    }
    
    .panorama-bg::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(14, 14, 14, 0.05) 0%, rgba(0,0,0,0.1) 100%);
    }
    
    @keyframes slowPan {
        0% {
            transform: translateX(0%) scale(1.1);
        }
        50% {
            transform: translateX(-25%) scale(1.15);
        }
        100% {
            transform: translateX(-50%) scale(1.1);
        }
    }
    
    /* Adjust content for dark background */
    .hero-content {
        position: relative;
        z-index: 1;
    }
    
    .hero-content h1,
    .hero-content .lead {
        color: white;
        text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
    }
    
    .hero-content .card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
    
    /* Make navbar blend better */
    body {
        background: #1a1a2e;
    }
</style>

<!-- Animated Background -->
<div class="panorama-bg"></div>

<div class="container hero-content">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-8 text-center">
            <h1 class="display-4 mb-4">
                <i class="bi bi-globe2 text-primary"></i> Viewer360
            </h1>
            <p class="lead mb-5">
                Upload, view, and share your panoramic images in stunning 360-degree interactive views.
            </p>
            <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                <a href="/explore.php" class="btn btn-primary btn-lg px-4 gap-3">
                    <i class="bi bi-compass"></i> Explore Gallery
                </a>
                <a href="/login.php" class="btn btn-outline-light btn-lg px-4 gap-3">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </a>
                <a href="/register.php" class="btn btn-outline-light btn-lg px-4">
                    <i class="bi bi-person-plus"></i> Register
                </a>
            </div>
            
            <div class="mt-5 pt-5">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-upload display-4 text-primary mb-3"></i>
                                <h5 class="card-title">Easy Upload</h5>
                                <p class="card-text text-muted">Upload your panoramic images up to 50MB in JPG or PNG format.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-eye display-4 text-primary mb-3"></i>
                                <h5 class="card-title">Interactive Viewer</h5>
                                <p class="card-text text-muted">Explore your panoramas with smooth 360° navigation controls.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-share display-4 text-primary mb-3"></i>
                                <h5 class="card-title">Share & Protect</h5>
                                <p class="card-text text-muted">Keep your panoramas private or share them publicly with others.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
