<?php

namespace App\Controllers;

use App\Database;

class VoteController
{
    public function getUserVote(int $panoramaId, int $userId): int
    {
        $stmt = Database::query(
            "SELECT value FROM votes WHERE panorama_id = ? AND user_id = ?",
            [$panoramaId, $userId]
        );
        
        $result = $stmt->fetch();
        return $result ? (int)$result['value'] : 0;
    }

    public function getVoteScore(int $panoramaId): int
    {
        $stmt = Database::query(
            "SELECT COALESCE(SUM(value), 0) as score FROM votes WHERE panorama_id = ?",
            [$panoramaId]
        );
        
        $result = $stmt->fetch();
        return (int)($result['score'] ?? 0);
    }

    public function vote(int $panoramaId, int $userId, int $value): array
    {
        if (!in_array($value, [-1, 0, 1])) {
            return ['success' => false, 'error' => 'Invalid vote value'];
        }

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

        if ((int)$panorama['user_id'] === $userId) {
            return ['success' => false, 'error' => 'Cannot vote on your own panorama'];
        }

        try {
            $currentVote = $this->getUserVote($panoramaId, $userId);

            if ($value === 0) {
                Database::query(
                    "DELETE FROM votes WHERE panorama_id = ? AND user_id = ?",
                    [$panoramaId, $userId]
                );
            } else if ($currentVote === 0) {
                Database::query(
                    "INSERT INTO votes (panorama_id, user_id, value) VALUES (?, ?, ?)",
                    [$panoramaId, $userId, $value]
                );
            } else {
                Database::query(
                    "UPDATE votes SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE panorama_id = ? AND user_id = ?",
                    [$value, $panoramaId, $userId]
                );
            }

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

    public function toggleVote(int $panoramaId, int $userId, int $intendedVote): array
    {
        $currentVote = $this->getUserVote($panoramaId, $userId);

        if ($currentVote === $intendedVote) {
            return $this->vote($panoramaId, $userId, 0);
        } else {
            return $this->vote($panoramaId, $userId, $intendedVote);
        }
    }
}
