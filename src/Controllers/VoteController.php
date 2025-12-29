<?php

namespace App\Controllers;

use App\Database;

class VoteController
{
    /**
     * Get current user's vote for a panorama
     * Returns: 1 (upvote), -1 (downvote), or 0 (no vote)
     */
    public function getUserVote(int $panoramaId, int $userId): int
    {
        $stmt = Database::query(
            "SELECT value FROM votes WHERE panorama_id = ? AND user_id = ?",
            [$panoramaId, $userId]
        );
        
        $result = $stmt->fetch();
        return $result ? (int)$result['value'] : 0;
    }

    /**
     * Get total vote score for a panorama
     */
    public function getVoteScore(int $panoramaId): int
    {
        $stmt = Database::query(
            "SELECT COALESCE(SUM(value), 0) as score FROM votes WHERE panorama_id = ?",
            [$panoramaId]
        );
        
        $result = $stmt->fetch();
        return (int)($result['score'] ?? 0);
    }

    /**
     * Cast or update a vote
     * Returns the new total score and user's current vote
     */
    public function vote(int $panoramaId, int $userId, int $value): array
    {
        // Validate value
        if (!in_array($value, [-1, 0, 1])) {
            return ['success' => false, 'error' => 'Invalid vote value'];
        }

        // Check if panorama exists and is public
        $stmt = Database::query(
            "SELECT id, is_public, user_id FROM panoramas WHERE id = ?",
            [$panoramaId]
        );
        $panorama = $stmt->fetch();

        if (!$panorama) {
            return ['success' => false, 'error' => 'Panorama not found'];
        }

        if (!$panorama['is_public']) {
            return ['success' => false, 'error' => 'Cannot vote on private panoramas'];
        }

        // Prevent voting on own panorama
        if ((int)$panorama['user_id'] === $userId) {
            return ['success' => false, 'error' => 'Cannot vote on your own panorama'];
        }

        try {
            // Get current vote
            $currentVote = $this->getUserVote($panoramaId, $userId);

            if ($value === 0) {
                // Remove vote
                Database::query(
                    "DELETE FROM votes WHERE panorama_id = ? AND user_id = ?",
                    [$panoramaId, $userId]
                );
            } else if ($currentVote === 0) {
                // Insert new vote
                Database::query(
                    "INSERT INTO votes (panorama_id, user_id, value) VALUES (?, ?, ?)",
                    [$panoramaId, $userId, $value]
                );
            } else {
                // Update existing vote
                Database::query(
                    "UPDATE votes SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE panorama_id = ? AND user_id = ?",
                    [$value, $panoramaId, $userId]
                );
            }

            // Get new totals
            $newScore = $this->getVoteScore($panoramaId);
            $newUserVote = $value === 0 ? 0 : $value;

            return [
                'success' => true,
                'score' => $newScore,
                'userVote' => $newUserVote
            ];
        } catch (\PDOException $e) {
            error_log("Vote error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save vote'];
        }
    }

    /**
     * Handle vote toggle logic
     * If clicking same vote type, remove vote
     * If clicking different vote type, switch
     */
    public function toggleVote(int $panoramaId, int $userId, int $intendedVote): array
    {
        $currentVote = $this->getUserVote($panoramaId, $userId);

        if ($currentVote === $intendedVote) {
            // Same vote - remove it (toggle off)
            return $this->vote($panoramaId, $userId, 0);
        } else {
            // Different vote or no vote - apply the intended vote
            return $this->vote($panoramaId, $userId, $intendedVote);
        }
    }
}
