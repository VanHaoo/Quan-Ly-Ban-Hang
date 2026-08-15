<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Vui long nhap day du thong tin!';
    } else {
        $stmt = $pdo->prepare("
            SELECT tk.*, nv.ho_ten, vt.ten_vai_tro 
            FROM TAIKHOAN tk
            JOIN NHANVIEN nv ON tk.ma_nhan_vien = nv.ma_nhan_vien
            JOIN VAITRO vt ON tk.ma_vai_tro = vt.ma_vai_tro
            WHERE tk.username = :username AND tk.trang_thai = 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['ma_tai_khoan'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['ho_ten'] = $user['ho_ten'];
            $_SESSION['ma_vai_tro'] = $user['ma_vai_tro'];
            $_SESSION['vai_tro'] = $user['ten_vai_tro'];

            $pdo->prepare("UPDATE TAIKHOAN SET lan_dang_nhap_cuoi = NOW() WHERE ma_tai_khoan = :id")
                ->execute(['id' => $user['ma_tai_khoan']]);

            redirect('dashboard/index.php');
        } else {
            $error = 'Ten dang nhap hoac mat khau khong dung!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dang nhap - Quan Ly Ban Hang</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-brand">
                <i class="fas fa-store"></i>
                <h1>QUAN LY BAN HANG</h1>
                <p>He thong quan ly cua hang ban le</p>
            </div>
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Ten dang nhap</label>
                    <input type="text" name="username" placeholder="Nhap username" required autofocus>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Mat khau</label>
                    <input type="password" name="password" placeholder="Nhap mat khau" required>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> DANG NHAP
                </button>
            </form>
            <div class="login-info">
                <p><strong>Tai khoan demo:</strong></p>
                <p>admin / 123456 | quanly / 123456 | banhang / 123456 | kho / 123456</p>
            </div>
        </div>
    </div>
</body>
</html>
