<?php
session_start();
require_once 'config/database.php';
require_once 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Banner Section -->
    <div id="mainCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="assets/images/banner1.png" class="d-block w-100" alt="Promo 1" width="200px" height="200px">
            </div>
            <div class="carousel-item">
                <img src="assets/images/banner11.jpg" class="d-block w-100" alt="Promo 2 width="200px" height="200px">
            </div>
        </div>
    </div>

    <!-- Featured Products -->
    <h2 class="mb-4">Produk Unggulan</h2>
    <div class="row">
        <?php
        $db = new Database();
        $products = $db->getConnection()->products->find(
            ['featured' => true],
            ['limit' => 4]
        );

        foreach ($products as $product) {
        ?>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <img src="<?php echo $product->image; ?>" class="card-img-top product-image" alt="<?php echo $product->name; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $product->name; ?></h5>
                        <p class="card-text">Rp <?php echo number_format($product->price, 0, ',', '.'); ?></p>
                        <a href="product.php?id=<?php echo $product->_id; ?>" class="btn btn-primary">Lihat Detail</a>
                    </div>
                </div>
            </div>
        <?php
        }
        ?>
    </div>

    <!-- About Section -->
    <div class="row mt-5">
        <div class="col-md-6">
            <h2>Tentang Kami</h2>
            <p>Selamat datang di Skincare Shop, destinasi terpercaya untuk semua kebutuhan perawatan kulit Anda. Kami menyediakan produk-produk berkualitas tinggi dengan harga terjangkau.</p>
        </div>
        <div class="col-md-6">
            <img src="assets/images/about.jpg" class="img-fluid rounded" alt="About Us">
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 