<?php
session_start();
header('Content-Type: application/json');

// Terima data JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id']) || !isset($data['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$product_id = $data['product_id'];
$quantity = (int)$data['quantity'];

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Update atau hapus item
if ($quantity > 0) {
    $_SESSION['cart'][$product_id] = $quantity;
} else {
    unset($_SESSION['cart'][$product_id]);
}

echo json_encode([
    'success' => true,
    'message' => 'Keranjang berhasil diupdate',
    'cart_count' => array_sum($_SESSION['cart'])
]); 