<?php

function getTasks($db, $filter = 'all') {
    $query = "SELECT * FROM tasks";
    $params = [];
    
    if ($filter !== 'all') {
        $query .= " WHERE status = :status";
        $params[':status'] = $filter;
    }
    
    $query .= " ORDER BY 
        CASE priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        due_date ASC,
        created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTaskById($db, $id) {
    $query = "SELECT * FROM tasks WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addTask($db, $data) {
    try {
        $query = "INSERT INTO tasks (title, description, status, priority, due_date) 
                  VALUES (:title, :description, :status, :priority, :due_date)";
        
        $stmt = $db->prepare($query);
        $title = $data['title'];
        $description = $data['description'];
        $status = $data['status'];
        $priority = $data['priority'];
        $due_date = $data['due_date'] ?: null;
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':due_date', $due_date);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Task added successfully',
                'id' => $db->lastInsertId()
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => 'Error adding task: ' . $e->getMessage()
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to add task'];
}

function updateTask($db, $data) {
    try {
        $query = "UPDATE tasks 
                  SET title = :title, description = :description, status = :status, 
                      priority = :priority, due_date = :due_date, updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $id = $data['id'];
        $title = $data['title'];
        $description = $data['description'];
        $status = $data['status'];
        $priority = $data['priority'];
        $due_date = $data['due_date'] ?: null;
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':due_date', $due_date);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Task updated successfully'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => 'Error updating task: ' . $e->getMessage()
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to update task'];
}

function deleteTask($db, $id) {
    try {
        $query = "DELETE FROM tasks WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Task deleted successfully'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => 'Error deleting task: ' . $e->getMessage()
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to delete task'];
}

function toggleTaskStatus($db, $id) {
    try {
        // First get current status
        $task = getTaskById($db, $id);
        if (!$task) {
            return ['success' => false, 'message' => 'Task not found'];
        }
        
        $newStatus = ($task['status'] === 'completed') ? 'pending' : 'completed';
        
        $query = "UPDATE tasks SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Task status updated successfully',
                'new_status' => $newStatus
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => 'Error updating task status: ' . $e->getMessage()
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to update task status'];
}

function validateTaskData($data) {
    $errors = [];
    
    if (empty($data['title'])) {
        $errors[] = 'Title is required';
    }
    
    if (!in_array($data['status'], ['pending', 'in_progress', 'completed'])) {
        $errors[] = 'Invalid status';
    }
    
    if (!in_array($data['priority'], ['low', 'medium', 'high'])) {
        $errors[] = 'Invalid priority';
    }
    
    if (!empty($data['due_date']) && !strtotime($data['due_date'])) {
        $errors[] = 'Invalid due date format';
    }
    
    return $errors;
}

function sanitizeInput($data) {
    return [
        'title' => trim(strip_tags($data['title'] ?? '')),
        'description' => trim(strip_tags($data['description'] ?? '')),
        'status' => $data['status'] ?? 'pending',
        'priority' => $data['priority'] ?? 'medium',
        'due_date' => $data['due_date'] ?? null
    ];
}

function getTaskStats($db) {
    $stats = [
        'total' => 0,
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'overdue' => 0
    ];
    
    try {
        // Total tasks
        $query = "SELECT COUNT(*) as total FROM tasks";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Tasks by status
        $query = "SELECT status, COUNT(*) as count FROM tasks GROUP BY status";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            $stats[$result['status']] = $result['count'];
        }
        
        // Overdue tasks
        $query = "SELECT COUNT(*) as overdue FROM tasks 
                  WHERE due_date < CURRENT_DATE AND status != 'completed'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['overdue'] = $stmt->fetch(PDO::FETCH_ASSOC)['overdue'];
        
    } catch (PDOException $e) {
        // Return default stats on error
    }
    
    return $stats;
}

?>