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
            "SELECT p.id, p.file_path, p.title, p.description, p.is_public, p.original_panorama_id, p.created_at,
                    op.title as original_title, ou.username as original_username
             FROM panoramas p
             LEFT JOIN panoramas op ON p.original_panorama_id = op.id
             LEFT JOIN users ou ON op.user_id = ou.id
             WHERE p.user_id = ? 
             ORDER BY p.created_at DESC",
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

        // Admins can view all panoramas
        if (AuthController::isAdmin()) {
            return true;
        }

        // Private panoramas can only be viewed by the owner
        $currentUserId = AuthController::getCurrentUserId();
        return $currentUserId !== null && $currentUserId === (int)$panorama['user_id'];
    }

    /**
     * Update panorama settings (title, description, visibility)
     */
    public function update(int $id, string $title, string $description, bool $isPublic): array
    {
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'errors' => ['You must be logged in to update.']];
        }

        $panorama = $this->getPanorama($id);

        if (!$panorama) {
            return ['success' => false, 'errors' => ['Panorama not found.']];
        }

        // Check ownership
        if ((int)$panorama['user_id'] !== AuthController::getCurrentUserId()) {
            return ['success' => false, 'errors' => ['You do not have permission to edit this panorama.']];
        }

        // Validate title
        if (empty($title) || strlen($title) > 200) {
            return ['success' => false, 'errors' => ['Title is required and must be less than 200 characters.']];
        }

        try {
            Database::query(
                "UPDATE panoramas SET title = ?, description = ?, is_public = ? WHERE id = ?",
                [$title, $description, $isPublic ? 1 : 0, $id]
            );

            return ['success' => true, 'message' => 'Panorama updated successfully.'];
        } catch (\PDOException $e) {
            error_log("Panorama update error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Failed to update panorama.']];
        }
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
            // Check if this file is used by other panoramas (forks or original)
            $filePath = $panorama['file_path'];
            $stmt = Database::query(
                "SELECT COUNT(*) as count FROM panoramas WHERE file_path = ? AND id != ?",
                [$filePath, $id]
            );
            $result = $stmt->fetch();
            $isSharedFile = (int)($result['count'] ?? 0) > 0;
            
            // Delete from database
            Database::query("DELETE FROM panoramas WHERE id = ?", [$id]);

            // Only delete physical file if no other panoramas use it
            if (!$isSharedFile) {
                $fullPath = __DIR__ . '/../../public/' . $filePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
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

    /**
     * Fork/Remix a public panorama to user's collection
     * Does NOT duplicate the physical file - only creates a new DB reference
     */
    public function forkPanorama(int $sourceId, int $newUserId): array
    {
        // Get source panorama
        $source = $this->getPanorama($sourceId);

        if (!$source) {
            return ['success' => false, 'error' => 'Source panorama not found.'];
        }

        // Only allow forking public panoramas
        if (!$source['is_public']) {
            return ['success' => false, 'error' => 'Only public panoramas can be saved to your collection.'];
        }

        // Don't allow forking own panoramas
        if ((int)$source['user_id'] === $newUserId) {
            return ['success' => false, 'error' => 'This panorama is already in your collection.'];
        }

        // Check if user already forked this panorama
        $existing = Database::query(
            "SELECT id FROM panoramas WHERE user_id = ? AND original_panorama_id = ?",
            [$newUserId, $sourceId]
        )->fetch();

        if ($existing) {
            return ['success' => false, 'error' => 'You have already saved this panorama to your collection.'];
        }

        try {
            // Create new panorama entry pointing to same file
            Database::query(
                "INSERT INTO panoramas (user_id, file_path, title, description, is_public, original_panorama_id) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $newUserId,
                    $source['file_path'],  // Same file path - no duplication!
                    $source['title'] . ' (Remixed)',
                    $source['description'],
                    0,  // Start as private
                    $sourceId  // Track the original
                ]
            );

            $newPanoramaId = (int)Database::lastInsertId();

            // Copy all markers from source to new panorama
            $markerController = new MarkerController();
            $markerController->copyMarkers($sourceId, $newPanoramaId, $newUserId);

            return [
                'success' => true,
                'message' => 'Panorama saved to your collection!',
                'panorama_id' => $newPanoramaId
            ];
        } catch (\PDOException $e) {
            error_log("Fork panorama error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save panorama to your collection.'];
        }
    }

    /**
     * Check if a panorama is a fork of another
     */
    public function getOriginalPanorama(int $panoramaId): ?array
    {
        $panorama = $this->getPanorama($panoramaId);
        
        if (!$panorama || !$panorama['original_panorama_id']) {
            return null;
        }

        return $this->getPanorama((int)$panorama['original_panorama_id']);
    }

    /**
     * Get count of forks for a panorama
     */
    public function getForkCount(int $panoramaId): int
    {
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM panoramas WHERE original_panorama_id = ?",
            [$panoramaId]
        );
        
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get user's panoramas for portal linking dropdown
     * Returns simplified list with id and title
     */
    public function getUserPanoramasForLinking(int $userId, ?int $excludeId = null): array
    {
        if ($excludeId !== null) {
            $stmt = Database::query(
                "SELECT id, title, file_path FROM panoramas WHERE user_id = ? AND id != ? ORDER BY title ASC",
                [$userId, $excludeId]
            );
        } else {
            $stmt = Database::query(
                "SELECT id, title, file_path FROM panoramas WHERE user_id = ? ORDER BY title ASC",
                [$userId]
            );
        }

        return $stmt->fetchAll();
    }
}
