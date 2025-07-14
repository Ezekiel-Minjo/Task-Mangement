<?php
echo "🚀 Task Management System - Deployment Script\n";
echo "============================================\n\n";

// Check PHP version
$phpVersion = phpversion();
echo "✓ PHP Version: $phpVersion\n";

if (version_compare($phpVersion, '7.4.0', '<')) {
    echo "❌ Warning: PHP 7.4+ recommended for best compatibility\n";
}

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_sqlite'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ Extension $ext: Available\n";
    } else {
        echo "❌ Extension $ext: Missing\n";
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "\n⚠️ Missing extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "Please install them before deploying.\n\n";
}

// Create data directory with proper permissions
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
    echo "✓ Created data directory\n";
} else {
    echo "✓ Data directory exists\n";
}

// Check data directory permissions
if (is_writable($dataDir)) {
    echo "✓ Data directory is writable\n";
} else {
    echo "❌ Data directory is not writable\n";
    echo "Run: chmod 755 data/\n";
}

// Initialize database
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✓ Database connection successful\n";
        
        // Create tables
        if ($database->isSqlite()) {
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
        } else {
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
        }
        
        $db->exec($query);
        echo "✓ Database tables created\n";
        
    } else {
        echo "❌ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Check if assets exist
$assets = [
    'assets/css/style.css' => 'CSS file',
    'assets/js/script.js' => 'JavaScript file',
    'index.php' => 'Main application',
    'api/tasks.php' => 'API endpoint'
];

foreach ($assets as $file => $description) {
    if (file_exists($file)) {
        echo "✓ $description: Found\n";
    } else {
        echo "❌ $description: Missing\n";
    }
}

echo "\n📋 Deployment Summary:\n";
echo "=====================\n";

if (empty($missingExtensions) && is_writable($dataDir)) {
    echo "🎉 Ready for deployment!\n\n";
    
    echo "Next steps:\n";
    echo "1. Upload all files to your web server\n";
    echo "2. Ensure data/ directory has write permissions\n";
    echo "3. Configure your domain to point to the project root\n";
    echo "4. Test the application in your browser\n\n";
    
    echo "Optional:\n";
    echo "- Set up SSL certificate for HTTPS\n";
    echo "- Configure custom domain\n";
    echo "- Set up database backups\n";
} else {
    echo "⚠️ Please fix the issues above before deploying.\n";
}

echo "\n🌐 Access your deployed app at: https://yourdomain.com\n";
?>