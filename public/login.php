<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;

$auth = new AuthController();

if (AuthController::isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = $auth->login($email, $password);

    if ($result['success']) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $errors = $result['errors'];
    }
}

$pageTitle = 'Login - Viewer360';
$currentPage = 'login';

include __DIR__ . '/../views/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h2><i class="bi bi-globe2 text-primary"></i> Viewer360</h2>
                        <p class="text-muted">Sign in to your account</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success">
                            Registration successful! Please log in.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/login.php">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <p class="text-center mb-0">
                        Don't have an account? <a href="/register.php">Register here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
