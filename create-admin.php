<?php
require_once 'config/database.php';

$db = new Database();
$collection = $db->getConnection();

// Cek apakah sudah ada admin
$admin = $collection->users->findOne(['role' => 'admin']);

if ($admin) {
    die('Admin sudah ada!');
}

// Data admin default
$admin_data = [
    'name' => 'Administrator',
    'email' => 'admin@admin.com',
    'password' => password_hash('admin123', PASSWORD_DEFAULT),
    'role' => 'admin',
    'status' => 'active',
    'created_at' => new MongoDB\BSON\UTCDateTime()
];

try {
    $result = $collection->users->insertOne($admin_data);
    if ($result->getInsertedCount()) {
        echo 'Admin berhasil dibuat!<br>';
        echo 'Email: admin@admin.com<br>';
        echo 'Password: admin123';
    } else {
        echo 'Gagal membuat admin!';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
} 