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

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);
    $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
    $brand = filter_var($_POST['brand'], FILTER_SANITIZE_STRING);

    // Validasi
    if (empty($name) || empty($description) || empty($price) || empty($stock) || empty($category) || empty($brand)) {
        $error = 'Semua field harus diisi!';
    } elseif ($price <= 0) {
        $error = 'Harga harus lebih dari 0!';
    } elseif ($stock < 0) {
        $error = 'Stok tidak boleh negatif!';
    } else {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Format file harus JPG atau PNG!';
            } else {
                $upload_dir = '../uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = '/uploads/products/' . $file_name;
                } else {
                    $error = 'Gagal mengupload gambar!';
                }
            }
        }

        if (empty($error)) {
            try {
                $result = $collection->products->insertOne([
                    'name' => $name,
                    'description' => $description,
                    'price' => (float)$price,
                    'stock' => (int)$stock,
                    'category' => $category,
                    'brand' => $brand,
                    'image' => $image_path,
                    'featured' => isset($_POST['featured']) ? true : false,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]);

                if ($result->getInsertedCount()) {
                    $_SESSION['success'] = 'Produk berhasil ditambahkan!';
                    header('Location: products.php');
                    exit();
                } else {
                    $error = 'Gagal menambahkan produk!';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get existing categories and brands for dropdown
$categories = $collection->categories->find([], ['sort' => ['name' => 1]])->toArray();
$brands = $collection->products->distinct('brand');

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Tambah Produk</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nama Produk</label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Deskripsi</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?php 
                                        echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                                    ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="price" class="form-label">Harga</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number" class="form-control" id="price" name="price" 
                                                       min="0" step="1000" required
                                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="stock" class="form-label">Stok</label>
                                            <input type="number" class="form-control" id="stock" name="stock" 
                                                   min="0" required
                                                   value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Gambar Produk</label>
                                    <div class="product-image-preview">
                                        <img src="/assets/images/placeholder.png" alt="Preview">
                                    </div>
                                    <div class="image-upload-area" onclick="document.getElementById('image').click()">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Klik atau seret gambar ke sini</p>
                                        <p class="small text-muted">Format: JPG, PNG. Maksimal 2MB</p>
                                    </div>
                                    <input type="file" class="image-input" id="image" name="image" 
                                           accept="image/*" onchange="previewImage(this)">
                                </div>

                                <div class="mb-3">
                                    <label for="category" class="form-label">Kategori</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category->name); ?>" 
                                                    <?php echo isset($_POST['category']) && $_POST['category'] == $category->name ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="brand" class="form-label">Merek</label>
                                    <input type="text" class="form-control" id="brand" name="brand" 
                                           list="brandList" required
                                           value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>">
                                    <datalist id="brandList">
                                        <?php foreach ($brands as $brand): ?>
                                            <option value="<?php echo htmlspecialchars($brand); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="featured" name="featured"
                                               <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">Produk Unggulan</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="products.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Produk</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Preview image before upload
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.size > 2 * 1024 * 1024) {
        alert('Ukuran file terlalu besar! Maksimal 2MB');
        this.value = '';
    }
});

// Format currency input
document.getElementById('price').addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value === '') return;
    this.value = Math.max(0, parseInt(value));
});
</script>

<?php require_once 'includes/footer.php'; ?> 