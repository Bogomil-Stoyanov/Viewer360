<?php
/**
 * API Router for handling AJAX requests
 * Endpoints:
 *   POST /api.php?action=marker/create
 *   GET  /api.php?action=marker/list&panorama_id=X
 *   POST /api.php?action=marker/update
 *   POST /api.php?action=marker/delete
 *   POST /api.php?action=panorama/fork
 */

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\MarkerController;
use App\Controllers\PanoramaController;

// Set JSON response header
header('Content-Type: application/json');

// Initialize controllers
$markerController = new MarkerController();
$panoramaController = new PanoramaController();

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
            
            $result = $markerController->create($panoramaId, $yaw, $pitch, $label, $description, $type, $color);
            
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
            
            $result = $markerController->update($markerId, $label, $description, $type, $color);
            
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
