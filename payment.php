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
    $order = $collection->orders->findOne([
        '_id' => new MongoDB\BSON\ObjectId($_GET['order_id']),
        'user_id' => $_SESSION['user']['_id'] // Pastikan pesanan milik user yang login
    ]);

    if (!$order) {
        $_SESSION['error'] = 'Pesanan tidak ditemukan!';
        header('Location: orders.php');
        exit();
    }

    // Cek apakah sudah ada pembayaran untuk pesanan ini
    $payment = $collection->payments->findOne(['order_id' => $order->_id]);
    if ($payment) {
        header('Location: payment-status.php?order_id=' . $_GET['order_id']);
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'ID Pesanan tidak valid!';
    header('Location: orders.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = filter_var($_POST['bank_name'], FILTER_SANITIZE_STRING);
    $account_number = filter_var($_POST['account_number'], FILTER_SANITIZE_STRING);
    $account_name = filter_var($_POST['account_name'], FILTER_SANITIZE_STRING);
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    
    $error = '';
    
    // Validasi
    if (empty($bank_name) || empty($account_number) || empty($account_name) || empty($amount)) {
        $error = 'Semua field harus diisi!';
    } elseif ($amount != $order->total) {
        $error = 'Jumlah transfer tidak sesuai dengan total pesanan!';
    } elseif (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Bukti pembayaran harus diupload!';
    } else {
        // Handle image upload
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['proof_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Format file harus JPG atau PNG!';
        } elseif ($_FILES['proof_image']['size'] > 2 * 1024 * 1024) { // 2MB
            $error = 'Ukuran file maksimal 2MB!';
        } else {
            $upload_dir = 'uploads/payments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_path)) {
                try {
                    $payment_data = [
                        'order_id' => $order->_id,
                        'user_id' => new MongoDB\BSON\ObjectId($_SESSION['user']['_id']),
                        'customer_name' => $_SESSION['user']['name'],
                        'customer_email' => $_SESSION['user']['email'],
                        'bank_name' => $bank_name,
                        'account_number' => $account_number,
                        'account_name' => $account_name,
                        'amount' => (float)$amount,
                        'proof_image' => '/' . $target_path,
                        'status' => 'pending',
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];

                    $result = $collection->payments->insertOne($payment_data);
                    if ($result->getInsertedId()) {
                        // Update status pesanan
                        $collection->orders->updateOne(
                            ['_id' => $order->_id],
                            ['$set' => [
                                'payment_status' => 'pending',
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );

                        $_SESSION['success'] = 'Pembayaran berhasil disubmit!';
                        header('Location: payment-status.php?order_id=' . $_GET['order_id']);
                        exit();
                    } else {
                        $error = 'Gagal menyimpan data pembayaran!';
                        // Hapus file jika gagal
                        unlink($target_path);
                    }
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                    // Hapus file jika gagal
                    unlink($target_path);
                }
            } else {
                $error = 'Gagal mengupload file!';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Pembayaran Pesanan #<?php echo $order->order_number; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Informasi Rekening Toko:</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td>Bank</td>
                                    <td>: BCA</td>
                                </tr>
                                <tr>
                                    <td>No. Rekening</td>
                                    <td>: 1234567890</td>
                                </tr>
                                <tr>
                                    <td>Atas Nama</td>
                                    <td>: Tiara Skincare</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Total Pembayaran:</h6>
                            <h3 class="text-primary">Rp <?php echo number_format($order->total, 0, ',', '.'); ?></h3>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="bank_name" class="form-label">Bank Pengirim</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name" required
                                   value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="account_number" class="form-label">Nomor Rekening</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" required
                                   value="<?php echo isset($_POST['account_number']) ? htmlspecialchars($_POST['account_number']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="account_name" class="form-label">Nama Pemilik Rekening</label>
                            <input type="text" class="form-control" id="account_name" name="account_name" required
                                   value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label">Jumlah Transfer</label>
                            <input type="number" class="form-control" id="amount" name="amount" required
                                   value="<?php echo $order->total; ?>" readonly>
                            <div class="form-text">Jumlah transfer harus sesuai dengan total pesanan</div>
                        </div>

                        <div class="mb-3">
                            <label for="proof_image" class="form-label">Bukti Transfer</label>
                            <input type="file" class="form-control" id="proof_image" name="proof_image" 
                                   accept="image/*" required>
                            <div class="form-text">Format: JPG, PNG. Maksimal 2MB</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Konfirmasi Pembayaran</button>
                            <a href="orders.php" class="btn btn-secondary">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview image before upload
document.getElementById('proof_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.size > 2 * 1024 * 1024) {
        alert('Ukuran file terlalu besar! Maksimal 2MB');
        this.value = '';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 