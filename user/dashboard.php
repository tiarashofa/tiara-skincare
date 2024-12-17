<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user']['_id'];

// Ambil data pesanan user
$orders = $db->getConnection()->orders->find([
    'user_id' => new MongoDB\BSON\ObjectId($userId)
], ['sort' => ['order_date' => -1]]);

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column align-items-center text-center">
                        <img src="<?php echo isset($_SESSION['user']['avatar']) ? $_SESSION['user']['avatar'] : '/assets/images/default-avatar.png'; ?>" 
                             alt="Profile" class="rounded-circle" width="150">
                        <div class="mt-3">
                            <h4><?php echo $_SESSION['user']['name']; ?></h4>
                            <p class="text-muted"><?php echo $_SESSION['user']['email']; ?></p>
                        </div>
                    </div>
                    <hr class="my-4">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item active">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </li>
                        <li class="list-group-item">
                            <a href="profile.php" class="text-decoration-none text-dark">
                                <i class="fas fa-user me-2"></i> Profil Saya
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="orders.php" class="text-decoration-none text-dark">
                                <i class="fas fa-shopping-bag me-2"></i> Pesanan Saya
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="wishlist.php" class="text-decoration-none text-dark">
                                <i class="fas fa-heart me-2"></i> Wishlist
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Status Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body">
                            <h5 class="card-title text-primary">Total Pesanan</h5>
                            <h2 class="card-text">
                                <?php 
                                $totalOrders = $db->getConnection()->orders->countDocuments([
                                    'user_id' => new MongoDB\BSON\ObjectId($userId)
                                ]);
                                echo $totalOrders;
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body">
                            <h5 class="card-title text-success">Pesanan Selesai</h5>
                            <h2 class="card-text">
                                <?php 
                                $completedOrders = $db->getConnection()->orders->countDocuments([
                                    'user_id' => new MongoDB\BSON\ObjectId($userId),
                                    'status' => 'completed'
                                ]);
                                echo $completedOrders;
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h5 class="card-title text-warning">Dalam Proses</h5>
                            <h2 class="card-text">
                                <?php 
                                $pendingOrders = $db->getConnection()->orders->countDocuments([
                                    'user_id' => new MongoDB\BSON\ObjectId($userId),
                                    'status' => ['$in' => ['pending', 'processing']]
                                ]);
                                echo $pendingOrders;
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Pesanan Terbaru</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Tanggal</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo substr($order->_id, -8); ?></td>
                                    <td><?php echo date('d M Y', $order->order_date->toDateTime()->getTimestamp()); ?></td>
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
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 