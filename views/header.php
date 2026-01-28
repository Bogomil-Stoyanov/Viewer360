<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Viewer360') ?></title>
    
    <!-- Main CSS -->
    <link href="<?= \App\Config::url('assets/css/main.css') ?>" rel="stylesheet">
    <!-- Bootstrap Icons (icon font only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?= \App\Config::url() ?>">
                <i class="bi bi-globe2"></i> Viewer360
            </a>
            <button class="navbar-toggler" type="button" onclick="toggleNavbar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage ?? '') === 'explore' ? 'active' : '' ?>" href="<?= \App\Config::url('explore.php') ?>">
                            <i class="bi bi-compass"></i><span class="ms-1">Explore</span>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (\App\Controllers\AuthController::isLoggedIn()): ?>
                        <?php if (\App\Controllers\AuthController::isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($currentPage ?? '') === 'admin' ? 'active' : '' ?>" href="<?= \App\Config::url('admin/dashboard.php') ?>">
                                <i class="bi bi-shield-lock"></i> Admin
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= \App\Config::url('dashboard.php') ?>">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" onclick="toggleDropdown(event)">
                                <i class="bi bi-person-circle"></i> 
                                <?= htmlspecialchars(\App\Controllers\AuthController::getCurrentUsername()) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" id="userDropdown">
                                <?php if (\App\Controllers\AuthController::isAdmin()): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= \App\Config::url('admin/dashboard.php') ?>">
                                        <i class="bi bi-shield-lock"></i> Admin Panel
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="<?= \App\Config::url('logout.php') ?>">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($currentPage ?? '') === 'login' ? 'active' : '' ?>" href="<?= \App\Config::url('login.php') ?>">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($currentPage ?? '') === 'register' ? 'active' : '' ?>" href="<?= \App\Config::url('register.php') ?>">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <script>
    function toggleNavbar() {
        const navbar = document.getElementById('navbarNav');
        navbar.classList.toggle('show');
    }
    
    function toggleDropdown(e) {
        e.preventDefault();
        e.stopPropagation();
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }
    
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && !e.target.closest('.dropdown')) {
            dropdown.classList.remove('show');
        }
    });
    </script>
