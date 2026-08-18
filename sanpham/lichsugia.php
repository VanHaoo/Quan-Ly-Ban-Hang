<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('sanpham');

$ma_sp = (int)($_GET['ma_sp'] ?? 0);

if ($ma_sp <= 0) {
    redirect('sanpham/index.php');
}

$stmt = $pdo->prepare("
    SELECT
        sp.ma_sp,
        sp.ten_san_pham
    FROM SANPHAM sp
    WHERE sp.ma_san_pham = ?
");

$stmt->execute([$ma_sp]);
$product = $stmt->fetch();

if (!$product) {
    redirect('sanpham/index.php');
}

$stmt = $pdo->prepare("
    SELECT
        ls.ma_lich_su,
        ls.gia_cu,
        ls.gia_moi,
        ls.ly_do,
        ls.ngay_thay_doi,
        bt.sku,
        bt.ten_bien_the,
        tk.username
    FROM LICHSUGIA ls
    LEFT JOIN BIEN_THESANPHAM bt
        ON ls.ma_bien_the = bt.ma_bien_the
    LEFT JOIN TAIKHOAN tk
        ON ls.nguoi_thay_doi = tk.ma_tai_khoan
    WHERE
        ls.ma_san_pham = ?
        OR bt.ma_san_pham = ?
    ORDER BY ls.ngay_thay_doi DESC, ls.ma_lich_su DESC
");

$stmt->execute([
    $ma_sp,
    $ma_sp
]);

$history = $stmt->fetchAll();

$page_title = 'Lịch sử giá';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">

    <div class="card-header">

        <div>

            <h2>
                <i class="fas fa-history"></i>
                Lịch sử giá
            </h2>

            <div style="color:#64748b;margin-top:5px;">
                <?= htmlspecialchars($product['ma_sp']) ?>
                -
                <?= htmlspecialchars($product['ten_san_pham']) ?>
            </div>

        </div>

        <a
            href="<?= url('sanpham/index.php') ?>"
            class="btn btn-secondary"
        >
            <i class="fas fa-arrow-left"></i>
            Quay lại
        </a>

    </div>

    <div class="card-body">

        <table class="data-table">

            <thead>

                <tr>
                    <th>Thời gian</th>
                    <th>SKU</th>
                    <th>Giá cũ</th>
                    <th>Giá mới</th>
                    <th>Thay đổi</th>
                    <th>Lý do</th>
                    <th>Người thay đổi</th>
                </tr>

            </thead>

            <tbody>

            <?php if (empty($history)): ?>

                <tr>
                    <td colspan="7" class="text-center">
                        Chưa có lịch sử thay đổi giá.
                    </td>
                </tr>

            <?php endif; ?>


            <?php foreach ($history as $item): ?>

                <?php
                    $change = $item['gia_moi'] - $item['gia_cu'];
                ?>

                <tr>

                    <td>
                        <?= formatDate($item['ngay_thay_doi']) ?>
                    </td>

                    <td>

                        <?php if ($item['sku']): ?>

                            <strong>
                                <?= htmlspecialchars($item['sku']) ?>
                            </strong>

                        <?php else: ?>

                            <span class="text-muted">
                                Giá sản phẩm
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= formatMoney($item['gia_cu']) ?>
                    </td>

                    <td>
                        <strong>
                            <?= formatMoney($item['gia_moi']) ?>
                        </strong>
                    </td>

                    <td>

                        <?php if ($change > 0): ?>

                            <span class="text-danger">
                                <i class="fas fa-arrow-up"></i>
                                +<?= formatMoney($change) ?>
                            </span>

                        <?php elseif ($change < 0): ?>

                            <span class="text-success">
                                <i class="fas fa-arrow-down"></i>
                                <?= formatMoney($change) ?>
                            </span>

                        <?php else: ?>

                            <span>
                                Không đổi
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= htmlspecialchars($item['ly_do'] ?? '-') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($item['username'] ?? 'Hệ thống') ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>