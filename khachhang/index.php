<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('khachhang');
$page_title = 'Khach hang';

$stmt = $pdo->query("SELECT * FROM KHACHHANG ORDER BY ngay_tao DESC");
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-users"></i> Danh sach khach hang</h2>
        <a href="<?= url('khachhang/create.php') ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Them</a>
    </div>
    <div class="card-body">
        <table class="data-table" id="customerTable">
            <thead>
                <tr><th>Ma KH</th><th>Ho ten</th><th>SDT</th><th>Tong chi tieu</th><th>Diem</th><th>Thao tac</th></tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['ma_kh']) ?></strong></td>
                    <td><?= htmlspecialchars($c['ho_ten']) ?></td>
                    <td><?= htmlspecialchars($c['so_dien_thoai']) ?></td>
                    <td class="text-right"><?= formatMoney($c['tong_chi_tieu']) ?></td>
                    <td class="text-center"><strong><?= $c['diem_tich_luy'] ?></strong></td>
                    <td>
                        <a href="<?= url('khachhang/detail.php?id=' . $c['ma_khach_hang']) ?>" class="btn btn-sm btn-secondary btn-action"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
