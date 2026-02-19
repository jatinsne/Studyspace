<?php
// SET GLOBAL TIMEZONE TO INDIA
date_default_timezone_set('Asia/Kolkata');

// -------------------------------------------------------------
// ENV LOADER HELPER
// -------------------------------------------------------------
if (!function_exists('loadEnv')) {
    function loadEnv($path)
    {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // Skip comments
            if (strpos($line, '=') === false) continue;   // Skip invalid lines

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, "\"'"); // Remove quotes

            // Set Environment Variables
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

class Database
{
    private static $instance = null;
    private $pdo;

    // Configuration fetched from ENV with fallbacks
    private $host;
    private $db;
    private $user;
    private $pass;
    private $charset = 'utf8mb4';

    // Private constructor prevents direct object creation
    private function __construct()
    {

        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->db   = getenv('DB_NAME') ?: 'library_db';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->pass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];


        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            $this->pdo->exec("SET time_zone = '+05:30'");
        } catch (\PDOException $e) {
            throw new \PDOException("Database Connection Failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}
