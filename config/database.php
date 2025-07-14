<?php
class Database {
    private $host = "localhost";
    private $db_name = "task_management";
    private $username = "root";
    private $password = "";
    private $conn;
    private $use_sqlite = false;

    public function __construct($force_sqlite = false) {
        $this->use_sqlite = $force_sqlite;
    }

    public function getConnection() {
        $this->conn = null;

        if ($this->use_sqlite) {
            try {
                // Create data directory if it doesn't exist
                $dataDir = __DIR__ . '/../data';
                if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0755, true);
                }
                
                $this->conn = new PDO("sqlite:" . $dataDir . "/tasks.db");
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $exception) {
                echo "Connection error: " . $exception->getMessage();
            }
        } else {
            try {
                $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                    $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $exception) {
                // Fall back to SQLite if MySQL is not available
                try {
                    $dataDir = __DIR__ . '/../data';
                    if (!is_dir($dataDir)) {
                        mkdir($dataDir, 0755, true);
                    }
                    
                    $this->conn = new PDO("sqlite:" . $dataDir . "/tasks.db");
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->use_sqlite = true;
                } catch(PDOException $sqlite_exception) {
                    echo "Connection error: " . $sqlite_exception->getMessage();
                }
            }
        }

        return $this->conn;
    }

    public function isSqlite() {
        return $this->use_sqlite;
    }
}
?>