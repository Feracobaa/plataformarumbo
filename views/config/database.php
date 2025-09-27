<?php

// config/database.php
class Database
{
    private $host = "localhost";
    private $db_name = "examenes_db";
    private $username = "root";
    private $password = "123456789";
    private $conn;

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            $this->conn->set_charset("utf8");

            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Error de conexión a la base de datos. Contacte al administrador.");
        }

        return $this->conn;
    }

    public function closeConnection()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Function to get database connection (for backward compatibility)
function getDatabaseConnection()
{
    $database = new Database();
    return $database->getConnection();
}
