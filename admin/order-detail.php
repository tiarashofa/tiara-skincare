<?php
require_once 'middleware/admin.php';
require_once '../config/database.php';

$db = new Database();
$collection = $db->getConnection();

// Cek ID pesanan
if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

try {
    $order = $collection->orders->findOne([
        '_id' => new MongoDB\BSON\ObjectId($_GET['id'])
    ]);

    if (!$order) {
        $_SESSION['error'] = 'Pesanan tidak ditemukan!';
        header('Location: orders.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'ID Pesanan tidak valid!';
    header('Location: orders.php');
    exit();
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Detail Pesanan #<?php echo htmlspecialchars($order->order_number); ?></h1>
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>

            <div class="row">
                <!-- Order Info -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informasi Pesanan</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="35%">No. Pesanan</th>
                                    <td><?php echo htmlspecialchars($order->order_number); ?></td>
                                </tr>
                                <tr>
                                    <th>Tanggal</th>
                                    <td><?php echo $order->created_at->toDateTime()->format('d/m/Y H:i'); ?></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order->status) {
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'shipped' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order->status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total</th>
                                    <td>Rp <?php echo number_format($order->total, 0, ',', '.'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informasi Pelanggan</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="35%">Nama</th>
                                    <td><?php echo htmlspecialchars($order->customer_name); ?></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td><?php echo htmlspecialchars($order->customer_email); ?></td>
                                </tr>
                                <tr>
                                    <th>Telepon</th>
                                    <td><?php echo htmlspecialchars($order->customer_phone); ?></td>
                                </tr>
                                <tr>
                                    <th>Alamat</th>
                                    <td><?php echo nl2br(htmlspecialchars($order->shipping_address)); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Detail Produk</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Harga</th>
                                            <th>Jumlah</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order->items as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($item->product_name); ?>
                                                </td>
                                                <td>Rp <?php echo number_format($item->price, 0, ',', '.'); ?></td>
                                                <td><?php echo $item->quantity; ?></td>
                                                <td class="text-end">
                                                    Rp <?php echo number_format($item->price * $item->quantity, 0, ',', '.'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                                            <td class="text-end">
                                                <strong>Rp <?php echo number_format($order->total, 0, ',', '.'); ?></strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 