<?php
/**
 * Database Initialization Script
 * Run this file once to setup the database
 */

require_once __DIR__ . '/config.php';

echo "ByFly Payment Center - Database Initialization\n";
echo "==============================================\n\n";

try {
    // Connect to MySQL without database selection
    $dsn = sprintf("mysql:host=%s;port=%d;charset=%s", DB_HOST, DB_PORT, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to MySQL server.\n";

    // Read and execute schema
    $schemaFile = __DIR__ . '/../database/schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    $sql = file_get_contents($schemaFile);
    
    echo "Executing database schema...\n";
    
    // Execute each statement separately
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   strpos($stmt, '--') !== 0 && 
                   strpos($stmt, '/*') !== 0;
        }
    );

    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Continue on duplicate or minor errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n✓ Database initialized successfully!\n\n";
    echo "Default admin phone: +77780021666\n";
    echo "Login via SMS code at: https://byfly-pay.com/\n\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
