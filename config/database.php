<?php
require_once __DIR__ . '/../vendor/autoload.php';

class Database {
    private $connection;
    private $database = "tiara_skincare";
    
    public function __construct() {
        try {
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $this->connection = $client->selectDatabase($this->database);
        } catch (Exception $e) {
            die("Error connecting to MongoDB: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
} 