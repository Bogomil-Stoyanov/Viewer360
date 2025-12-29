<?php

namespace App\Controllers;

use App\Config;
use App\Database;

class PanoramaController
{
    public function upload(array $file, string $title, string $description, bool $isPublic): array
    {
        // Check if user is logged in
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'errors' => ['You must be logged in to upload.']];
        }

        $errors = [];

        // Validate title
        if (empty($title) || strlen($title) > 200) {
            $errors[] = "Title is required and must be less than 200 characters.";
        }

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = "Please select a file to upload.";
            return ['success' => false, 'errors' => $errors];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['success' => false, 'errors' => $errors];
        }

        // Validate file size
        $maxSize = Config::get('upload.max_size');
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds the maximum limit of 50MB";
        }

        // Validate file type using MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedTypes = Config::get('upload.allowed_types');

        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = "Only JPEG and PNG images are allowed.";
        }

        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = Config::get('upload.allowed_extensions');

        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "Invalid file extension. Only .jpg, .jpeg, and .png are allowed.";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Generate unique filename
        $newFilename = md5(time() . $file['name'] . uniqid()) . '.' . $extension;
        $uploadDir = Config::get('upload.upload_dir');
        $destination = $uploadDir . $newFilename;

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'errors' => ['Failed to save the uploaded file.']];
        }

        // Insert record into database
        try {
            $userId = AuthController::getCurrentUserId();
            $filePath = 'uploads/' . $newFilename;

            Database::query(
                "INSERT INTO panoramas (user_id, file_path, title, description, is_public) VALUES (?, ?, ?, ?, ?)",
                [$userId, $filePath, $title, $description, $isPublic ? 1 : 0]
            );

            $panoramaId = Database::lastInsertId();

            return [
                'success' => true,
                'message' => 'Panorama uploaded successfully!',
                'panorama_id' => $panoramaId
            ];
        } catch (\PDOException $e) {
            // Delete the uploaded file if database insert fails
            unlink($destination);
            error_log("Panorama upload error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Failed to save panorama. Please try again.']];
        }
    }

    public function getUserPanoramas(int $userId): array
    {
        $stmt = Database::query(
            "SELECT id, file_path, title, description, is_public, created_at 
             FROM panoramas 
             WHERE user_id = ? 
             ORDER BY created_at DESC",
            [$userId]
        );

        return $stmt->fetchAll();
    }

    public function getPanorama(int $id): ?array
    {
        $stmt = Database::query(
            "SELECT p.*, u.username 
             FROM panoramas p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.id = ?",
            [$id]
        );

        return $stmt->fetch() ?: null;
    }

    public function canView(array $panorama): bool
    {
        // Public panoramas can be viewed by anyone
        if ($panorama['is_public']) {
            return true;
        }

        // Private panoramas can only be viewed by the owner
        $currentUserId = AuthController::getCurrentUserId();
        return $currentUserId !== null && $currentUserId === (int)$panorama['user_id'];
    }

    public function delete(int $id): array
    {
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'errors' => ['You must be logged in to delete.']];
        }

        $panorama = $this->getPanorama($id);

        if (!$panorama) {
            return ['success' => false, 'errors' => ['Panorama not found.']];
        }

        // Check ownership
        if ((int)$panorama['user_id'] !== AuthController::getCurrentUserId()) {
            return ['success' => false, 'errors' => ['You do not have permission to delete this panorama.']];
        }

        try {
            // Delete from database
            Database::query("DELETE FROM panoramas WHERE id = ?", [$id]);

            // Delete physical file
            $filePath = __DIR__ . '/../../public/' . $panorama['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return ['success' => true, 'message' => 'Panorama deleted successfully.'];
        } catch (\PDOException $e) {
            error_log("Panorama delete error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Failed to delete panorama.']];
        }
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server limit.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.',
        };
    }
}
