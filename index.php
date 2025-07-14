<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'add':
            $result = addTask($db, $_POST);
            echo json_encode($result);
            exit;
            
        case 'update':
            $result = updateTask($db, $_POST);
            echo json_encode($result);
            exit;
            
        case 'delete':
            $result = deleteTask($db, $_POST['id']);
            echo json_encode($result);
            exit;
            
        case 'toggle':
            $result = toggleTaskStatus($db, $_POST['id']);
            echo json_encode($result);
            exit;
    }
}

// Get tasks
$tasks = getTasks($db, $_GET['filter'] ?? 'all');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management System</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-tasks"></i> Task Management</h1>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Task
            </button>
        </header>

        <div class="filters">
            <button class="filter-btn <?= (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'active' : '' ?>" 
                    onclick="filterTasks('all')">All Tasks</button>
            <button class="filter-btn <?= (isset($_GET['filter']) && $_GET['filter'] === 'pending') ? 'active' : '' ?>" 
                    onclick="filterTasks('pending')">Pending</button>
            <button class="filter-btn <?= (isset($_GET['filter']) && $_GET['filter'] === 'in_progress') ? 'active' : '' ?>" 
                    onclick="filterTasks('in_progress')">In Progress</button>
            <button class="filter-btn <?= (isset($_GET['filter']) && $_GET['filter'] === 'completed') ? 'active' : '' ?>" 
                    onclick="filterTasks('completed')">Completed</button>
        </div>

        <div class="tasks-container">
            <?php if (empty($tasks)): ?>
                <div class="no-tasks">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No tasks found</h3>
                    <p>Start by adding your first task!</p>
                </div>
            <?php else: ?>
                <div class="tasks-grid">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card <?= $task['status'] ?> priority-<?= $task['priority'] ?>" data-id="<?= $task['id'] ?>">
                            <div class="task-header">
                                <h3><?= htmlspecialchars($task['title']) ?></h3>
                                <div class="task-actions">
                                    <button class="btn-icon" onclick="editTask(<?= $task['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon" onclick="deleteTask(<?= $task['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (!empty($task['description'])): ?>
                                <p class="task-description"><?= htmlspecialchars($task['description']) ?></p>
                            <?php endif; ?>
                            
                            <div class="task-meta">
                                <span class="priority-badge priority-<?= $task['priority'] ?>">
                                    <?= ucfirst($task['priority']) ?>
                                </span>
                                <span class="status-badge status-<?= $task['status'] ?>">
                                    <?= str_replace('_', ' ', ucfirst($task['status'])) ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($task['due_date'])): ?>
                                <div class="due-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="task-footer">
                                <button class="btn btn-sm <?= $task['status'] === 'completed' ? 'btn-warning' : 'btn-success' ?>" 
                                        onclick="toggleStatus(<?= $task['id'] ?>)">
                                    <?= $task['status'] === 'completed' ? 'Mark Incomplete' : 'Mark Complete' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Task</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="taskForm">
                <input type="hidden" id="taskId" name="id">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>