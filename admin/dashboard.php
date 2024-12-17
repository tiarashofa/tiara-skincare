<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$collection = $db->getConnection();

// Mengambil statistik
$total_products = $collection->products->countDocuments();
$total_users = $collection->users->countDocuments(['role' => 'user']);
$total_orders = $collection->orders->countDocuments();
$total_revenue = $collection->orders->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$group' => ['_id' => null, 'total' => ['$sum' => '$total']]]
])->toArray();

// Mengambil pesanan terbaru
$recent_orders = $collection->orders->find(
    [],
    [
        'limit' => 5,
        'sort' => ['created_at' => -1]
    ]
)->toArray();

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

            <!-- Statistik Cards -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Produk</h6>
                                    <h2 class="mb-0"><?php echo $total_products; ?></h2>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-box fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="products.php" class="text-white text-decoration-none">Lihat Detail</a>
                            <i class="fas fa-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Pengguna</h6>
                                    <h2 class="mb-0"><?php echo $total_users; ?></h2>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="users.php" class="text-white text-decoration-none">Lihat Detail</a>
                            <i class="fas fa-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Pesanan</h6>
                                    <h2 class="mb-0"><?php echo $total_orders; ?></h2>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="orders.php" class="text-white text-decoration-none">Lihat Detail</a>
                            <i class="fas fa-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Pendapatan</h6>
                                    <h2 class="mb-0">Rp <?php echo number_format($total_revenue[0]->total ?? 0, 0, ',', '.'); ?></h2>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="reports.php" class="text-white text-decoration-none">Lihat Detail</a>
                            <i class="fas fa-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Pesanan Terbaru</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo substr($order->_id, -8); ?></td>
                                        <td><?php echo $order->customer_name; ?></td>
                                        <td>Rp <?php echo number_format($order->total, 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($order->status) {
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($order->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', $order->created_at->toDateTime()->getTimestamp()); ?></td>
                                        <td>
                                            <a href="order-detail.php?id=<?php echo $order->_id; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 