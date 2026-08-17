<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('trahang');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ============================================================
// action=search
// ============================================================
if ($action === 'search') {
    $ma_hd = trim($_GET['ma_hd'] ?? '');
    if ($ma_hd === '') {
        echo json_encode(['success' => false, 'message' => 'Thiếu mã hóa đơn']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT hd.ma_hoa_don, hd.ma_hd, hd.ngay_tao, hd.trang_thai, kh.ho_ten AS ten_khach
        FROM HOADON hd
        LEFT JOIN KHACHHANG kh ON hd.ma_khach_hang = kh.ma_khach_hang
        WHERE hd.ma_hd = ?
    ");
    $stmt->execute([$ma_hd]);
    $hoadon = $stmt->fetch();

    if (!$hoadon) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn "' . $ma_hd . '"']);
        exit;
    }
    if ($hoadon['trang_thai'] === 'HUY') {
        echo json_encode(['success' => false, 'message' => 'Hóa đơn đã bị hủy, không thể trả hàng']);
        exit;
    }
    $hoadon['ngay_tao'] = date('H:i d/m/Y', strtotime($hoadon['ngay_tao']));

    $stmt = $pdo->prepare("
        SELECT ct.ma_bien_the, bt.sku, sp.ten_san_pham, bt.ten_bien_the, ct.so_luong, ct.don_gia,
               COALESCE((
                   SELECT SUM(cttr.so_luong)
                   FROM CHITIETTRAHANG cttr
                   JOIN TRAHANG th ON cttr.ma_tra_hang = th.ma_tra_hang
                   WHERE th.ma_hoa_don = hd.ma_hoa_don AND cttr.ma_bien_the = ct.ma_bien_the AND th.trang_thai = 'HOANTAT'
               ), 0) AS da_tra
        FROM CHITIETHOADON ct
        JOIN BIEN_THESANPHAM bt ON ct.ma_bien_the = bt.ma_bien_the
        JOIN SANPHAM sp ON bt.ma_san_pham = sp.ma_san_pham
        JOIN HOADON hd ON ct.ma_hoa_don = hd.ma_hoa_don
        WHERE ct.ma_hoa_don = ?
    ");
    $stmt->execute([$hoadon['ma_hoa_don']]);
    $chi_tiet = $stmt->fetchAll();

    echo json_encode(['success' => true, 'hoadon' => $hoadon, 'chi_tiet' => $chi_tiet]);
    exit;
}

// ============================================================
// action=confirm : tạo TRAHANG (CHO_XAC_NHAN) + CHITIETTRAHANG, rồi CALL SP_TRAHANG để hoàn tất
// ============================================================
if ($action === 'confirm') {
    $data = json_decode(file_get_contents('php://input'), true);
    $ma_hd = trim($data['ma_hd'] ?? '');
    $items = $data['items'] ?? [];
    $ly_do = trim($data['ly_do'] ?? '');

    if ($ma_hd === '' || empty($items) || $ly_do === '') {
        echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu trả hàng']);
        exit;
    }

    $ma_tra_hang = null;
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT ma_hoa_don, ma_khach_hang, trang_thai FROM HOADON WHERE ma_hd = ?");
        $stmt->execute([$ma_hd]);
        $hoadon = $stmt->fetch();
        if (!$hoadon || $hoadon['trang_thai'] === 'HUY') {
            throw new Exception('Hóa đơn không hợp lệ để trả hàng');
        }

        // Tạo phiếu trả nháp (CHO_XAC_NHAN)
        $stmt = $pdo->prepare("
            INSERT INTO TRAHANG (ma_th, ma_hoa_don, ma_khach_hang, ly_do, trang_thai, nguoi_tao)
            VALUES ('TEMP', :ma_hd, :ma_kh, :ly_do, 'CHO_XAC_NHAN', :nguoi)
        ");
        $stmt->execute(['ma_hd' => $hoadon['ma_hoa_don'], 'ma_kh' => $hoadon['ma_khach_hang'], 'ly_do' => $ly_do, 'nguoi' => $_SESSION['user_id']]);
        $ma_tra_hang = $pdo->lastInsertId();
        $ma_th = 'TH' . str_pad($ma_tra_hang, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE TRAHANG SET ma_th = ? WHERE ma_tra_hang = ?")->execute([$ma_th, $ma_tra_hang]);

        // Chi tiết trả hàng - lấy đơn giá GỐC từ CHITIETHOADON, không tin giá client gửi lên
        $stmt_gia = $pdo->prepare("SELECT don_gia FROM CHITIETHOADON WHERE ma_hoa_don = ? AND ma_bien_the = ?");
        $stmt_ct = $pdo->prepare("
            INSERT INTO CHITIETTRAHANG (ma_tra_hang, ma_bien_the, so_luong, don_gia_hoan, thanh_tien)
            VALUES (:ma_th, :ma_bt, :sl, :dg, :tt)
        ");
        foreach ($items as $item) {
            $ma_bt = (int)$item['id'];
            $sl = (int)$item['qty'];
            if ($sl <= 0) continue;

            $stmt_gia->execute([$hoadon['ma_hoa_don'], $ma_bt]);
            $row = $stmt_gia->fetch();
            if (!$row) throw new Exception('Sản phẩm (id ' . $ma_bt . ') không thuộc hóa đơn này');

            $stmt_ct->execute([
                'ma_th' => $ma_tra_hang, 'ma_bt' => $ma_bt, 'sl' => $sl,
                'dg' => $row['don_gia'], 'tt' => $sl * $row['don_gia']
            ]);
        }

        $pdo->commit();

        // ===== Gọi SP_TRAHANG để hoàn tất: hoàn kho, cộng/trừ điểm, cập nhật trạng thái =====
        // SP tự quản lý transaction riêng, không bọc beginTransaction() của PHP quanh lời gọi này.
        $stmt = $pdo->prepare("CALL SP_TRAHANG(:ma_th, :ma_hd, :ly_do, :nguoi)");
        $stmt->execute(['ma_th' => $ma_th, 'ma_hd' => $ma_hd, 'ly_do' => $ly_do, 'nguoi' => $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'ma_th' => $ma_th]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Dọn phiếu trả nháp nếu SP thất bại sau khi đã tạo TRAHANG/CHITIETTRAHANG
        if ($ma_tra_hang) {
            $pdo->prepare("DELETE FROM CHITIETTRAHANG WHERE ma_tra_hang = ?")->execute([$ma_tra_hang]);
            $pdo->prepare("DELETE FROM TRAHANG WHERE ma_tra_hang = ? AND trang_thai = 'CHO_XAC_NHAN'")->execute([$ma_tra_hang]);
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);