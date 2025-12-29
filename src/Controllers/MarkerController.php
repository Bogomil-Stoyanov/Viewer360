<?php

namespace App\Controllers;

use App\Config;
use App\Database;

class MarkerController
{
    /**
     * Create a new marker for a panorama
     */
    // Available marker colors
    public const COLORS = [
        'blue' => '#0d6efd',
        'red' => '#dc3545',
        'green' => '#198754',
        'yellow' => '#ffc107',
        'orange' => '#fd7e14',
        'purple' => '#6f42c1',
        'pink' => '#d63384',
        'cyan' => '#0dcaf0',
        'white' => '#ffffff'
    ];

    public function create(int $panoramaId, float $yaw, float $pitch, string $label, string $description = '', string $type = 'text', string $color = 'blue', ?array $audioFile = null, ?int $targetPanoramaId = null): array
    {
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'error' => 'You must be logged in to create markers.'];
        }

        $userId = AuthController::getCurrentUserId();

        // Validate inputs
        if (empty($label) || strlen($label) > 200) {
            return ['success' => false, 'error' => 'Label is required and must be less than 200 characters.'];
        }

        // Validate color
        if (!array_key_exists($color, self::COLORS)) {
            $color = 'blue';
        }

        // Check if panorama exists and user can access it
        $panoramaController = new PanoramaController();
        $panorama = $panoramaController->getPanorama($panoramaId);

        if (!$panorama) {
            return ['success' => false, 'error' => 'Panorama not found.'];
        }

        if (!$panoramaController->canView($panorama)) {
            return ['success' => false, 'error' => 'You do not have access to this panorama.'];
        }

        // Only allow marker creation on own panoramas or forked panoramas
        if ((int)$panorama['user_id'] !== $userId) {
            return ['success' => false, 'error' => 'You can only add markers to your own panoramas.'];
        }

        // Validate target panorama if it's a portal marker
        if ($targetPanoramaId !== null) {
            $targetPanorama = $panoramaController->getPanorama($targetPanoramaId);
            if (!$targetPanorama) {
                return ['success' => false, 'error' => 'Target panorama not found.'];
            }
            // Target must be user's own panorama
            if ((int)$targetPanorama['user_id'] !== $userId) {
                return ['success' => false, 'error' => 'You can only link to your own panoramas.'];
            }
            // Can't link to itself
            if ($targetPanoramaId === $panoramaId) {
                return ['success' => false, 'error' => 'Cannot link a panorama to itself.'];
            }
            // Force type to 'portal' when linking
            $type = 'portal';
        }

        // Handle audio file upload if provided
        $audioPath = null;
        if ($audioFile !== null && isset($audioFile['tmp_name']) && !empty($audioFile['tmp_name'])) {
            $audioResult = $this->handleAudioUpload($audioFile);
            if (!$audioResult['success']) {
                return $audioResult;
            }
            $audioPath = $audioResult['path'];
        }

        try {
            Database::query(
                "INSERT INTO markers (panorama_id, user_id, yaw, pitch, type, color, label, description, audio_path, target_panorama_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$panoramaId, $userId, $yaw, $pitch, $type, $color, $label, $description, $audioPath, $targetPanoramaId]
            );

            $markerId = Database::lastInsertId();

            return [
                'success' => true,
                'marker' => [
                    'id' => (int)$markerId,
                    'panorama_id' => $panoramaId,
                    'user_id' => $userId,
                    'yaw' => $yaw,
                    'pitch' => $pitch,
                    'type' => $type,
                    'color' => $color,
                    'label' => $label,
                    'description' => $description,
                    'audio_path' => $audioPath,
                    'target_panorama_id' => $targetPanoramaId
                ]
            ];
        } catch (\PDOException $e) {
            error_log("Marker creation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create marker.'];
        }
    }

    /**
     * Get all markers for a panorama
     */
    public function getByPanorama(int $panoramaId): array
    {
        // Check if panorama exists and user can access it
        $panoramaController = new PanoramaController();
        $panorama = $panoramaController->getPanorama($panoramaId);

        if (!$panorama) {
            return [];
        }

        if (!$panoramaController->canView($panorama)) {
            return [];
        }

        $stmt = Database::query(
            "SELECT m.*, u.username 
             FROM markers m 
             JOIN users u ON m.user_id = u.id 
             WHERE m.panorama_id = ? 
             ORDER BY m.created_at ASC",
            [$panoramaId]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get a single marker by ID
     */
    public function getMarker(int $id): ?array
    {
        $stmt = Database::query(
            "SELECT m.*, u.username 
             FROM markers m 
             JOIN users u ON m.user_id = u.id 
             WHERE m.id = ?",
            [$id]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Update a marker
     */
    public function update(int $id, string $label, string $description = '', string $type = 'text', string $color = 'blue', ?array $audioFile = null, bool $removeAudio = false, ?int $targetPanoramaId = null): array
    {
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'error' => 'You must be logged in to update markers.'];
        }

        $marker = $this->getMarker($id);

        if (!$marker) {
            return ['success' => false, 'error' => 'Marker not found.'];
        }

        // Only allow owner to update
        $userId = AuthController::getCurrentUserId();
        if ((int)$marker['user_id'] !== $userId) {
            return ['success' => false, 'error' => 'You can only edit your own markers.'];
        }

        // Validate inputs
        if (empty($label) || strlen($label) > 200) {
            return ['success' => false, 'error' => 'Label is required and must be less than 200 characters.'];
        }

        // Validate color
        if (!array_key_exists($color, self::COLORS)) {
            $color = 'blue';
        }

        // Validate target panorama if it's a portal marker
        $panoramaController = new PanoramaController();
        if ($targetPanoramaId !== null) {
            $targetPanorama = $panoramaController->getPanorama($targetPanoramaId);
            if (!$targetPanorama) {
                return ['success' => false, 'error' => 'Target panorama not found.'];
            }
            // Target must be user's own panorama
            if ((int)$targetPanorama['user_id'] !== $userId) {
                return ['success' => false, 'error' => 'You can only link to your own panoramas.'];
            }
            // Can't link to itself
            if ($targetPanoramaId === (int)$marker['panorama_id']) {
                return ['success' => false, 'error' => 'Cannot link a panorama to itself.'];
            }
            // Force type to 'portal' when linking
            $type = 'portal';
        }

        // Handle audio: remove, replace, or keep existing
        $audioPath = $marker['audio_path'] ?? null;
        
        if ($removeAudio) {
            // Delete existing audio file if present
            if ($audioPath) {
                $this->deleteAudioFile($audioPath);
            }
            $audioPath = null;
        } elseif ($audioFile !== null && isset($audioFile['tmp_name']) && !empty($audioFile['tmp_name'])) {
            // Delete old audio file if replacing
            if ($marker['audio_path']) {
                $this->deleteAudioFile($marker['audio_path']);
            }
            // Upload new audio
            $audioResult = $this->handleAudioUpload($audioFile);
            if (!$audioResult['success']) {
                return $audioResult;
            }
            $audioPath = $audioResult['path'];
        }

        try {
            Database::query(
                "UPDATE markers SET label = ?, description = ?, type = ?, color = ?, audio_path = ?, target_panorama_id = ? WHERE id = ?",
                [$label, $description, $type, $color, $audioPath, $targetPanoramaId, $id]
            );

            return [
                'success' => true,
                'marker' => array_merge($marker, [
                    'label' => $label,
                    'description' => $description,
                    'type' => $type,
                    'color' => $color,
                    'audio_path' => $audioPath,
                    'target_panorama_id' => $targetPanoramaId
                ])
            ];
        } catch (\PDOException $e) {
            error_log("Marker update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update marker.'];
        }
    }

    /**
     * Delete a marker
     */
    public function delete(int $id): array
    {
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'error' => 'You must be logged in to delete markers.'];
        }

        $marker = $this->getMarker($id);

        if (!$marker) {
            return ['success' => false, 'error' => 'Marker not found.'];
        }

        // Only allow owner to delete
        $userId = AuthController::getCurrentUserId();
        if ((int)$marker['user_id'] !== $userId) {
            return ['success' => false, 'error' => 'You can only delete your own markers.'];
        }

        try {
            // Delete associated audio file if present
            if (!empty($marker['audio_path'])) {
                $this->deleteAudioFile($marker['audio_path']);
            }
            
            Database::query("DELETE FROM markers WHERE id = ?", [$id]);
            return ['success' => true, 'message' => 'Marker deleted successfully.'];
        } catch (\PDOException $e) {
            error_log("Marker delete error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete marker.'];
        }
    }

    /**
     * Handle audio file upload
     */
    private function handleAudioUpload(array $file): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        // Validate file size (15MB max)
        $maxSize = Config::get('audio.max_size');
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Audio file size exceeds the maximum limit of 15MB.'];
        }

        // Validate MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedTypes = Config::get('audio.allowed_types');

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Only MP3, WAV, and OGG audio files are allowed.'];
        }

        // Validate extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = Config::get('audio.allowed_extensions');

        if (!in_array($extension, $allowedExtensions)) {
            return ['success' => false, 'error' => 'Invalid audio file extension.'];
        }

        // Generate unique filename
        $newFilename = md5(time() . $file['name'] . uniqid()) . '.' . $extension;
        $uploadDir = Config::get('audio.upload_dir');
        $destination = $uploadDir . $newFilename;

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save the audio file.'];
        }

        return [
            'success' => true,
            'path' => 'uploads/audio/' . $newFilename
        ];
    }

    /**
     * Delete an audio file from disk
     */
    private function deleteAudioFile(string $audioPath): void
    {
        $fullPath = __DIR__ . '/../../public/' . $audioPath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Get human-readable upload error message
     */
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
     * Copy all markers from one panorama to another
     * Keeps the original author (user_id) so attribution is preserved
     * Note: Portal markers (target_panorama_id) are NOT copied as they reference 
     * panoramas in the original user's collection
     */
    public function copyMarkers(int $sourcePanoramaId, int $targetPanoramaId, int $newUserId): bool
    {
        try {
            // Keep original user_id to preserve author attribution
            // Exclude portal markers (target_panorama_id IS NOT NULL) as they link to original user's panoramas
            $markers = Database::query(
                "SELECT user_id, yaw, pitch, type, color, label, description, audio_path 
                 FROM markers 
                 WHERE panorama_id = ? AND target_panorama_id IS NULL",
                [$sourcePanoramaId]
            )->fetchAll();

            foreach ($markers as $marker) {
                Database::query(
                    "INSERT INTO markers (panorama_id, user_id, yaw, pitch, type, color, label, description, audio_path) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $targetPanoramaId,
                        $marker['user_id'],  // Keep original author!
                        $marker['yaw'],
                        $marker['pitch'],
                        $marker['type'],
                        $marker['color'] ?? 'blue',
                        $marker['label'],
                        $marker['description'],
                        $marker['audio_path']  // Keep same audio path reference
                    ]
                );
            }

            return true;
        } catch (\PDOException $e) {
            error_log("Copy markers error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available colors
     */
    public static function getColors(): array
    {
        return self::COLORS;
    }
}
