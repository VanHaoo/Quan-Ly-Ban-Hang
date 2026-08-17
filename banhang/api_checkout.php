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
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
    exit;
}
if ($points_used < 0) {
    echo json_encode(['success' => false, 'message' => 'Số điểm sử dụng không hợp lệ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Tính tạm tính từ giá thời điểm hiện tại (đọc lại từ DB, không tin giá client gửi lên)
    $stmt_gia = $pdo->prepare("SELECT gia_ban FROM BIEN_THESANPHAM WHERE ma_bien_the = ?");
    $tong_tam_tinh = 0;
    $gia_thuc = [];
    foreach ($cart as $item) {
        $stmt_gia->execute([$item['id']]);
        $row = $stmt_gia->fetch();
        if (!$row) throw new Exception('Sản phẩm không tồn tại (id ' . $item['id'] . ')');
        if ((int)$item['qty'] <= 0) throw new Exception('Số lượng không hợp lệ');
        $gia_thuc[$item['id']] = $row['gia_ban'];
        $tong_tam_tinh += $row['gia_ban'] * $item['qty'];
    }

    // Tính giảm giá khuyến mãi (validate lại ở server)
    $giam_gia = 0;
    if ($promotion_id) {
        $stmt = $pdo->prepare("SELECT * FROM KHUYENMAI WHERE ma_khuyen_mai = ? AND trang_thai = 1
                                AND ngay_bat_dau <= CURDATE() AND ngay_ket_thuc >= CURDATE()");
        $stmt->execute([$promotion_id]);
        $km = $stmt->fetch();
        if (!$km) throw new Exception('Khuyến mãi không còn hiệu lực');
        if ($tong_tam_tinh >= $km['dieu_kien_toi_thieu']) {
            $giam_gia = $km['loai_giam'] === 'PHANTRAM'
                ? $tong_tam_tinh * ($km['gia_tri_giam'] / 100)
                : $km['gia_tri_giam'];
        }
    }

    // Lấy mã nhân viên từ session
    $stmt = $pdo->prepare("SELECT ma_nhan_vien FROM TAIKHOAN WHERE ma_tai_khoan = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $nv = $stmt->fetch();
    $ma_nv = $nv['ma_nhan_vien'] ?? 1;

    // Sinh mã hóa đơn dựa trên ma_hoa_don tự tăng (không race condition)
    $stmt = $pdo->prepare("
        INSERT INTO HOADON (ma_hd, ma_khach_hang, ma_nhan_vien, tong_tam_tinh, giam_gia, diem_su_dung, tong_thanh_toan, trang_thai)
        VALUES ('TEMP', :ma_kh, :ma_nv, 0, 0, 0, 0, 'HOANTAT')
    ");
    $stmt->execute(['ma_kh' => $customer_id, 'ma_nv' => $ma_nv]);
    $ma_hoa_don = $pdo->lastInsertId();
    $ma_hd = 'HD' . str_pad($ma_hoa_don, 3, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE HOADON SET ma_hd = ? WHERE ma_hoa_don = ?")->execute([$ma_hd, $ma_hoa_don]);

    // Thêm chi tiết hóa đơn (dùng giá đọc từ DB, không tin giá client)
    $stmt_ct = $pdo->prepare("
        INSERT INTO CHITIETHOADON (ma_hoa_don, ma_bien_the, so_luong, don_gia, thanh_tien)
        VALUES (:ma_hd, :ma_bt, :sl, :dg, :tt)
    ");
    foreach ($cart as $item) {
        $gia = $gia_thuc[$item['id']];
        $stmt_ct->execute([
            'ma_hd' => $ma_hoa_don, 'ma_bt' => $item['id'], 'sl' => $item['qty'],
            'dg' => $gia, 'tt' => $gia * $item['qty']
        ]);
    }

    $pdo->commit();

    // ===== Gọi stored procedure để hoàn tất: tính tổng, trừ kho, cộng điểm, TRONG 1 TRANSACTION =====
    // Lưu ý: SP tự quản lý transaction riêng (START TRANSACTION/COMMIT bên trong),
    // nên KHÔNG bọc lời gọi này trong beginTransaction() của PHP (tránh xung đột transaction lồng nhau).
    $stmt = $pdo->prepare("CALL SP_THANHTOAN_HOADON(:ma_hd, :ma_kh, :ma_nv, :giam_gia, :diem_sd, :pt, :nguoi)");
    $stmt->execute([
        'ma_hd' => $ma_hd, 'ma_kh' => $customer_id, 'ma_nv' => $ma_nv,
        'giam_gia' => $giam_gia, 'diem_sd' => $points_used,
        'pt' => $payment_method, 'nguoi' => $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true, 'ma_hd' => $ma_hd]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Nếu SP đã lỡ tạo HOADON rỗng ở bước trước mà SP lỗi (vd het hang/het diem),
    // hóa đơn "TEMP" trạng thái vẫn còn tồn tại với tong_thanh_toan=0 -> dọn lại cho sạch:
    if (!empty($ma_hoa_don)) {
        $pdo->prepare("DELETE FROM CHITIETHOADON WHERE ma_hoa_don = ?")->execute([$ma_hoa_don]);
        $pdo->prepare("DELETE FROM HOADON WHERE ma_hoa_don = ? AND tong_thanh_toan = 0")->execute([$ma_hoa_don]);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}