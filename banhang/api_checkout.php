<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('banhang');
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$cart = is_array($data) ? ($data['cart'] ?? []) : [];
$customerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;
$promotionId = !empty($data['promotion_id']) ? (int) $data['promotion_id'] : null;
$pointsUsed = (int) ($data['points_used'] ?? 0);
$paymentMethod = $data['payment_method'] ?? 'TIENMAT';
$allowedMethods = ['TIENMAT', 'CHUYENKHOAN', 'THE', 'VI'];

if (!$cart || $pointsUsed < 0 || !in_array($paymentMethod, $allowedMethods, true)) {
    echo json_encode(['success' => false, 'message' => 'Thông tin thanh toán không hợp lệ']); exit;
}

$quantities = [];
foreach ($cart as $item) {
    $id = (int) ($item['id'] ?? 0); $qty = (int) ($item['qty'] ?? 0);
    if ($id < 1 || $qty < 1) { echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ']); exit; }
    $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT ma_nhan_vien FROM TAIKHOAN WHERE ma_tai_khoan = ? AND trang_thai = 1');
    $stmt->execute([$_SESSION['user_id']]); $employeeId = $stmt->fetchColumn();
    if (!$employeeId) throw new RuntimeException('Tài khoản chưa được gắn nhân viên hoạt động');

    $customer = null;
    if ($customerId) {
        $stmt = $pdo->prepare('SELECT diem_tich_luy FROM KHACHHANG WHERE ma_khach_hang = ? AND trang_thai = 1 FOR UPDATE');
        $stmt->execute([$customerId]); $customer = $stmt->fetch();
        if (!$customer || $pointsUsed > $customer['diem_tich_luy']) throw new RuntimeException('Khách hàng không hợp lệ hoặc không đủ điểm');
    } elseif ($pointsUsed) throw new RuntimeException('Phải chọn khách hàng để dùng điểm');

    $lookup = $pdo->prepare('SELECT bt.gia_ban, tk.so_luong FROM BIEN_THESANPHAM bt JOIN TONKHO tk ON tk.ma_bien_the = bt.ma_bien_the WHERE bt.ma_bien_the = ? AND bt.trang_thai = 1 FOR UPDATE');
    $items = []; $subtotal = 0;
    foreach ($quantities as $id => $qty) {
        $lookup->execute([$id]); $row = $lookup->fetch();
        if (!$row || $row['so_luong'] < $qty) throw new RuntimeException('Không đủ tồn kho');
        $items[] = [$id, $qty, $row['gia_ban'], $row['so_luong']]; $subtotal += $qty * $row['gia_ban'];
    }
    $discount = 0;
    if ($promotionId) {
        $stmt = $pdo->prepare('SELECT * FROM KHUYENMAI WHERE ma_khuyen_mai = ? AND trang_thai = 1 AND ngay_bat_dau <= CURDATE() AND ngay_ket_thuc >= CURDATE()');
        $stmt->execute([$promotionId]); $km = $stmt->fetch();
        if (!$km) throw new RuntimeException('Khuyến mãi không còn hiệu lực');
        if ($subtotal >= $km['dieu_kien_toi_thieu']) $discount = $km['loai_giam'] === 'PHANTRAM' ? $subtotal * $km['gia_tri_giam'] / 100 : $km['gia_tri_giam'];
    }
    $discount = min($discount, $subtotal); $pointValue = min($pointsUsed * 100, $subtotal - $discount); $total = $subtotal - $discount - $pointValue;
    $stmt = $pdo->prepare("INSERT INTO HOADON (ma_hd,ma_khach_hang,ma_nhan_vien,tong_tam_tinh,giam_gia,diem_su_dung,diem_quy_doi,tong_thanh_toan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['TMP-' . bin2hex(random_bytes(8)), $customerId, $employeeId, $subtotal, $discount, $pointsUsed, $pointValue, $total]);
    $invoiceId = (int) $pdo->lastInsertId(); $code = 'HD' . str_pad($invoiceId, 3, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE HOADON SET ma_hd = ? WHERE ma_hoa_don = ?')->execute([$code, $invoiceId]);
    $detail = $pdo->prepare('INSERT INTO CHITIETHOADON (ma_hoa_don,ma_bien_the,so_luong,don_gia,thanh_tien) VALUES (?, ?, ?, ?, ?)');
    $stock = $pdo->prepare('UPDATE TONKHO SET so_luong = so_luong - ? WHERE ma_bien_the = ?');
    foreach ($items as [$id,$qty,$price,$before]) { $detail->execute([$invoiceId,$id,$qty,$price,$qty*$price]); $stock->execute([$qty,$id]); }
    if ($customer) { $earned = (int) floor($total / 10000); $pdo->prepare('UPDATE KHACHHANG SET tong_chi_tieu=tong_chi_tieu+?, diem_tich_luy=diem_tich_luy-?+? WHERE ma_khach_hang=?')->execute([$total,$pointsUsed,$earned,$customerId]); }
    $pdo->prepare('INSERT INTO THANHTOAN (ma_hoa_don,phuong_thuc,so_tien,ghi_chu) VALUES (?, ?, ?, ?)')->execute([$invoiceId,$paymentMethod,$total,'Thanh toán hóa đơn']);
    $pdo->commit(); echo json_encode(['success' => true, 'ma_hd' => $code]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Không thể hoàn tất thanh toán']);
}
