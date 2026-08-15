<?php
require_once __DIR__ . '/config.php';

/**
 * Kết nối cơ sở dữ liệu MySQL sử dụng PDO
 * Cấu hình cho XAMPP mặc định
 */
$host = 'localhost';
$dbname = 'quanlybanhang';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

/**
 * Hàm tạo mã tự động theo định dạng PREFIX + số tự tăng
 */
function generateCode($prefix, $table, $column, $pdo) {
    $stmt = $pdo->query("SELECT $column FROM $table ORDER BY ma_{$table} DESC LIMIT 1");
    $last = $stmt->fetch();
    if ($last) {
        $num = (int) preg_replace('/[^0-9]/', '', $last[$column]) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

/**
 * Hàm format tiền VNĐ
 */
function formatMoney($amount) {
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

/**
 * Hàm format ngày giờ
 */
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}
