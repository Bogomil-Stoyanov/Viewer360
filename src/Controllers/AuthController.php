<?php

namespace App\Controllers;

use App\Database;

class AuthController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function register(string $username, string $email, string $password): array
    {
        $errors = [];

        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Username must be between 3 and 50 characters.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $stmt = Database::query(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );

        if ($stmt->fetch()) {
            return ['success' => false, 'errors' => ['Username or email already exists.']];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            Database::query(
                "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)",
                [$username, $email, $passwordHash]
            );

            return ['success' => true, 'message' => 'Registration successful! Please log in.'];
        } catch (\PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }

    public function login(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'errors' => ['Please enter email and password.']];
        }

        $stmt = Database::query(
            "SELECT id, username, email, password_hash, role, is_banned FROM users WHERE email = ?",
            [$email]
        );

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Invalid email or password.']];
        }

        if ($user['is_banned']) {
            return ['success' => false, 'errors' => ['Your account has been suspended. Please contact support.']];
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['logged_in'] = true;

        return ['success' => true, 'message' => 'Login successful!'];
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }

    public static function getCurrentUsername(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['username'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function isAdmin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return self::isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>';
            echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
            echo '<p><a href="/">Go to Home</a></p></body></html>';
            exit;
        }
    }

    public static function getCurrentRole(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['role'] ?? null;
    }
}
