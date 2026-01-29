<?php
// SET GLOBAL TIMEZONE TO INDIA
date_default_timezone_set('Asia/Kolkata');

class Database
{
    private static $instance = null;
    private $pdo;

    // Database Configuration
    private $host = '127.0.0.1';
    private $db   = 'library_db';
    private $user = 'root';
    private $pass = ''; // Default XAMPP password is empty
    private $charset = 'utf8mb4';

    // Private constructor prevents direct object creation
    private function __construct()
    {
        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";

        $options = [
            // Throw exceptions on errors (essential for debugging)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return arrays indexed by column name
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use native prepared statements (Security)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];


        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            $this->pdo->exec("SET time_zone = '+05:30'");
        } catch (\PDOException $e) {
            // In production, log this error instead of showing it
            throw new \PDOException("Database Connection Failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    // The Access Point (Singleton Pattern)
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Return the actual PDO connection object
    public function getConnection()
    {
        return $this->pdo;
    }
}
