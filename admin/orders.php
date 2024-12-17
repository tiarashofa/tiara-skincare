<?php
require_once 'middleware/admin.php';
require_once '../config/database.php';

$db = new Database();
$collection = $db->getConnection();

// Handle update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    try {
        $result = $collection->orders->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($order_id)],
            ['$set' => [
                'status' => $status,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        if ($result->getModifiedCount()) {
            $_SESSION['success'] = "Status pesanan berhasil diupdate";
        } else {
            $_SESSION['error'] = "Gagal mengupdate status pesanan";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: orders.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$skip = ($page - 1) * $limit;

// Filter
$filter = [];
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filter['status'] = $_GET['status'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filter['$or'] = [
        ['order_number' => ['$regex' => $_GET['search'], '$options' => 'i']],
        ['customer_name' => ['$regex' => $_GET['search'], '$options' => 'i']],
        ['customer_email' => ['$regex' => $_GET['search'], '$options' => 'i']]
    ];
}

$total_orders = $collection->orders->countDocuments($filter);
$total_pages = ceil($total_orders / $limit);

// Get orders
$orders = $collection->orders->find(
    $filter,
    [
        'limit' => $limit,
        'skip' => $skip,
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
                <h1 class="h2">Manajemen Pesanan</h1>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Cari Pesanan</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="No. Pesanan / Nama / Email"
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Semua Status</option>
                                <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo isset($_GET['status']) && $_GET['status'] === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="shipped" <?php echo isset($_GET['status']) && $_GET['status'] === 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                                <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="orders.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No. Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order->order_number); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order->customer_name); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order->customer_email); ?></small>
                                        </td>
                                        <td>Rp <?php echo number_format($order->total, 0, ',', '.'); ?></td>
                                        <td>
                                            <select class="form-select form-select-sm status-select" 
                                                    data-order-id="<?php echo $order->_id; ?>"
                                                    style="width: auto;">
                                                <option value="pending" <?php echo $order->status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $order->status === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                                <option value="shipped" <?php echo $order->status === 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                                                <option value="completed" <?php echo $order->status === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="cancelled" <?php echo $order->status === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                            </select>
                                        </td>
                                        <td><?php echo $order->created_at->toDateTime()->format('d/m/Y H:i'); ?></td>
                                        <td>
                                            <a href="order-detail.php?id=<?php echo $order->_id; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                            echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; 
                                            echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '';
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Status Update Form -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="order_id" id="orderIdInput">
    <input type="hidden" name="status" id="statusInput">
    <input type="hidden" name="update_status" value="1">
</form>

<script>
// Handle status change
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const orderId = this.dataset.orderId;
        const status = this.value;
        
        if (confirm('Apakah Anda yakin ingin mengubah status pesanan ini?')) {
            document.getElementById('orderIdInput').value = orderId;
            document.getElementById('statusInput').value = status;
            document.getElementById('statusForm').submit();
        } else {
            // Reset to previous value if cancelled
            this.value = this.options[this.selectedIndex].defaultSelected;
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 