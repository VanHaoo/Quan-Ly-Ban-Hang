<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission('sanpham');
$page_title = 'Danh sach san pham';

$stmt = $pdo->query("
    SELECT
        sp.*,
        l.ten_loai,
        th.ten_thuong_hieu,
        d.ten_dvt,

        (
            SELECT COUNT(*)
            FROM BIEN_THESANPHAM bt
            WHERE bt.ma_san_pham = sp.ma_san_pham
        ) AS so_bien_the,

        (
            SELECT MIN(bt.gia_ban)
            FROM BIEN_THESANPHAM bt
            WHERE bt.ma_san_pham = sp.ma_san_pham
              AND bt.trang_thai = 1
        ) AS gia_sku_min,

        (
            SELECT MAX(bt.gia_ban)
            FROM BIEN_THESANPHAM bt
            WHERE bt.ma_san_pham = sp.ma_san_pham
              AND bt.trang_thai = 1
        ) AS gia_sku_max

    FROM SANPHAM sp

    LEFT JOIN LOAISANPHAM l
        ON sp.ma_loai = l.ma_loai

    LEFT JOIN THUONGHIEU th
        ON sp.ma_thuong_hieu = th.ma_thuong_hieu

    LEFT JOIN DONVITINH d
        ON sp.ma_dvt = d.ma_dvt

    ORDER BY sp.ngay_tao DESC
");

$products = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-box"></i> Danh sach san pham</h2>
        <a href="<?= url('sanpham/create.php') ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Them san pham</a>
    </div>
    <div class="card-body">
        <div class="form-group">
            <input type="text" id="searchInput" class="form-control" placeholder="Tim kiem san pham..." onkeyup="searchTable('searchInput', 'productTable')">
        </div>
        <table class="data-table" id="productTable">
            <thead>
                <tr>
                    <th>Ma SP</th><th>Ten san pham</th><th>Loai</th><th>Thuong hieu</th>
                    <th>Bien the</th><th>Gia ban</th><th>Trang thai</th><th>Thao tac</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['ma_sp']) ?></strong></td>
                    <td><?= htmlspecialchars($p['ten_san_pham']) ?></td>
                    <td><?= htmlspecialchars($p['ten_loai'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['ten_thuong_hieu'] ?? '-') ?></td>
                    <td class="text-center"><?= $p['so_bien_the'] ?></td>
                    <td class="text-right">

                        <?php if ($p['gia_sku_min'] !== null): ?>

                            <?php if ((float)$p['gia_sku_min'] == (float)$p['gia_sku_max']): ?>

                                <?= formatMoney($p['gia_sku_min']) ?>

                            <?php else: ?>

                                <?= formatMoney($p['gia_sku_min']) ?>
                                -
                                <?= formatMoney($p['gia_sku_max']) ?>

                            <?php endif; ?>

                        <?php else: ?>

                            <?= formatMoney($p['gia_ban']) ?>

                        <?php endif; ?>

                    </td>
                    <td>

                        <a
                            href="<?= url('sanpham/edit.php?id=' . $p['ma_san_pham']) ?>"
                            class="btn btn-sm btn-secondary btn-action"
                            title="Sửa sản phẩm"
                        >
                            <i class="fas fa-edit"></i>
                        </a>

                        <a
                            href="<?= url('sanpham/bienthe.php?ma_sp=' . $p['ma_san_pham']) ?>"
                            class="btn btn-sm btn-primary btn-action"
                            title="Quản lý SKU"
                        >
                            <i class="fas fa-cubes"></i>
                            SKU
                        </a>

                        <a
                            href="<?= url('sanpham/lichsugia.php?ma_sp=' . $p['ma_san_pham']) ?>"
                            class="btn btn-sm btn-secondary btn-action"
                            title="Lịch sử giá"
                        >
                            <i class="fas fa-history"></i>
                        </a>

                    </td>
                    <td class="text-center">
                        <?php if ($p['trang_thai'] == 1): ?>
                            <span class="text-success"><i class="fas fa-check-circle"></i> Hien</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="fas fa-times-circle"></i> An</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= url('sanpham/edit.php?id=' . $p['ma_san_pham']) ?>" class="btn btn-sm btn-secondary btn-action"><i class="fas fa-edit"></i></a>
                        <a href="<?= url('sanpham/bienthe.php?ma_sp=' . $p['ma_san_pham']) ?>" class="btn btn-sm btn-primary btn-action"><i class="fas fa-cubes"></i> SKU</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>searchTable('searchInput', 'productTable');</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
