<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('nhanvien');
$page_title = 'Nhan vien';

$stmt = $pdo->query("
    SELECT nv.*, cv.ten_chuc_vu, tk.username, vt.ten_vai_tro
    FROM NHANVIEN nv
    LEFT JOIN CHUCVU cv ON nv.ma_chuc_vu = cv.ma_chuc_vu
    LEFT JOIN TAIKHOAN tk ON nv.ma_nhan_vien = tk.ma_nhan_vien
    LEFT JOIN VAITRO vt ON tk.ma_vai_tro = vt.ma_vai_tro
    ORDER BY nv.ngay_tao DESC
");
$staff = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-user-tie"></i> Nhan vien</h2>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr><th>Ma NV</th><th>Ho ten</th><th>SDT</th><th>Chuc vu</th><th>Tai khoan</th><th>Vai tro</th><th>Trang thai</th></tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['ma_nv']) ?></td>
                    <td><strong><?= htmlspecialchars($s['ho_ten']) ?></strong></td>
                    <td><?= htmlspecialchars($s['so_dien_thoai']) ?></td>
                    <td><?= htmlspecialchars($s['ten_chuc_vu'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['username'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['ten_vai_tro'] ?? '-') ?></td>
                    <td class="text-center"><?= $s['trang_thai'] ? '<span class="text-success">Hoat dong</span>' : '<span class="text-danger">Khoa</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
