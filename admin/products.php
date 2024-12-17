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

// Get categories for filter
$categories = $collection->categories->find([], ['sort' => ['name' => 1]])->toArray();

// Handle delete product
if (isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    try {
        $result = $collection->products->deleteOne([
            '_id' => new MongoDB\BSON\ObjectId($product_id)
        ]);
        if ($result->getDeletedCount()) {
            $_SESSION['success'] = "Produk berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus produk";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header('Location: products.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$skip = ($page - 1) * $limit;

// Search and Filter
$filter = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filter['name'] = ['$regex' => $_GET['search'], '$options' => 'i'];
}
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $filter['category'] = $_GET['category'];
}

$total_products = $collection->products->countDocuments($filter);
$total_pages = ceil($total_products / $limit);

// Get products
$products = $collection->products->find(
    $filter,
    [
        'limit' => $limit,
        'skip' => $skip,
        'sort' => ['created_at' => -1]
    ]
);

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manajemen Produk</h1>
                <a href="product-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Produk
                </a>
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
                            <label for="search" class="form-label">Cari Produk</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="category_filter" class="form-label">Kategori</label>
                            <select class="form-select" id="category_filter" name="category">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category->name); ?>"
                                            <?php echo isset($_GET['category']) && $_GET['category'] == $category->name ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="products.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="product-image-container">
                                                <?php if (!empty($product->image)): ?>
                                                    <img src="<?php echo htmlspecialchars($product->image); ?>" 
                                                         alt="<?php echo htmlspecialchars($product->name); ?>"
                                                         class="product-image"
                                                         onclick="showImagePreview('<?php echo htmlspecialchars($product->image); ?>', '<?php echo htmlspecialchars($product->name); ?>')">
                                                <?php else: ?>
                                                    <div class="no-image">
                                                        <i class="fas fa-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($product->name); ?></td>
                                        <td><?php echo htmlspecialchars($product->category); ?></td>
                                        <td>Rp <?php echo number_format($product->price, 0, ',', '.'); ?></td>
                                        <td><?php echo $product->stock; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $product->stock > 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $product->stock > 0 ? 'Tersedia' : 'Habis'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="product-edit.php?id=<?php echo $product->_id; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmDelete('<?php echo $product->_id; ?>', '<?php echo htmlspecialchars($product->name); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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
                                            echo isset($_GET['category']) ? '&category=' . urlencode($_GET['category']) : '';
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
                <p>Apakah Anda yakin ingin menghapus produk "<span id="productName"></span>"?</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="product_id" id="productId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_product" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Gambar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" alt="" id="previewImage" class="modal-image-preview">
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(productId, productName) {
    document.getElementById('productId').value = productId;
    document.getElementById('productName').textContent = productName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function showImagePreview(src, alt) {
    const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    const image = document.getElementById('previewImage');
    image.src = src;
    image.alt = alt;
    modal.show();
}

// Image upload preview
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.querySelector('.product-image-preview img');
            if (preview) {
                preview.src = e.target.result;
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Drag and drop
const uploadArea = document.querySelector('.image-upload-area');
if (uploadArea) {
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('border-primary');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('border-primary');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('border-primary');
        
        const input = document.querySelector('.image-input');
        if (input) {
            input.files = e.dataTransfer.files;
            previewImage(input);
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?> 