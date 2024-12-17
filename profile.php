<?php
session_start();
require_once 'config/database.php';

// Cek login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$collection = $db->getConnection();

$error = '';
$success = '';

// Get user data
try {
    $user = $collection->users->findOne([
        '_id' => new MongoDB\BSON\ObjectId($_SESSION['user']['_id'])
    ]);
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars(trim($_POST['address']), ENT_QUOTES, 'UTF-8');
        
        if (empty($name)) {
            $error = 'Nama harus diisi!';
        } else {
            try {
                $result = $collection->users->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($_SESSION['user']['_id'])],
                    ['$set' => [
                        'name' => $name,
                        'phone' => $phone,
                        'address' => $address,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );

                if ($result->getModifiedCount()) {
                    $_SESSION['user']['name'] = $name;
                    $success = 'Profil berhasil diupdate!';
                    $user->name = $name;
                    $user->phone = $phone;
                    $user->address = $address;
                } else {
                    $error = 'Tidak ada perubahan data!';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field password harus diisi!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru tidak cocok!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter!';
        } elseif (!password_verify($current_password, $user->password)) {
            $error = 'Password saat ini tidak valid!';
        } else {
            try {
                $result = $collection->users->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($_SESSION['user']['_id'])],
                    ['$set' => [
                        'password' => password_hash($new_password, PASSWORD_DEFAULT),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );

                if ($result->getModifiedCount()) {
                    $success = 'Password berhasil diubah!';
                } else {
                    $error = 'Gagal mengubah password!';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Info -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Informasi Profil</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user->email); ?>" readonly>
                            <div class="form-text">Email tidak dapat diubah</div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($user->name); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo isset($user->phone) ? htmlspecialchars($user->phone) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php 
                                echo isset($user->address) ? htmlspecialchars($user->address) : ''; 
                            ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profil</button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Ubah Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Password Saat Ini</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Minimal 6 karakter</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">Ubah Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide password
document.querySelectorAll('[type="password"]').forEach(input => {
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'btn btn-outline-secondary';
    toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
    toggleBtn.style.position = 'absolute';
    toggleBtn.style.right = '10px';
    toggleBtn.style.top = '50%';
    toggleBtn.style.transform = 'translateY(-50%)';
    
    const wrapper = document.createElement('div');
    wrapper.className = 'position-relative';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    wrapper.appendChild(toggleBtn);
    
    toggleBtn.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
        toggleBtn.innerHTML = `<i class="fas fa-eye${input.type === 'password' ? '' : '-slash'}"></i>`;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 