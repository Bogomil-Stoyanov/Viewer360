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

include __DIR__ . '/../views/header.php';
?>

<div class="container">
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
                <a href="/login.php" class="btn btn-outline-primary btn-lg px-4 gap-3">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </a>
                <a href="/register.php" class="btn btn-outline-secondary btn-lg px-4">
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
                                <p class="card-text text-muted">Upload your panoramic images up to 20MB in JPG or PNG format.</p>
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
