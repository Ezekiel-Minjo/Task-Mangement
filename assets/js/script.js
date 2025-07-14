// Global variables
let currentEditId = null;

// DOM loaded event
document.addEventListener('DOMContentLoaded', function() {
    // Initialize event listeners
    setupEventListeners();
    
    // Set today's date as default for due date
    const dueDateInput = document.getElementById('due_date');
    if (dueDateInput) {
        const today = new Date().toISOString().split('T')[0];
        dueDateInput.setAttribute('min', today);
    }
});

function setupEventListeners() {
    // Task form submission
    const taskForm = document.getElementById('taskForm');
    if (taskForm) {
        taskForm.addEventListener('submit', handleFormSubmit);
    }
    
    // Modal close events
    const modal = document.getElementById('taskModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }
    
    // Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

// Modal functions
function openAddModal() {
    currentEditId = null;
    document.getElementById('modalTitle').textContent = 'Add New Task';
    document.getElementById('taskForm').reset();
    document.getElementById('taskId').value = '';
    document.getElementById('taskModal').style.display = 'block';
    
    // Focus on title input
    setTimeout(() => {
        document.getElementById('title').focus();
    }, 100);
}

function closeModal() {
    document.getElementById('taskModal').style.display = 'none';
    currentEditId = null;
}

// Task operations
function editTask(id) {
    currentEditId = id;
    document.getElementById('modalTitle').textContent = 'Edit Task';
    
    // Get task data from the card
    const taskCard = document.querySelector(`[data-id="${id}"]`);
    if (!taskCard) return;
    
    const title = taskCard.querySelector('h3').textContent;
    const description = taskCard.querySelector('.task-description')?.textContent || '';
    const priorityBadge = taskCard.querySelector('.priority-badge');
    const statusBadge = taskCard.querySelector('.status-badge');
    const dueDateElement = taskCard.querySelector('.due-date');
    
    // Extract priority and status from classes
    const priority = priorityBadge ? priorityBadge.className.match(/priority-(\w+)/)?.[1] || 'medium' : 'medium';
    const status = statusBadge ? statusBadge.className.match(/status-(\w+)/)?.[1] || 'pending' : 'pending';
    
    // Extract due date
    let dueDate = '';
    if (dueDateElement) {
        const dateText = dueDateElement.textContent.trim();
        const dateMatch = dateText.match(/(\w{3} \d{1,2}, \d{4})/);
        if (dateMatch) {
            const parsedDate = new Date(dateMatch[1]);
            dueDate = parsedDate.toISOString().split('T')[0];
        }
    }
    
    // Populate form
    document.getElementById('taskId').value = id;
    document.getElementById('title').value = title;
    document.getElementById('description').value = description;
    document.getElementById('status').value = status;
    document.getElementById('priority').value = priority;
    document.getElementById('due_date').value = dueDate;
    
    // Show modal
    document.getElementById('taskModal').style.display = 'block';
    
    // Focus on title input
    setTimeout(() => {
        document.getElementById('title').focus();
    }, 100);
}

function deleteTask(id) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('?action=delete', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Task deleted successfully!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error deleting task', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error deleting task', 'error');
    });
}

function toggleStatus(id) {
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('?action=toggle', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Task status updated!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error updating task status', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating task status', 'error');
    });
}

// Form handling
function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    
    // Validate form
    if (!validateForm(formData)) {
        return;
    }
    
    const action = currentEditId ? 'update' : 'add';
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> Saving...';
    
    fetch(`?action=${action}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal();
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error saving task', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving task', 'error');
    })
    .finally(() => {
        // Restore button state
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    });
}

function validateForm(formData) {
    const title = formData.get('title');
    const dueDate = formData.get('due_date');
    
    if (!title || title.trim() === '') {
        showNotification('Title is required', 'error');
        document.getElementById('title').focus();
        return false;
    }
    
    if (dueDate && new Date(dueDate) < new Date().setHours(0, 0, 0, 0)) {
        showNotification('Due date cannot be in the past', 'error');
        document.getElementById('due_date').focus();
        return false;
    }
    
    return true;
}

// Filter functions
function filterTasks(filter) {
    const url = new URL(window.location);
    if (filter === 'all') {
        url.searchParams.delete('filter');
    } else {
        url.searchParams.set('filter', filter);
    }
    window.location.href = url.toString();
}

// Notification system
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Utility functions
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function isOverdue(dueDate) {
    const today = new Date().setHours(0, 0, 0, 0);
    const due = new Date(dueDate).setHours(0, 0, 0, 0);
    return due < today;
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N to add new task
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        openAddModal();
    }
    
    // Ctrl/Cmd + K for quick filter
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const filters = document.querySelectorAll('.filter-btn');
        if (filters.length > 0) {
            filters[0].focus();
        }
    }
});

// Auto-save draft functionality (optional enhancement)
let draftTimer;
function saveDraft() {
    const form = document.getElementById('taskForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const draft = {
        title: formData.get('title'),
        description: formData.get('description'),
        status: formData.get('status'),
        priority: formData.get('priority'),
        due_date: formData.get('due_date')
    };
    
    localStorage.setItem('taskDraft', JSON.stringify(draft));
}

function loadDraft() {
    const draft = localStorage.getItem('taskDraft');
    if (!draft) return;
    
    try {
        const draftData = JSON.parse(draft);
        if (draftData.title) {
            document.getElementById('title').value = draftData.title || '';
            document.getElementById('description').value = draftData.description || '';
            document.getElementById('status').value = draftData.status || 'pending';
            document.getElementById('priority').value = draftData.priority || 'medium';
            document.getElementById('due_date').value = draftData.due_date || '';
        }
    } catch (e) {
        console.error('Error loading draft:', e);
    }
}

function clearDraft() {
    localStorage.removeItem('taskDraft');
}

// Enhanced modal with draft support
const originalOpenAddModal = openAddModal;
openAddModal = function() {
    originalOpenAddModal();
    loadDraft();
    
    // Setup auto-save
    const inputs = document.querySelectorAll('#taskForm input, #taskForm textarea, #taskForm select');
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            clearTimeout(draftTimer);
            draftTimer = setTimeout(saveDraft, 1000);
        });
    });
};

const originalCloseModal = closeModal;
closeModal = function() {
    clearDraft();
    originalCloseModal();
};

// Search functionality (if implemented)
function searchTasks(query) {
    const tasks = document.querySelectorAll('.task-card');
    const searchTerm = query.toLowerCase();
    
    tasks.forEach(task => {
        const title = task.querySelector('h3').textContent.toLowerCase();
        const description = task.querySelector('.task-description')?.textContent.toLowerCase() || '';
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            task.style.display = 'block';
        } else {
            task.style.display = 'none';
        }
    });
}

// Export tasks (future enhancement)
function exportTasks() {
    const tasks = Array.from(document.querySelectorAll('.task-card')).map(card => {
        return {
            title: card.querySelector('h3').textContent,
            description: card.querySelector('.task-description')?.textContent || '',
            priority: card.className.match(/priority-(\w+)/)?.[1] || 'medium',
            status: card.className.match(/status-(\w+)/)?.[1] || 'pending',
            due_date: card.querySelector('.due-date')?.textContent.trim() || ''
        };
    });
    
    const dataStr = JSON.stringify(tasks, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(dataBlob);
    link.download = 'tasks.json';
    link.click();
}