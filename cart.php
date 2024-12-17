<?php
session_start();
require_once 'config/database.php';

// Cek login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$collection = $db->getConnection();

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        $_SESSION['error'] = 'Keranjang belanja kosong!';
    } elseif (empty($_POST['shipping_address'])) {
        $_SESSION['error'] = 'Alamat pengiriman harus diisi!';
    } else {
        try {
            $cart_items = [];
            $total = 0;
            
            // Validasi stok dan hitung total
            foreach ($_SESSION['cart'] as $product_id => $qty) {
                $product = $collection->products->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($product_id)
                ]);
                
                if (!$product) {
                    throw new Exception('Produk tidak ditemukan!');
                }
                if ($product->stock < $qty) {
                    throw new Exception("Stok {$product->name} tidak mencukupi!");
                }
                
                $cart_items[] = [
                    'product_id' => $product->_id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $qty
                ];
                
                $total += $product->price * $qty;
            }
            
            // Generate order number
            $last_order = $collection->orders->findOne(
                [],
                ['sort' => ['order_number' => -1]]
            );
            
            $order_number = $last_order ? 
                'ORD' . str_pad((intval(substr($last_order->order_number, 3)) + 1), 6, '0', STR_PAD_LEFT) : 
                'ORD000001';
            
            // Create order
            $order_data = [
                'order_number' => $order_number,
                'user_id' => new MongoDB\BSON\ObjectId($_SESSION['user']['_id']),
                'customer_name' => $_SESSION['user']['name'],
                'customer_email' => $_SESSION['user']['email'],
                'customer_phone' => htmlspecialchars(trim($_POST['phone'])),
                'shipping_address' => htmlspecialchars(trim($_POST['shipping_address'])),
                'items' => $cart_items,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->orders->insertOne($order_data);
            
            if ($result->getInsertedId()) {
                // Update stok produk
                foreach ($cart_items as $item) {
                    $collection->products->updateOne(
                        ['_id' => $item['product_id']],
                        ['$inc' => ['stock' => -$item['quantity']]]
                    );
                }
                
                // Kosongkan keranjang
                unset($_SESSION['cart']);
                
                // Redirect ke halaman pembayaran
                header('Location: payment.php?order_id=' . $result->getInsertedId());
                exit();
            } else {
                throw new Exception('Gagal membuat pesanan!');
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

// Get cart items
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $qty) {
        $product = $collection->products->findOne([
            '_id' => new MongoDB\BSON\ObjectId($product_id)
        ]);
        
        if ($product) {
            $cart_items[] = [
                'id' => $product_id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => $qty,
                'stock' => $product->stock,
                'image' => $product->image
            ];
            $total += $product->price * $qty;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <h1 class="mb-4">Keranjang Belanja</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            Keranjang belanja kosong. 
            <a href="products.php" class="alert-link">Belanja sekarang!</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Harga</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $item['image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                         class="img-thumbnail me-2" style="width: 50px;">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </div>
                                            </td>
                                            <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm quantity-input"
                                                       style="width: 80px" min="1" max="<?php echo $item['stock']; ?>"
                                                       value="<?php echo $item['quantity']; ?>"
                                                       data-product-id="<?php echo $item['id']; ?>">
                                            </td>
                                            <td>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger remove-item" 
                                                        data-product-id="<?php echo $item['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                        <td colspan="2"><strong>Rp <?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informasi Pengiriman</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required
                                       value="<?php echo isset($user->phone) ? htmlspecialchars($user->phone) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="shipping_address" class="form-label">Alamat Pengiriman</label>
                                <textarea class="form-control" id="shipping_address" name="shipping_address" 
                                          rows="3" required><?php 
                                    echo isset($user->address) ? htmlspecialchars($user->address) : ''; 
                                ?></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="checkout" class="btn btn-primary">
                                    Lanjutkan ke Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Update quantity
document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('change', function() {
        const productId = this.dataset.productId;
        const quantity = parseInt(this.value);
        const maxStock = parseInt(this.getAttribute('max'));
        
        if (quantity < 1) {
            this.value = 1;
        } else if (quantity > maxStock) {
            this.value = maxStock;
            alert('Jumlah melebihi stok yang tersedia!');
        }
        
        fetch('update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `product_id=${productId}&quantity=${this.value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
    });
});

// Remove item
document.querySelectorAll('.remove-item').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Apakah Anda yakin ingin menghapus produk ini?')) {
            const productId = this.dataset.productId;
            
            fetch('update-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `product_id=${productId}&remove=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 