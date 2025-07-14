<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Create tasks table if it doesn't exist
$query = "CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $db->exec($query);
} catch(PDOException $e) {
    // If MySQL is not available, fall back to SQLite
    $database = new Database(true);
    $db = $database->getConnection();
    
    $query = "CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'pending',
        priority TEXT DEFAULT 'medium',
        due_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($query);
}

// Get request method and parse URL
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/api/tasks.php', '', $path);
$segments = explode('/', trim($path, '/'));

// Route requests
switch ($method) {
    case 'GET':
        handleGet($db, $segments);
        break;
    case 'POST':
        handlePost($db);
        break;
    case 'PUT':
        handlePut($db, $segments);
        break;
    case 'DELETE':
        handleDelete($db, $segments);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($db, $segments) {
    if (!empty($segments[0]) && is_numeric($segments[0])) {
        // Get single task
        $task = getTaskById($db, $segments[0]);
        if ($task) {
            echo json_encode(['success' => true, 'data' => $task]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
        }
    } else {
        // Get all tasks with optional filtering
        $filter = $_GET['filter'] ?? 'all';
        $tasks = getTasks($db, $filter);
        $stats = getTaskStats($db);
        
        echo json_encode([
            'success' => true,
            'data' => $tasks,
            'stats' => $stats,
            'total' => count($tasks)
        ]);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate and sanitize input
    $sanitized = sanitizeInput($input);
    $errors = validateTaskData($sanitized);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
        return;
    }
    
    $result = addTask($db, $sanitized);
    
    if ($result['success']) {
        http_response_code(201);
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}

function handlePut($db, $segments) {
    if (empty($segments[0]) || !is_numeric($segments[0])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $input['id'] = $segments[0];
    
    // Validate and sanitize input
    $sanitized = sanitizeInput($input);
    $errors = validateTaskData($sanitized);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
        return;
    }
    
    $result = updateTask($db, $sanitized);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}

function handleDelete($db, $segments) {
    if (empty($segments[0]) || !is_numeric($segments[0])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        return;
    }
    
    $result = deleteTask($db, $segments[0]);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}

// Additional utility endpoints
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'stats':
            echo json_encode(['success' => true, 'data' => getTaskStats($db)]);
            break;
            
        case 'export':
            $tasks = getTasks($db, 'all');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="tasks_export.json"');
            echo json_encode($tasks, JSON_PRETTY_PRINT);
            break;
            
        case 'bulk-update':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $results = [];
                
                if (isset($input['tasks']) && is_array($input['tasks'])) {
                    foreach ($input['tasks'] as $taskData) {
                        if (isset($taskData['id'])) {
                            $sanitized = sanitizeInput($taskData);
                            $result = updateTask($db, $sanitized);
                            $results[] = ['id' => $taskData['id'], 'result' => $result];
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'results' => $results]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            break;
    }
}
?>