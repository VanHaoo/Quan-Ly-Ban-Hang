<?php
/**
 * Xác thực và phân quyền người dùng
 */
require_once __DIR__ . '/../config/config.php';
session_start();

require_once __DIR__ . '/../config/database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Guest';
$vai_tro = $_SESSION['vai_tro'] ?? 'BANHANG';
$ho_ten = $_SESSION['ho_ten'] ?? $username;

// Lấy quyền của vai trò hiện tại
$stmt = $pdo->prepare("
    SELECT q.ma_quyen_code 
    FROM VAITRO_QUYEN vq
    JOIN QUYEN q ON vq.ma_quyen = q.ma_quyen
    WHERE vq.ma_vai_tro = :ma_vai_tro
");
$stmt->execute(['ma_vai_tro' => $_SESSION['ma_vai_tro']]);
$user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

function hasPermission($permission_code) {
    global $user_permissions;
    return in_array($permission_code, $user_permissions);
}

function requirePermission($permission_code) {
    if (!hasPermission($permission_code)) {
        redirect('dashboard/index.php?error=Khong_co_quyen');
    }
}

function getCurrentModule() {
    $path = $_SERVER['PHP_SELF'];
    $base = trim(BASE_URL, '/');
    if (!empty($base)) {
        $path = str_replace('/' . $base . '/', '/', $path);
    }
    if (strpos($path, '/dashboard/') !== false) return 'dashboard';
    if (strpos($path, '/banhang/') !== false) return 'banhang';
    if (strpos($path, '/sanpham/') !== false) return 'sanpham';
    if (strpos($path, '/nhaphang/') !== false) return 'nhaphang';
    if (strpos($path, '/kho/') !== false) return 'kho';
    if (strpos($path, '/khachhang/') !== false) return 'khachhang';
    if (strpos($path, '/khuyenmai/') !== false) return 'khuyenmai';
    if (strpos($path, '/nhanvien/') !== false) return 'nhanvien';
    if (strpos($path, '/baocao/') !== false) return 'baocao';
    return 'dashboard';
}
