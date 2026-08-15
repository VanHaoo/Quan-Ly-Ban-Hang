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

    // ===== FIX #1: KHÓA & KIỂM TRA TỒN KHO Ở SERVER (không tin dữ liệu từ client) =====
    $stmt_lock = $pdo->prepare("SELECT so_luong FROM TONKHO WHERE ma_bien_the = ? FOR UPDATE");
    $ton_hien_tai = [];
    foreach ($cart as $item) {
        $stmt_lock->execute([$item['id']]);
        $row = $stmt_lock->fetch();
        if (!$row) {
            throw new Exception('Sản phẩm không tồn tại hoặc đã bị xóa (SKU id: ' . $item['id'] . ')');
        }
        if ((int)$item['qty'] <= 0) {
            throw new Exception('Số lượng không hợp lệ');
        }
        if ($row['so_luong'] < $item['qty']) {
            throw new Exception('Sản phẩm "' . $item['name'] . '" không đủ tồn kho (còn ' . $row['so_luong'] . ')');
        }
        $ton_hien_tai[$item['id']] = $row['so_luong'];
    }

    // ===== FIX #2: KIỂM TRA ĐIỂM KHÁCH HÀNG Ở SERVER =====
    $diem_hien_tai = 0;
    if ($customer_id) {
        $stmt_kh = $pdo->prepare("SELECT diem_tich_luy FROM KHACHHANG WHERE ma_khach_hang = ? FOR UPDATE");
        $stmt_kh->execute([$customer_id]);
        $kh_row = $stmt_kh->fetch();
        if (!$kh_row) {
            throw new Exception('Khách hàng không tồn tại');
        }
        $diem_hien_tai = (int)$kh_row['diem_tich_luy'];
        if ($points_used > $diem_hien_tai) {
            throw new Exception('Khách chỉ có ' . $diem_hien_tai . ' điểm, không đủ để dùng ' . $points_used . ' điểm');
        }
    } elseif ($points_used > 0) {
        throw new Exception('Chỉ khách hàng đã đăng ký mới dùng được điểm tích lũy');
    }

    // Tính tổng tạm tính
    $tong_tam_tinh = 0;
    foreach ($cart as $item) {
        $tong_tam_tinh += $item['price'] * $item['qty'];
    }

    // Tính giảm giá (khuyến mãi) - validate lại ở server, không tin data-* từ client
    $giam_gia = 0;
    if ($promotion_id) {
        $stmt = $pdo->prepare("SELECT * FROM KHUYENMAI WHERE ma_khuyen_mai = ? AND trang_thai = 1
                                AND ngay_bat_dau <= CURDATE() AND ngay_ket_thuc >= CURDATE()");
        $stmt->execute([$promotion_id]);
        $km = $stmt->fetch();
        if (!$km) {
            throw new Exception('Khuyến mãi không còn hiệu lực');
        }
        if ($tong_tam_tinh >= $km['dieu_kien_toi_thieu']) {
            $giam_gia = $km['loai_giam'] === 'PHANTRAM'
                ? $tong_tam_tinh * ($km['gia_tri_giam'] / 100)
                : $km['gia_tri_giam'];
        }
    }

    $diem_quy_doi = $points_used * 100;
    $tong_thanh_toan = max(0, $tong_tam_tinh - $giam_gia - $diem_quy_doi);

    // Lấy mã nhân viên từ session
    $stmt = $pdo->prepare("SELECT ma_nhan_vien FROM TAIKHOAN WHERE ma_tai_khoan = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $nv = $stmt->fetch();
    $ma_nv = $nv['ma_nhan_vien'] ?? 1;

    // ===== FIX #3: Thêm hóa đơn TRƯỚC, sinh ma_hd SAU dựa trên lastInsertId (không race condition) =====
    $stmt = $pdo->prepare("
        INSERT INTO HOADON (ma_hd, ma_khach_hang, ma_nhan_vien, tong_tam_tinh, giam_gia, diem_su_dung, diem_quy_doi, tong_thanh_toan, trang_thai)
        VALUES (:ma_hd, :ma_kh, :ma_nv, :tong_tam, :giam_gia, :diem_sd, :diem_qd, :tong_tt, 'HOANTAT')
    ");
    $stmt->execute([
        'ma_hd' => 'TEMP',   // placeholder, cập nhật lại ngay bên dưới
        'ma_kh' => $customer_id,
        'ma_nv' => $ma_nv,
        'tong_tam' => $tong_tam_tinh,
        'giam_gia' => $giam_gia,
        'diem_sd' => $points_used,
        'diem_qd' => $diem_quy_doi,
        'tong_tt' => $tong_thanh_toan
    ]);
    $ma_hoa_don = $pdo->lastInsertId();
    $ma_hd = 'HD' . str_pad($ma_hoa_don, 3, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE HOADON SET ma_hd = ? WHERE ma_hoa_don = ?")->execute([$ma_hd, $ma_hoa_don]);

    // Thêm chi tiết hóa đơn
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

    // Thêm thanh toán
    $pdo->prepare("
        INSERT INTO THANHTOAN (ma_hoa_don, phuong_thuc, so_tien, ghi_chu)
        VALUES (:ma_hd, :pt, :tien, 'Thanh toan POS')
    ")->execute(['ma_hd' => $ma_hoa_don, 'pt' => $payment_method, 'tien' => $tong_thanh_toan]);

    // Trừ tồn kho + ghi lịch sử kho (dùng lại $ton_hien_tai đã khóa & đọc ở trên, không SELECT lại)
    $stmt_upd = $pdo->prepare("UPDATE TONKHO SET so_luong = so_luong - :sl WHERE ma_bien_the = :ma_bt");
    $stmt_ls = $pdo->prepare("
        INSERT INTO LICHSUTONKHO (ma_bien_the, loai_giao_dich, so_luong_truoc, so_luong_thay_doi, so_luong_sau, ma_hoa_don, ghi_chu, nguoi_thuc_hien)
        VALUES (:ma_bt, 'BAN', :truoc, :thay_doi, :sau, :ma_hd, 'Ban hang POS', :nguoi)
    ");
    foreach ($cart as $item) {
        $stmt_upd->execute(['sl' => $item['qty'], 'ma_bt' => $item['id']]);
        $truoc = $ton_hien_tai[$item['id']];
        $sau = $truoc - $item['qty'];
        $stmt_ls->execute([
            'ma_bt' => $item['id'],
            'truoc' => $truoc,
            'thay_doi' => -$item['qty'],
            'sau' => $sau,
            'ma_hd' => $ma_hoa_don,
            'nguoi' => $_SESSION['user_id']
        ]);
    }

    // Cập nhật điểm khách hàng
    if ($customer_id) {
        $diem_cong = (int)floor($tong_thanh_toan / 10000);
        $diem_sau = $diem_hien_tai + $diem_cong - $points_used;

        $pdo->prepare("
            UPDATE KHACHHANG SET tong_chi_tieu = tong_chi_tieu + :tien, diem_tich_luy = :diem_sau
            WHERE ma_khach_hang = :ma_kh
        ")->execute(['tien' => $tong_thanh_toan, 'diem_sau' => $diem_sau, 'ma_kh' => $customer_id]);

        if ($points_used > 0) {
            $pdo->prepare("
                INSERT INTO LICHSUTICHDIEM (ma_khach_hang, loai_giao_dich, diem_truoc, diem_thay_doi, diem_sau, ma_hoa_don, ghi_chu)
                VALUES (:ma_kh, 'SUDUNG', :truoc, :thay_doi, :sau, :ma_hd, 'Su dung diem khi mua hang')
            ")->execute([
                'ma_kh' => $customer_id, 'truoc' => $diem_hien_tai, 'thay_doi' => -$points_used,
                'sau' => $diem_hien_tai - $points_used, 'ma_hd' => $ma_hoa_don
            ]);
        }
        if ($diem_cong > 0) {
            $pdo->prepare("
                INSERT INTO LICHSUTICHDIEM (ma_khach_hang, loai_giao_dich, diem_truoc, diem_thay_doi, diem_sau, ma_hoa_don, ghi_chu)
                VALUES (:ma_kh, 'TICH', :truoc, :thay_doi, :sau, :ma_hd, 'Tich diem tu hoa don')
            ")->execute([
                'ma_kh' => $customer_id, 'truoc' => $diem_hien_tai - $points_used, 'thay_doi' => $diem_cong,
                'sau' => $diem_sau, 'ma_hd' => $ma_hoa_don
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'ma_hd' => $ma_hd]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}