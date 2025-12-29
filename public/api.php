<?php
/**
 * API Router for handling AJAX requests
 * Endpoints:
 *   POST /api.php?action=marker/create
 *   GET  /api.php?action=marker/list&panorama_id=X
 *   POST /api.php?action=marker/update
 *   POST /api.php?action=marker/delete
 *   POST /api.php?action=panorama/fork
 *   POST /api.php?action=vote/toggle
 *   GET  /api.php?action=vote/status&panorama_id=X
 *   GET  /api.php?action=panorama/export&panorama_id=X
 */

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\MarkerController;
use App\Controllers\PanoramaController;
use App\Controllers\VoteController;

// Set JSON response header
header('Content-Type: application/json');

// Initialize controllers
$markerController = new MarkerController();
$panoramaController = new PanoramaController();
$voteController = new VoteController();

// Get action from query string
$action = $_GET['action'] ?? '';

// Parse JSON body for POST requests
$inputData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $inputData = json_decode($rawInput, true) ?? [];
    }
    // Also merge with POST data for form submissions
    $inputData = array_merge($_POST, $inputData);
}

try {
    switch ($action) {
        // ========== MARKER ENDPOINTS ==========
        
        case 'marker/create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $panoramaId = (int)($inputData['panorama_id'] ?? 0);
            $yaw = (float)($inputData['yaw'] ?? 0);
            $pitch = (float)($inputData['pitch'] ?? 0);
            $label = trim($inputData['label'] ?? '');
            $description = trim($inputData['description'] ?? '');
            $type = trim($inputData['type'] ?? 'text');
            $color = trim($inputData['color'] ?? 'blue');
            
            // Get audio file if uploaded (supports multipart/form-data)
            $audioFile = isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE 
                         ? $_FILES['audio_file'] 
                         : null;
            
            $result = $markerController->create($panoramaId, $yaw, $pitch, $label, $description, $type, $color, $audioFile);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
            
        case 'marker/list':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $panoramaId = (int)($_GET['panorama_id'] ?? 0);
            
            if ($panoramaId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid panorama ID']);
                exit;
            }
            
            $markers = $markerController->getByPanorama($panoramaId);
            echo json_encode(['success' => true, 'markers' => $markers]);
            break;
            
        case 'marker/get':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $markerId = (int)($_GET['id'] ?? 0);
            
            if ($markerId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid marker ID']);
                exit;
            }
            
            $marker = $markerController->getMarker($markerId);
            if ($marker) {
                echo json_encode(['success' => true, 'marker' => $marker]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Marker not found']);
            }
            break;
            
        case 'marker/update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $markerId = (int)($inputData['id'] ?? 0);
            $label = trim($inputData['label'] ?? '');
            $description = trim($inputData['description'] ?? '');
            $type = trim($inputData['type'] ?? 'text');
            $color = trim($inputData['color'] ?? 'blue');
            $removeAudio = filter_var($inputData['remove_audio'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Get audio file if uploaded (supports multipart/form-data)
            $audioFile = isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE 
                         ? $_FILES['audio_file'] 
                         : null;
            
            $result = $markerController->update($markerId, $label, $description, $type, $color, $audioFile, $removeAudio);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
            
        case 'marker/delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $markerId = (int)($inputData['id'] ?? 0);
            
            $result = $markerController->delete($markerId);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
            
        // ========== PANORAMA ENDPOINTS ==========
        
        case 'panorama/fork':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            if (!AuthController::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'You must be logged in to save to your collection.']);
                exit;
            }
            
            $sourceId = (int)($inputData['panorama_id'] ?? 0);
            $userId = AuthController::getCurrentUserId();
            
            if ($sourceId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid panorama ID']);
                exit;
            }
            
            $result = $panoramaController->forkPanorama($sourceId, $userId);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
        
        // ========== VOTE ENDPOINTS ==========
        
        case 'vote/toggle':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            if (!AuthController::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'You must be logged in to vote.']);
                exit;
            }
            
            $panoramaId = (int)($inputData['panorama_id'] ?? 0);
            $voteValue = (int)($inputData['value'] ?? 0);
            $userId = AuthController::getCurrentUserId();
            
            if ($panoramaId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid panorama ID']);
                exit;
            }
            
            if (!in_array($voteValue, [-1, 1])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid vote value']);
                exit;
            }
            
            $result = $voteController->toggleVote($panoramaId, $userId, $voteValue);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
        
        case 'vote/status':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $panoramaId = (int)($_GET['panorama_id'] ?? 0);
            
            if ($panoramaId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid panorama ID']);
                exit;
            }
            
            $score = $voteController->getVoteScore($panoramaId);
            $userVote = 0;
            
            if (AuthController::isLoggedIn()) {
                $userVote = $voteController->getUserVote($panoramaId, AuthController::getCurrentUserId());
            }
            
            echo json_encode([
                'success' => true,
                'score' => $score,
                'userVote' => $userVote
            ]);
            break;
        
        case 'panorama/export':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            if (!AuthController::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'You must be logged in to export data.']);
                exit;
            }
            
            $panoramaId = (int)($_GET['panorama_id'] ?? 0);
            $userId = AuthController::getCurrentUserId();
            
            if ($panoramaId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid panorama ID']);
                exit;
            }
            
            // Get panorama
            $panorama = $panoramaController->getPanorama($panoramaId);
            
            if (!$panorama) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Panorama not found']);
                exit;
            }
            
            // Only owner can export
            if ((int)$panorama['user_id'] !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'You can only export your own panoramas']);
                exit;
            }
            
            // Get markers
            $markers = $markerController->getByPanorama($panoramaId);
            
            // Get vote score
            $voteScore = $voteController->getVoteScore($panoramaId);
            
            // Build export data
            $exportData = [
                'exported_at' => date('c'),
                'panorama' => [
                    'id' => (int)$panorama['id'],
                    'title' => $panorama['title'],
                    'description' => $panorama['description'],
                    'file_path' => $panorama['file_path'],
                    'is_public' => (bool)$panorama['is_public'],
                    'created_at' => $panorama['created_at'],
                    'vote_score' => $voteScore
                ],
                'markers' => array_map(function($m) {
                    return [
                        'id' => (int)$m['id'],
                        'yaw' => (float)$m['yaw'],
                        'pitch' => (float)$m['pitch'],
                        'label' => $m['label'],
                        'description' => $m['description'],
                        'type' => $m['type'],
                        'color' => $m['color'],
                        'created_at' => $m['created_at']
                    ];
                }, $markers)
            ];
            
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Unknown API action']);
            break;
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
