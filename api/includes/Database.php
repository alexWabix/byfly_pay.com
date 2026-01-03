<?php
/**
 * Database Connection Class
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        $this->query($sql, $values);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        $values = [];

        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
            $values[] = $value;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set),
            $where
        );

        $values = array_merge($values, $whereParams);
        return $this->query($sql, $values)->rowCount();
    }

    public function delete($table, $where, $whereParams = []) {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        return $this->query($sql, $whereParams)->rowCount();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
