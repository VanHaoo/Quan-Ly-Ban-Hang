<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('banhang');
$page_title = 'Lịch sử hóa đơn';

$tu_ngay = $_GET['tu_ngay'] ?? date('Y-m-d', strtotime('-7 day'));
$den_ngay = $_GET['den_ngay'] ?? date('Y-m-d');
$trang_thai = $_GET['trang_thai'] ?? '';
$tu_khoa = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = ["DATE(hd.ngay_tao) BETWEEN :tu_ngay AND :den_ngay"];
$params = ['tu_ngay' => $tu_ngay, 'den_ngay' => $den_ngay];

if ($trang_thai !== '') {
    $where[] = "hd.trang_thai = :trang_thai";
    $params['trang_thai'] = $trang_thai;
}
if ($tu_khoa !== '') {
    $where[] = "(hd.ma_hd LIKE :tu_khoa OR kh.ho_ten LIKE :tu_khoa)";
    $params['tu_khoa'] = '%' . $tu_khoa . '%';
}
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM HOADON hd LEFT JOIN KHACHHANG kh ON hd.ma_khach_hang = kh.ma_khach_hang WHERE $where_sql");
$stmt->execute($params);
$total_rows = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$stmt = $pdo->prepare("
    SELECT hd.ma_hoa_don, hd.ma_hd, hd.ngay_tao, hd.tong_thanh_toan, hd.trang_thai,
           kh.ho_ten AS ten_khach, nv.ho_ten AS ten_nv
    FROM HOADON hd
    LEFT JOIN KHACHHANG kh ON hd.ma_khach_hang = kh.ma_khach_hang
    JOIN NHANVIEN nv ON hd.ma_nhan_vien = nv.ma_nhan_vien
    WHERE $where_sql
    ORDER BY hd.ngay_tao DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="filter-bar">
    <form method="get" class="filter-form">
        <div class="filter-field"><label>Từ ngày</label><input type="date" name="tu_ngay" value="<?= htmlspecialchars($tu_ngay) ?>"></div>
        <div class="filter-field"><label>Đến ngày</label><input type="date" name="den_ngay" value="<?= htmlspecialchars($den_ngay) ?>"></div>
        <div class="filter-field">
            <label>Trạng thái</label>
            <select name="trang_thai">
                <option value="">-- Tất cả --</option>
                <option value="HOANTAT" <?= $trang_thai === 'HOANTAT' ? 'selected' : '' ?>>Hoàn tất</option>
                <option value="TRAHANG" <?= $trang_thai === 'TRAHANG' ? 'selected' : '' ?>>Đã trả 1 phần/toàn bộ</option>
                <option value="HUY" <?= $trang_thai === 'HUY' ? 'selected' : '' ?>>Đã hủy</option>
            </select>
        </div>
        <div class="filter-field grow">
            <label>Tìm mã HD / tên khách</label>
            <input type="text" name="q" value="<?= htmlspecialchars($tu_khoa) ?>" placeholder="VD: HD012 hoặc Nguyễn Văn A">
        </div>
        <div class="filter-field"><button type="submit" class="btn-primary"><i class="fas fa-search"></i> Lọc</button></div>
    </form>
</div>

<div class="dashboard-card">
    <table class="data-table">
        <thead>
            <tr><th>Mã HD</th><th>Thời gian</th><th>Khách hàng</th><th>Nhân viên</th><th class="text-right">Tổng tiền</th><th>Trạng thái</th><th></th></tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
            <tr class="empty-row"><td colspan="7"><div class="empty-state"><i class="fas fa-inbox"></i><span>Không có hóa đơn nào khớp bộ lọc</span></div></td></tr>
            <?php else: foreach ($invoices as $hd):
                $badge = ['HOANTAT' => ['success', 'Hoàn tất'], 'TRAHANG' => ['warning', 'Đã trả hàng'], 'HUY' => ['danger', 'Đã hủy']][$hd['trang_thai']] ?? ['secondary', $hd['trang_thai']];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($hd['ma_hd']) ?></strong></td>
                <td><?= date('H:i d/m/Y', strtotime($hd['ngay_tao'])) ?></td>
                <td><?= htmlspecialchars($hd['ten_khach'] ?? 'Khách lẻ') ?></td>
                <td><?= htmlspecialchars($hd['ten_nv']) ?></td>
                <td class="text-right"><?= number_format($hd['tong_thanh_toan'], 0, ',', '.') ?> VNĐ</td>
                <td><span class="badge badge-<?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                <td><button class="btn-icon" onclick="xemChiTiet(<?= $hd['ma_hoa_don'] ?>)" title="Xem chi tiết"><i class="fas fa-eye"></i></button></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a class="<?= $p === $page ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div id="modalChiTiet" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-header"><h3>Chi tiết hóa đơn</h3><button onclick="dongModal()"><i class="fas fa-times"></i></button></div>
        <div class="modal-body" id="modalBody"><div class="loading">Đang tải...</div></div>
    </div>
</div>

<script>
function xemChiTiet(maHoaDon) {
    document.getElementById('modalChiTiet').style.display = 'flex';
    document.getElementById('modalBody').innerHTML = '<div class="loading">Đang tải...</div>';
    fetch('<?= url('banhang/api_hoadon_detail.php') ?>?id=' + maHoaDon)
        .then(r => r.json())
        .then(result => {
            if (!result.success) { document.getElementById('modalBody').innerHTML = '<p class="text-danger">' + result.message + '</p>'; return; }
            const hd = result.hoadon;
            let html = `<p><strong>Mã HD:</strong> ${hd.ma_hd} &nbsp; <strong>Khách:</strong> ${hd.ten_khach || 'Khách lẻ'}</p>
                <p><strong>Nhân viên:</strong> ${hd.ten_nv} &nbsp; <strong>Thời gian:</strong> ${hd.ngay_tao}</p>
                <table class="data-table compact"><thead><tr><th>SKU</th><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>`;
            result.chi_tiet.forEach(ct => {
                html += `<tr><td>${ct.sku}</td><td>${ct.ten_san_pham} - ${ct.ten_bien_the}</td>
                         <td class="text-center">${ct.so_luong}</td>
                         <td class="text-right">${Number(ct.don_gia).toLocaleString('vi-VN')}</td>
                         <td class="text-right">${Number(ct.thanh_tien).toLocaleString('vi-VN')}</td></tr>`;
            });
            html += `</tbody></table><div class="modal-summary">
                <div>Tạm tính: ${Number(hd.tong_tam_tinh).toLocaleString('vi-VN')} VNĐ</div>
                <div>Giảm giá: ${Number(hd.giam_gia).toLocaleString('vi-VN')} VNĐ</div>
                <div>Điểm dùng: ${hd.diem_su_dung} điểm</div>
                <div class="total"><strong>Tổng thanh toán: ${Number(hd.tong_thanh_toan).toLocaleString('vi-VN')} VNĐ</strong></div></div>`;
            document.getElementById('modalBody').innerHTML = html;
        })
        .catch(() => { document.getElementById('modalBody').innerHTML = '<p class="text-danger">Lỗi tải dữ liệu</p>'; });
}
function dongModal() { document.getElementById('modalChiTiet').style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>