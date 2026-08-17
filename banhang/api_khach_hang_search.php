<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('banhang');
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'customers' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT ma_khach_hang, ma_kh, ho_ten, so_dien_thoai, diem_tich_luy
    FROM KHACHHANG
    WHERE trang_thai = 1 AND (ho_ten LIKE :q OR so_dien_thoai LIKE :q OR ma_kh LIKE :q)
    ORDER BY ho_ten
    LIMIT 10
");
$stmt->execute(['q' => '%' . $q . '%']);
echo json_encode(['success' => true, 'customers' => $stmt->fetchAll()]);