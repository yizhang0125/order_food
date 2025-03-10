<?php
require_once(__DIR__ . '/../config/Database.php');

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Create admin credentials
$username = 'admin1';
$password = '123';
$email = 'admin1@example.com';

// Generate password hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// First, clear existing admin
$clear_query = "TRUNCATE TABLE admins";
$db->exec($clear_query);

// Insert new admin
$query = "INSERT INTO admins (username, password, email) VALUES (:username, :password, :email)";
$stmt = $db->prepare($query);

$stmt->bindParam(":username", $username);
$stmt->bindParam(":password", $hashed_password);
$stmt->bindParam(":email", $email);

if($stmt->execute()) {
    echo "Admin created successfully!<br>";
    echo "Username: " . $username . "<br>";
    echo "Password: " . $password . "<br>";
    echo "Generated Hash: " . $hashed_password . "<br>";
} else {
    echo "Failed to create admin";
}
?> 