<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('banhang');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$cart = $data['cart'] ?? [];
$customer_id = $data['customer_id'] ?: null;
$promotion_id = $data['promotion_id'] ?: null;
$payment_method = $data['payment_method'] ?? 'TIENMAT';
$points_used = (int)($data['points_used'] ?? 0);

if (empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Gio hang trong']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Tao ma hoa don
    $stmt = $pdo->query("SELECT ma_hd FROM HOADON ORDER BY ma_hoa_don DESC LIMIT 1");
    $last = $stmt->fetch();
    $num = $last ? ((int)preg_replace('/[^0-9]/', '', $last['ma_hd']) + 1) : 1;
    $ma_hd = 'HD' . str_pad($num, 3, '0', STR_PAD_LEFT);

    // Tinh tong tam tinh
    $tong_tam_tinh = 0;
    foreach ($cart as $item) {
        $tong_tam_tinh += $item['price'] * $item['qty'];
    }

    // Tinh giam gia
    $giam_gia = 0;
    if ($promotion_id) {
        $stmt = $pdo->prepare("SELECT * FROM KHUYENMAI WHERE ma_khuyen_mai = ? AND trang_thai = 1");
        $stmt->execute([$promotion_id]);
        $km = $stmt->fetch();
        if ($km && $tong_tam_tinh >= $km['dieu_kien_toi_thieu']) {
            if ($km['loai_giam'] === 'PHANTRAM') {
                $giam_gia = $tong_tam_tinh * ($km['gia_tri_giam'] / 100);
            } else {
                $giam_gia = $km['gia_tri_giam'];
            }
        }
    }

    $diem_quy_doi = $points_used * 100;
    $tong_thanh_toan = max(0, $tong_tam_tinh - $giam_gia - $diem_quy_doi);

    // Lay ma nhan vien tu session
    $stmt = $pdo->prepare("SELECT ma_nhan_vien FROM TAIKHOAN WHERE ma_tai_khoan = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $nv = $stmt->fetch();
    $ma_nv = $nv['ma_nhan_vien'] ?? 1;

    // Them hoa don
    $stmt = $pdo->prepare("
        INSERT INTO HOADON (ma_hd, ma_khach_hang, ma_nhan_vien, tong_tam_tinh, giam_gia, diem_su_dung, diem_quy_doi, tong_thanh_toan, trang_thai)
        VALUES (:ma_hd, :ma_kh, :ma_nv, :tong_tam, :giam_gia, :diem_sd, :diem_qd, :tong_tt, 'HOANTAT')
    ");
    $stmt->execute([
        'ma_hd' => $ma_hd,
        'ma_kh' => $customer_id,
        'ma_nv' => $ma_nv,
        'tong_tam' => $tong_tam_tinh,
        'giam_gia' => $giam_gia,
        'diem_sd' => $points_used,
        'diem_qd' => $diem_quy_doi,
        'tong_tt' => $tong_thanh_toan
    ]);
    $ma_hoa_don = $pdo->lastInsertId();

    // Them chi tiet hoa don
    $stmt_ct = $pdo->prepare("
        INSERT INTO CHITIETHOADON (ma_hoa_don, ma_bien_the, so_luong, don_gia, thanh_tien)
        VALUES (:ma_hd, :ma_bt, :sl, :dg, :tt)
    ");
    foreach ($cart as $item) {
        $stmt_ct->execute([
            'ma_hd' => $ma_hoa_don,
            'ma_bt' => $item['id'],
            'sl' => $item['qty'],
            'dg' => $item['price'],
            'tt' => $item['price'] * $item['qty']
        ]);
    }

    // Them thanh toan
    $stmt = $pdo->prepare("
        INSERT INTO THANHTOAN (ma_hoa_don, phuong_thuc, so_tien, ghi_chu)
        VALUES (:ma_hd, :pt, :tien, 'Thanh toan POS')
    ");
    $stmt->execute([
        'ma_hd' => $ma_hoa_don,
        'pt' => $payment_method,
        'tien' => $tong_thanh_toan
    ]);

    // Tru ton kho
    $stmt = $pdo->prepare("
        UPDATE TONKHO SET so_luong = so_luong - :sl WHERE ma_bien_the = :ma_bt
    ");
    foreach ($cart as $item) {
        $stmt->execute(['sl' => $item['qty'], 'ma_bt' => $item['id']]);
    }

    // Ghi lich su kho
    $stmt_ls = $pdo->prepare("
        INSERT INTO LICHSUTONKHO (ma_bien_the, loai_giao_dich, so_luong_truoc, so_luong_thay_doi, so_luong_sau, ma_hoa_don, ghi_chu, nguoi_thuc_hien)
        VALUES (:ma_bt, 'BAN', :truoc, :thay_doi, :sau, :ma_hd, 'Ban hang POS', :nguoi)
    ");
    foreach ($cart as $item) {
        $stmt_tk = $pdo->prepare("SELECT so_luong FROM TONKHO WHERE ma_bien_the = ?");
        $stmt_tk->execute([$item['id']]);
        $tk = $stmt_tk->fetch();
        $sau = $tk['so_luong'];
        $stmt_ls->execute([
            'ma_bt' => $item['id'],
            'truoc' => $sau + $item['qty'],
            'thay_doi' => -$item['qty'],
            'sau' => $sau,
            'ma_hd' => $ma_hoa_don,
            'nguoi' => $_SESSION['user_id']
        ]);
    }

    // Cap nhat diem khach hang
    if ($customer_id) {
        $diem_cong = floor($tong_thanh_toan / 10000);
        $stmt = $pdo->prepare("
            UPDATE KHACHHANG SET tong_chi_tieu = tong_chi_tieu + :tien, diem_tich_luy = diem_tich_luy + :diem_cong - :diem_sd
            WHERE ma_khach_hang = :ma_kh
        ");
        $stmt->execute(['tien' => $tong_thanh_toan, 'diem_cong' => $diem_cong, 'diem_sd' => $points_used, 'ma_kh' => $customer_id]);

        // Ghi lich su tich diem
        $stmt = $pdo->prepare("
            INSERT INTO LICHSUTICHDIEM (ma_khach_hang, loai_giao_dich, diem_truoc, diem_thay_doi, diem_sau, ma_hoa_don, ghi_chu)
            VALUES (:ma_kh, 'CONG', :truoc, :thay_doi, :sau, :ma_hd, 'Tich diem tu hoa don')
        ");
        $stmt_tk = $pdo->prepare("SELECT diem_tich_luy FROM KHACHHANG WHERE ma_khach_hang = ?");
        $stmt_tk->execute([$customer_id]);
        $kh = $stmt_tk->fetch();
        $stmt->execute([
            'ma_kh' => $customer_id,
            'truoc' => $kh['diem_tich_luy'] - $diem_cong + $points_used,
            'thay_doi' => $diem_cong,
            'sau' => $kh['diem_tich_luy'],
            'ma_hd' => $ma_hoa_don
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'ma_hd' => $ma_hd]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
