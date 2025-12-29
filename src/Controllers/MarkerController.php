<?php

namespace App\Controllers;

use App\Database;

class MarkerController
{
    /**
     * Create a new marker for a panorama
     */
    public function create(int $panoramaId, float $yaw, float $pitch, string $label, string $description = '', string $type = 'text'): array
    {
        if (!AuthController::isLoggedIn()) {
            return ['success' => false, 'error' => 'You must be logged in to create markers.'];
        }

        $userId = AuthController::getCurrentUserId();

        // Validate inputs
        if (empty($label) || strlen($label) > 200) {
            return ['success' => false, 'error' => 'Label is required and must be less than 200 characters.'];
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

        try {
            Database::query(
                "INSERT INTO markers (panorama_id, user_id, yaw, pitch, type, label, description) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$panoramaId, $userId, $yaw, $pitch, $type, $label, $description]
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
                    'label' => $label,
                    'description' => $description
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
    public function update(int $id, string $label, string $description = '', string $type = 'text'): array
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

        try {
            Database::query(
                "UPDATE markers SET label = ?, description = ?, type = ? WHERE id = ?",
                [$label, $description, $type, $id]
            );

            return [
                'success' => true,
                'marker' => array_merge($marker, [
                    'label' => $label,
                    'description' => $description,
                    'type' => $type
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
            Database::query("DELETE FROM markers WHERE id = ?", [$id]);
            return ['success' => true, 'message' => 'Marker deleted successfully.'];
        } catch (\PDOException $e) {
            error_log("Marker delete error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete marker.'];
        }
    }

    /**
     * Copy all markers from one panorama to another
     */
    public function copyMarkers(int $sourcePanoramaId, int $targetPanoramaId, int $newUserId): bool
    {
        try {
            $markers = Database::query(
                "SELECT yaw, pitch, type, label, description FROM markers WHERE panorama_id = ?",
                [$sourcePanoramaId]
            )->fetchAll();

            foreach ($markers as $marker) {
                Database::query(
                    "INSERT INTO markers (panorama_id, user_id, yaw, pitch, type, label, description) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $targetPanoramaId,
                        $newUserId,
                        $marker['yaw'],
                        $marker['pitch'],
                        $marker['type'],
                        $marker['label'],
                        $marker['description']
                    ]
                );
            }

            return true;
        } catch (\PDOException $e) {
            error_log("Copy markers error: " . $e->getMessage());
            return false;
        }
    }
}
