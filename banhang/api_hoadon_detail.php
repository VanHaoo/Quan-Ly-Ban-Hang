<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('banhang');

header('Content-Type: application/json; charset=utf-8');

$maHoaDon = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$maHoaDon || $maHoaDon < 1) {
    echo json_encode(['success' => false, 'message' => 'Mã hóa đơn không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT hd.ma_hoa_don, hd.ma_hd, hd.ngay_tao, hd.tong_tam_tinh, hd.giam_gia,
               hd.diem_su_dung, hd.diem_quy_doi, hd.tong_thanh_toan, hd.trang_thai,
               kh.ho_ten AS ten_khach, nv.ho_ten AS ten_nv
        FROM HOADON hd
        LEFT JOIN KHACHHANG kh ON hd.ma_khach_hang = kh.ma_khach_hang
        JOIN NHANVIEN nv ON hd.ma_nhan_vien = nv.ma_nhan_vien
        WHERE hd.ma_hoa_don = :ma_hoa_don
    ");
    $stmt->execute(['ma_hoa_don' => $maHoaDon]);
    $hoaDon = $stmt->fetch();

    if (!$hoaDon) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT ct.so_luong, ct.don_gia, ct.thanh_tien, bt.sku, bt.ten_bien_the,
               sp.ten_san_pham
        FROM CHITIETHOADON ct
        JOIN BIEN_THESANPHAM bt ON ct.ma_bien_the = bt.ma_bien_the
        JOIN SANPHAM sp ON bt.ma_san_pham = sp.ma_san_pham
        WHERE ct.ma_hoa_don = :ma_hoa_don
        ORDER BY ct.ma_ct_hd
    ");
    $stmt->execute(['ma_hoa_don' => $maHoaDon]);

    echo json_encode([
        'success' => true,
        'hoadon' => $hoaDon,
        'chi_tiet' => $stmt->fetchAll()
    ]);
} catch (PDOException $e) {
    error_log('Không thể tải chi tiết hóa đơn: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Không thể tải chi tiết hóa đơn']);
}
