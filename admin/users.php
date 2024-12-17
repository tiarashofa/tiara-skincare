<?php
require_once 'middleware/admin.php';
require_once '../config/database.php';

$db = new Database();
$collection = $db->getConnection();

// Handle delete user
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    try {
        // Cek apakah user yang akan dihapus bukan admin
        $user = $collection->users->findOne([
            '_id' => new MongoDB\BSON\ObjectId($user_id)
        ]);

        if ($user && $user->role === 'admin') {
            $_SESSION['error'] = "Tidak dapat menghapus akun admin!";
        } else {
            $result = $collection->users->deleteOne([
                '_id' => new MongoDB\BSON\ObjectId($user_id)
            ]);
            
            if ($result->getDeletedCount()) {
                $_SESSION['success'] = "Pengguna berhasil dihapus";
            } else {
                $_SESSION['error'] = "Gagal menghapus pengguna";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header('Location: users.php');
    exit();
}

// Handle update status
if (isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $status = $_POST['status'];
    
    try {
        // Cek apakah user yang akan diupdate bukan admin
        $user = $collection->users->findOne([
            '_id' => new MongoDB\BSON\ObjectId($user_id)
        ]);

        if ($user && $user->role === 'admin') {
            $_SESSION['error'] = "Tidak dapat mengubah status admin!";
        } else {
            $result = $collection->users->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($user_id)],
                ['$set' => [
                    'status' => $status,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            if ($result->getModifiedCount()) {
                $_SESSION['success'] = "Status pengguna berhasil diupdate";
            } else {
                $_SESSION['error'] = "Tidak ada perubahan status";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header('Location: users.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$skip = ($page - 1) * $limit;

// Filter
$filter = ['role' => 'user']; // Hanya tampilkan user biasa
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filter['$or'] = [
        ['name' => ['$regex' => $_GET['search'], '$options' => 'i']],
        ['email' => ['$regex' => $_GET['search'], '$options' => 'i']]
    ];
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filter['status'] = $_GET['status'];
}

$total_users = $collection->users->countDocuments($filter);
$total_pages = ceil($total_users / $limit);

// Get users
$users = $collection->users->find(
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
                <h1 class="h2">Manajemen Pengguna</h1>
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
                            <label for="search" class="form-label">Cari Pengguna</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                   placeholder="Nama atau email...">
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Semua Status</option>
                                <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : ''; ?>>
                                    Aktif
                                </option>
                                <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Nonaktif
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="users.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Status</th>
                                    <th>Terdaftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user->name); ?></td>
                                        <td><?php echo htmlspecialchars($user->email); ?></td>
                                        <td><?php echo isset($user->phone) ? htmlspecialchars($user->phone) : '-'; ?></td>
                                        <td>
                                            <select class="form-select form-select-sm status-select" 
                                                    data-user-id="<?php echo $user->_id; ?>"
                                                    style="width: auto;">
                                                <option value="active" <?php echo (!isset($user->status) || $user->status === 'active') ? 'selected' : ''; ?>>
                                                    Aktif
                                                </option>
                                                <option value="inactive" <?php echo (isset($user->status) && $user->status === 'inactive') ? 'selected' : ''; ?>>
                                                    Nonaktif
                                                </option>
                                            </select>
                                        </td>
                                        <td><?php echo $user->created_at->toDateTime()->format('d/m/Y H:i'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    onclick="confirmDelete('<?php echo $user->_id; ?>', '<?php echo htmlspecialchars($user->name); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pengguna <strong id="userName"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Form -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="statusUserId">
    <input type="hidden" name="status" id="statusValue">
    <input type="hidden" name="update_status" value="1">
</form>

<script>
// Handle status change
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const userId = this.dataset.userId;
        const status = this.value;
        
        if (confirm('Apakah Anda yakin ingin mengubah status pengguna ini?')) {
            document.getElementById('statusUserId').value = userId;
            document.getElementById('statusValue').value = status;
            document.getElementById('statusForm').submit();
        } else {
            // Reset to previous value if cancelled
            this.value = this.options[this.selectedIndex].defaultSelected;
        }
    });
});

// Show delete confirmation modal
function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('userName').textContent = userName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?> 