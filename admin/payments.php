<?php
require_once 'middleware/admin.php';
require_once '../config/database.php';

$db = new Database();
$collection = $db->getConnection();

// Handle verifikasi pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $payment_id = $_POST['payment_id'];
    $status = $_POST['status'];
    $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);
    
    try {
        $payment = $collection->payments->findOne([
            '_id' => new MongoDB\BSON\ObjectId($payment_id)
        ]);

        if ($payment) {
            // Update status pembayaran
            $result = $collection->payments->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($payment_id)],
                ['$set' => [
                    'status' => $status,
                    'admin_notes' => $notes,
                    'verified_at' => new MongoDB\BSON\UTCDateTime(),
                    'verified_by' => $_SESSION['user']['name'],
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // Update status pesanan jika pembayaran diverifikasi
            if ($status === 'verified') {
                $collection->orders->updateOne(
                    ['_id' => $payment->order_id],
                    ['$set' => [
                        'payment_status' => 'paid',
                        'status' => 'processing',
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                $_SESSION['success'] = "Pembayaran berhasil diverifikasi";
            } elseif ($status === 'rejected') {
                $collection->orders->updateOne(
                    ['_id' => $payment->order_id],
                    ['$set' => [
                        'payment_status' => 'rejected',
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                $_SESSION['success'] = "Pembayaran ditolak";
            }
        } else {
            $_SESSION['error'] = "Data pembayaran tidak ditemukan";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: payments.php');
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
        ['bank_name' => ['$regex' => $_GET['search'], '$options' => 'i']]
    ];
}

$total_payments = $collection->payments->countDocuments($filter);
$total_pages = ceil($total_payments / $limit);

// Get payments with order data
$payments = $collection->payments->aggregate([
    ['$match' => $filter],
    ['$sort' => ['created_at' => -1]],
    ['$skip' => $skip],
    ['$limit' => $limit],
    ['$lookup' => [
        'from' => 'orders',
        'localField' => 'order_id',
        'foreignField' => '_id',
        'as' => 'order'
    ]],
    ['$unwind' => '$order']
])->toArray();

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manajemen Pembayaran</h1>
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
                            <label for="search" class="form-label">Cari Pembayaran</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="No. Pesanan / Nama / Bank"
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Semua Status</option>
                                <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="verified" <?php echo isset($_GET['status']) && $_GET['status'] === 'verified' ? 'selected' : ''; ?>>Terverifikasi</option>
                                <option value="rejected" <?php echo isset($_GET['status']) && $_GET['status'] === 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="payments.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No. Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>Bank</th>
                                    <th>Jumlah</th>
                                    <th>Bukti</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment->order->order_number); ?></td>
                                        <td><?php echo htmlspecialchars($payment->customer_name); ?></td>
                                        <td><?php echo htmlspecialchars($payment->bank_name); ?></td>
                                        <td>Rp <?php echo number_format($payment->amount, 0, ',', '.'); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($payment->proof_image); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-image"></i> Lihat
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($payment->status) {
                                                    'pending' => 'warning',
                                                    'verified' => 'success',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($payment->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $payment->created_at->toDateTime()->format('d/m/Y H:i'); ?></td>
                                        <td>
                                            <?php if ($payment->status === 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        onclick="showVerificationModal('<?php echo $payment->_id; ?>', '<?php echo $payment->order->order_number; ?>')">
                                                    <i class="fas fa-check"></i> Verifikasi
                                                </button>
                                            <?php else: ?>
                                                <a href="payment-detail.php?id=<?php echo $payment->_id; ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> Detail
                                                </a>
                                            <?php endif; ?>
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

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verifikasi Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="paymentId">
                    <p>Verifikasi pembayaran untuk pesanan <strong id="orderNumber"></strong>?</p>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="verificationStatus" name="status" required>
                            <option value="verified">Terverifikasi</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="verify_payment" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showVerificationModal(paymentId, orderNumber) {
    document.getElementById('paymentId').value = paymentId;
    document.getElementById('orderNumber').textContent = orderNumber;
    new bootstrap.Modal(document.getElementById('verificationModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?> 