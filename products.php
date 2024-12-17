<?php
session_start();
require_once 'config/database.php';
require_once 'includes/header.php';

$db = new Database();
$collection = $db->getConnection()->products;

// Filter dan Pencarian
$filter = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : '';

if (!empty($search)) {
    $filter['name'] = ['$regex' => $search, '$options' => 'i'];
}
if (!empty($category)) {
    $filter['category'] = $category;
}
if (!empty($brand)) {
    $filter['brand'] = $brand;
}
if (!empty($min_price) || !empty($max_price)) {
    $filter['price'] = [];
    if (!empty($min_price)) $filter['price']['$gte'] = $min_price;
    if (!empty($max_price)) $filter['price']['$lte'] = $max_price;
}

// Ambil kategori dan brand untuk filter
$categories = $collection->distinct('category');
$brands = $collection->distinct('brand');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$skip = ($page - 1) * $limit;

$total_products = $collection->countDocuments($filter);
$total_pages = ceil($total_products / $limit);

// Ambil produk
$products = $collection->find($filter, [
    'limit' => $limit,
    'skip' => $skip,
    'sort' => ['created_at' => -1]
]);
?>

<div class="container mt-4">
    <div class="row">
        <!-- Filter Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Filter Produk</h5>
                    <form action="" method="GET">
                        <div class="mb-3">
                            <label class="form-label">Pencarian</label>
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari produk...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="category" class="form-select">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Merek</label>
                            <select name="brand" class="form-select">
                                <option value="">Semua Merek</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?php echo $b; ?>" <?php echo $brand === $b ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($b); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rentang Harga</label>
                            <div class="row">
                                <div class="col">
                                    <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?php echo $min_price; ?>">
                                </div>
                                <div class="col">
                                    <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?php echo $max_price; ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Terapkan Filter</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Produk Kami</h2>
                <span class="text-muted">Menampilkan <?php echo $total_products; ?> produk</span>
            </div>

            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <img src="<?php echo htmlspecialchars($product->image); ?>" 
                                 class="card-img-top product-image" 
                                 alt="<?php echo htmlspecialchars($product->name); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product->name); ?></h5>
                                <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($product->brand); ?></p>
                                <p class="card-text fw-bold">Rp <?php echo number_format($product->price, 0, ',', '.'); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="product-detail.php?id=<?php echo $product->_id; ?>" 
                                       class="btn btn-outline-primary">Detail</a>
                                    <button class="btn btn-primary add-to-cart" 
                                            data-product-id="<?php echo $product->_id; ?>">
                                        <i class="fas fa-shopping-cart"></i> Tambah
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                    echo !empty($category) ? '&category=' . urlencode($category) : '';
                                    echo !empty($brand) ? '&brand=' . urlencode($brand) : '';
                                    echo !empty($min_price) ? '&min_price=' . $min_price : '';
                                    echo !empty($max_price) ? '&max_price=' . $max_price : '';
                                ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add to Cart Script -->
<script>
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.dataset.productId;
        fetch('/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Produk berhasil ditambahkan ke keranjang!');
            } else {
                alert('Gagal menambahkan produk ke keranjang.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menambahkan produk.');
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 