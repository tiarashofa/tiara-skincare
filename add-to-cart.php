<?php
session_start();
header('Content-Type: application/json');

// Terima data JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id']) || !isset($data['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

require_once 'config/database.php';

$product_id = $data['product_id'];
$quantity = (int)$data['quantity'];

// Validasi produk exists dan stok
$db = new Database();
$product = $db->getConnection()->products->findOne([
    '_id' => new MongoDB\BSON\ObjectId($product_id)
]);

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    exit;
}

if ($product->stock < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
    exit;
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Tambah ke keranjang atau update quantity
if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id] += $quantity;
} else {
    $_SESSION['cart'][$product_id] = $quantity;
}

echo json_encode([
    'success' => true,
    'message' => 'Produk berhasil ditambahkan ke keranjang',
    'cart_count' => array_sum($_SESSION['cart'])
]); 