<?php
session_start();
require_once 'config/database.php';

// Cek login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Cek ID pesanan
if (!isset($_GET['order_id'])) {
    header('Location: orders.php');
    exit();
}

$db = new Database();
$collection = $db->getConnection();

try {
    $payment = $collection->payments->findOne([
        'order_id' => new MongoDB\BSON\ObjectId($_GET['order_id']),
        'user_id' => new MongoDB\BSON\ObjectId($_SESSION['user']['_id'])
    ]);

    if (!$payment) {
        $_SESSION['error'] = 'Data pembayaran tidak ditemukan!';
        header('Location: orders.php');
        exit();
    }

    $order = $collection->orders->findOne([
        '_id' => $payment->order_id
    ]);
} catch (Exception $e) {
    $_SESSION['error'] = 'ID tidak valid!';
    header('Location: orders.php');
    exit();
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Status Pembayaran #<?php echo $order->order_number; ?></h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <span class="badge bg-<?php 
                                echo match($payment->status) {
                                    'pending' => 'warning',
                                    'verified' => 'success',
                                    'rejected' => 'danger',
                                    default => 'secondary'
                                };
                            ?> fs-5">
                                <?php 
                                echo match($payment->status) {
                                    'pending' => 'Menunggu Verifikasi',
                                    'verified' => 'Pembayaran Diterima',
                                    'rejected' => 'Pembayaran Ditolak',
                                    default => 'Unknown'
                                };
                                ?>
                            </span>
                        </div>
                        <?php if ($payment->status === 'rejected' && !empty($payment->admin_notes)): ?>
                            <div class="alert alert-danger">
                                <strong>Alasan:</strong> <?php echo htmlspecialchars($payment->admin_notes); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <table class="table">
                        <tr>
                            <th width="30%">Total Pembayaran</th>
                            <td>Rp <?php echo number_format($payment->amount, 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <th>Bank Pengirim</th>
                            <td><?php echo htmlspecialchars($payment->bank_name); ?></td>
                        </tr>
                        <tr>
                            <th>Nomor Rekening</th>
                            <td><?php echo htmlspecialchars($payment->account_number); ?></td>
                        </tr>
                        <tr>
                            <th>Nama Pengirim</th>
                            <td><?php echo htmlspecialchars($payment->account_name); ?></td>
                        </tr>
                        <tr>
                            <th>Tanggal Transfer</th>
                            <td><?php echo $payment->created_at->toDateTime()->format('d/m/Y H:i'); ?></td>
                        </tr>
                        <tr>
                            <th>Bukti Transfer</th>
                            <td>
                                <a href="<?php echo htmlspecialchars($payment->proof_image); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-image"></i> Lihat Bukti Transfer
                                </a>
                            </td>
                        </tr>
                    </table>

                    <?php if ($payment->status === 'rejected'): ?>
                        <div class="d-grid">
                            <a href="payment.php?order_id=<?php echo $order->_id; ?>" 
                               class="btn btn-primary">Upload Ulang Pembayaran</a>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="orders.php" class="btn btn-secondary">Kembali ke Pesanan</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 