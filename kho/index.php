<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('kho');
$page_title = 'Ton kho';

$stmt = $pdo->query("
    SELECT tk.*, bt.sku, bt.ten_bien_the, bt.ton_toi_thieu, sp.ten_san_pham
    FROM TONKHO tk
    JOIN BIEN_THESANPHAM bt ON tk.ma_bien_the = bt.ma_bien_the
    JOIN SANPHAM sp ON bt.ma_san_pham = sp.ma_san_pham
    ORDER BY sp.ten_san_pham
");
$items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-warehouse"></i> Ton kho</h2>
    </div>
    <div class="card-body">
        <table class="data-table" id="stockTable">
            <thead>
                <tr><th>SKU</th><th>San pham</th><th>Bien the</th><th>Ton kho</th><th>Toi thieu</th><th>Trang thai</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $status = $item['so_luong'] <= 0 ? 'HET_HANG' : ($item['so_luong'] <= $item['ton_toi_thieu'] ? 'SAP_HET' : 'DU');
                    $statusClass = $status === 'HET_HANG' ? 'text-danger' : ($status === 'SAP_HET' ? 'text-warning' : 'text-success');
                    $statusText = $status === 'HET_HANG' ? 'Het hang' : ($status === 'SAP_HET' ? 'Sap het' : 'Du');
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['sku']) ?></td>
                    <td><?= htmlspecialchars($item['ten_san_pham']) ?></td>
                    <td><?= htmlspecialchars($item['ten_bien_the']) ?></td>
                    <td class="text-center"><strong><?= $item['so_luong'] ?></strong></td>
                    <td class="text-center"><?= $item['ton_toi_thieu'] ?></td>
                    <td class="text-center <?= $statusClass ?>"><i class="fas fa-circle" style="font-size:8px;"></i> <?= $statusText ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
