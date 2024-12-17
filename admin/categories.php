<?php
require_once 'middleware/admin.php';
require_once '../config/database.php';

$db = new Database();
$collection = $db->getConnection();

// Pastikan collection categories sudah ada
try {
    $collection->createCollection('categories');
} catch (Exception $e) {
    // Collection sudah ada, abaikan error
}

// Handle delete category
if (isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'];
    try {
        $result = $collection->categories->deleteOne([
            '_id' => new MongoDB\BSON\ObjectId($category_id)
        ]);
        if ($result->getDeletedCount()) {
            $_SESSION['success'] = "Kategori berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus kategori";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header('Location: categories.php');
    exit();
}

// Handle add/edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    
    if (empty($name)) {
        $_SESSION['error'] = "Nama kategori harus diisi!";
    } else {
        // Cek apakah kategori dengan nama yang sama sudah ada
        $existing = $collection->categories->findOne(['name' => $name]);
        if ($existing && (!isset($_POST['category_id']) || $existing->_id != $_POST['category_id'])) {
            $_SESSION['error'] = "Kategori dengan nama tersebut sudah ada!";
        } else {
            $category_data = [
                'name' => $name,
                'description' => $description,
                'slug' => strtolower(str_replace(' ', '-', $name)),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];

            try {
                if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
                    // Update existing category
                    $result = $collection->categories->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($_POST['category_id'])],
                        ['$set' => $category_data]
                    );
                    if ($result->getModifiedCount() || $result->getMatchedCount()) {
                        $_SESSION['success'] = "Kategori berhasil diupdate";
                    } else {
                        $_SESSION['error'] = "Tidak ada perubahan data";
                    }
                } else {
                    // Add new category
                    $category_data['created_at'] = new MongoDB\BSON\UTCDateTime();
                    $result = $collection->categories->insertOne($category_data);
                    if ($result->getInsertedId()) {
                        $_SESSION['success'] = "Kategori berhasil ditambahkan";
                    } else {
                        $_SESSION['error'] = "Gagal menambahkan kategori";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error: " . $e->getMessage();
            }
        }
    }
    header('Location: categories.php');
    exit();
}

// Get categories
$categories = $collection->categories->find([], [
    'sort' => ['name' => 1]
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
                <h1 class="h2">Manajemen Kategori</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus"></i> Tambah Kategori
                </button>
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

            <!-- Categories Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Kategori</th>
                                    <th>Slug</th>
                                    <th>Deskripsi</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category->name); ?></td>
                                        <td><?php echo htmlspecialchars($category->slug); ?></td>
                                        <td><?php echo htmlspecialchars($category->description); ?></td>
                                        <td><?php echo $category->created_at->toDateTime()->format('d/m/Y H:i'); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        onclick="editCategory('<?php echo $category->_id; ?>', '<?php echo htmlspecialchars($category->name); ?>', '<?php echo htmlspecialchars($category->description); ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmDelete('<?php echo $category->_id; ?>', '<?php echo htmlspecialchars($category->name); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="categoryId">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="save_category" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
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
                <p>Apakah Anda yakin ingin menghapus kategori "<span id="categoryName"></span>"?</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="category_id" id="deleteCategoryId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_category" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editCategory(id, name, description) {
    document.getElementById('modalTitle').textContent = 'Edit Kategori';
    document.getElementById('categoryId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('description').value = description;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function confirmDelete(categoryId, categoryName) {
    document.getElementById('deleteCategoryId').value = categoryId;
    document.getElementById('categoryName').textContent = categoryName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Reset modal form when closed
document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Tambah Kategori';
    document.getElementById('categoryId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
});
</script>

<?php require_once 'includes/footer.php'; ?> 