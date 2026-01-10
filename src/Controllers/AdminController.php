<?php

namespace App\Controllers;

use App\Database;

class AdminController
{
    /**
     * Get overview statistics for the admin dashboard
     */
    public function getStats(): array
    {
        // Total users
        $stmt = Database::query("SELECT COUNT(*) as count FROM users");
        $totalUsers = (int)$stmt->fetch()['count'];

        // Total panoramas
        $stmt = Database::query("SELECT COUNT(*) as count FROM panoramas");
        $totalPanoramas = (int)$stmt->fetch()['count'];

        // Calculate storage used
        $storageUsed = $this->calculateStorageUsed();

        return [
            'total_users' => $totalUsers,
            'total_panoramas' => $totalPanoramas,
            'storage_used' => $storageUsed,
            'storage_formatted' => $this->formatBytes($storageUsed)
        ];
    }

    /**
     * Calculate total storage used by uploads
     */
    public function calculateStorageUsed(): int
    {
        $uploadDir = __DIR__ . '/../../public/uploads/';
        $totalSize = 0;

        if (is_dir($uploadDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploadDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                }
            }
        }

        return $totalSize;
    }

    /**
     * Format bytes to human readable format
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get all users for admin management
     */
    public function getAllUsers(): array
    {
        $stmt = Database::query(
            "SELECT u.id, u.username, u.email, u.role, u.is_banned, u.created_at,
                    COUNT(p.id) as panorama_count
             FROM users u
             LEFT JOIN panoramas p ON u.id = p.user_id
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );

        return $stmt->fetchAll();
    }

    /**
     * Get all panoramas for admin moderation
     */
    public function getAllPanoramas(?int $userId = null): array
    {
        $sql = "SELECT p.id, p.file_path, p.title, p.is_public, p.created_at,
                       u.id as user_id, u.username
                FROM panoramas p
                JOIN users u ON p.user_id = u.id";
        
        $params = [];
        
        if ($userId !== null) {
            $sql .= " WHERE p.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY p.created_at DESC";

        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Toggle user ban status
     */
    public function toggleUserBan(int $userId): array
    {
        // Can't ban yourself
        if ($userId === AuthController::getCurrentUserId()) {
            return ['success' => false, 'error' => 'You cannot ban yourself.'];
        }

        // Get current status
        $stmt = Database::query("SELECT is_banned, role FROM users WHERE id = ?", [$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        // Can't ban other admins
        if ($user['role'] === 'admin') {
            return ['success' => false, 'error' => 'You cannot ban an admin user.'];
        }

        $newStatus = $user['is_banned'] ? 0 : 1;

        try {
            Database::query(
                "UPDATE users SET is_banned = ? WHERE id = ?",
                [$newStatus, $userId]
            );

            return [
                'success' => true,
                'is_banned' => (bool)$newStatus,
                'message' => $newStatus ? 'User has been banned.' : 'User has been unbanned.'
            ];
        } catch (\PDOException $e) {
            error_log("Ban toggle error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update user status.'];
        }
    }

    /**
     * Force delete a panorama (admin action)
     * This removes the database entry AND the physical file
     */
    public function forceDeletePanorama(int $panoramaId): array
    {
        // Get panorama info
        $stmt = Database::query("SELECT file_path FROM panoramas WHERE id = ?", [$panoramaId]);
        $panorama = $stmt->fetch();

        if (!$panorama) {
            return ['success' => false, 'error' => 'Panorama not found.'];
        }

        $filePath = $panorama['file_path'];

        // Check if this file is used by other panoramas
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM panoramas WHERE file_path = ? AND id != ?",
            [$filePath, $panoramaId]
        );
        $isSharedFile = (int)$stmt->fetch()['count'] > 0;

        try {
            // Delete from database (this will cascade delete markers and votes)
            Database::query("DELETE FROM panoramas WHERE id = ?", [$panoramaId]);

            // Only delete physical file if no other panoramas use it
            if (!$isSharedFile) {
                $fullPath = __DIR__ . '/../../public/' . $filePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            return [
                'success' => true,
                'message' => 'Panorama deleted successfully.',
                'file_deleted' => !$isSharedFile
            ];
        } catch (\PDOException $e) {
            error_log("Force delete error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete panorama.'];
        }
    }

    /**
     * Find and remove orphan files (files on disk not in database)
     */
    public function cleanupOrphanFiles(): array
    {
        $uploadDir = __DIR__ . '/../../public/uploads/';
        $orphanFiles = [];
        $deletedCount = 0;
        $freedSpace = 0;

        if (!is_dir($uploadDir)) {
            return ['success' => false, 'error' => 'Upload directory not found.'];
        }

        // Get all file paths from database
        $stmt = Database::query("SELECT DISTINCT file_path FROM panoramas");
        $dbFiles = [];
        while ($row = $stmt->fetch()) {
            // Extract just the filename from the path
            $dbFiles[] = basename($row['file_path']);
        }

        // Scan upload directory
        $files = scandir($uploadDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $fullPath = $uploadDir . $file;
            if (!is_file($fullPath)) continue;

            // Check if file is in database
            if (!in_array($file, $dbFiles)) {
                $fileSize = filesize($fullPath);
                $orphanFiles[] = [
                    'name' => $file,
                    'size' => $fileSize,
                    'size_formatted' => $this->formatBytes($fileSize)
                ];

                // Delete the orphan file
                if (unlink($fullPath)) {
                    $deletedCount++;
                    $freedSpace += $fileSize;
                }
            }
        }

        return [
            'success' => true,
            'orphan_files' => $orphanFiles,
            'deleted_count' => $deletedCount,
            'freed_space' => $freedSpace,
            'freed_space_formatted' => $this->formatBytes($freedSpace)
        ];
    }

    /**
     * Promote a user to admin role
     */
    public function promoteToAdmin(int $userId): array
    {
        try {
            Database::query(
                "UPDATE users SET role = 'admin' WHERE id = ?",
                [$userId]
            );

            return ['success' => true, 'message' => 'User promoted to admin.'];
        } catch (\PDOException $e) {
            error_log("Promote admin error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to promote user.'];
        }
    }

    /**
     * Get all markers for admin moderation
     */
    public function getAllMarkers(?int $panoramaId = null): array
    {
        $sql = "SELECT m.id, m.panorama_id, m.user_id, m.yaw, m.pitch, m.type, m.color, 
                       m.label, m.description, m.audio_path, m.target_panorama_id, m.created_at,
                       u.username, p.title as panorama_title
                FROM markers m
                JOIN users u ON m.user_id = u.id
                JOIN panoramas p ON m.panorama_id = p.id";
        
        $params = [];
        
        if ($panoramaId !== null) {
            $sql .= " WHERE m.panorama_id = ?";
            $params[] = $panoramaId;
        }
        
        $sql .= " ORDER BY m.created_at DESC";

        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Force delete a marker (admin action)
     */
    public function forceDeleteMarker(int $markerId): array
    {
        // Get marker info
        $stmt = Database::query("SELECT audio_path FROM markers WHERE id = ?", [$markerId]);
        $marker = $stmt->fetch();

        if (!$marker) {
            return ['success' => false, 'error' => 'Marker not found.'];
        }

        try {
            // Delete from database
            Database::query("DELETE FROM markers WHERE id = ?", [$markerId]);

            // Delete audio file if exists
            $audioDeleted = false;
            if (!empty($marker['audio_path'])) {
                $fullPath = __DIR__ . '/../../public/' . $marker['audio_path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                    $audioDeleted = true;
                }
            }

            return [
                'success' => true,
                'message' => 'Marker deleted successfully.',
                'audio_deleted' => $audioDeleted
            ];
        } catch (\PDOException $e) {
            error_log("Force delete marker error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete marker.'];
        }
    }

    /**
     * Get marker statistics
     */
    public function getMarkerStats(): array
    {
        $stmt = Database::query("SELECT COUNT(*) as count FROM markers");
        $totalMarkers = (int)$stmt->fetch()['count'];

        $stmt = Database::query("SELECT COUNT(*) as count FROM markers WHERE audio_path IS NOT NULL AND audio_path != ''");
        $audioMarkers = (int)$stmt->fetch()['count'];

        $stmt = Database::query("SELECT COUNT(*) as count FROM markers WHERE type = 'portal'");
        $portalMarkers = (int)$stmt->fetch()['count'];

        return [
            'total_markers' => $totalMarkers,
            'audio_markers' => $audioMarkers,
            'portal_markers' => $portalMarkers
        ];
    }
}
