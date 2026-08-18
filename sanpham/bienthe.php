<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('sanpham');

$ma_sp = (int)($_GET['ma_sp'] ?? 0);

if ($ma_sp <= 0) {
    redirect('sanpham/index.php');
}

$stmt = $pdo->prepare("
    SELECT
        sp.ma_san_pham,
        sp.ma_sp,
        sp.ten_san_pham,
        sp.trang_thai
    FROM SANPHAM sp
    WHERE sp.ma_san_pham = ?
");
$stmt->execute([$ma_sp]);

$product = $stmt->fetch();

if (!$product) {
    redirect('sanpham/index.php');
}

$errors = [];

/*
 * Xóa/ẩn SKU
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sku'])) {

    $ma_bien_the = (int)$_POST['ma_bien_the'];

    try {

        /*
         * Không xóa SKU đã phát sinh hóa đơn/nhập hàng
         * vì các bảng nghiệp vụ đang dùng FK RESTRICT.
         *
         * Thay vào đó chuyển trạng thái = 0.
         */
        $stmt = $pdo->prepare("
            UPDATE BIEN_THESANPHAM
            SET trang_thai = 0
            WHERE ma_bien_the = ?
              AND ma_san_pham = ?
        ");

        $stmt->execute([
            $ma_bien_the,
            $ma_sp
        ]);

        redirect('sanpham/bienthe.php?ma_sp=' . $ma_sp);

    } catch (PDOException $e) {

        $errors[] = 'Không thể thay đổi trạng thái SKU: ' . $e->getMessage();
    }
}

/*
 * Thêm SKU
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sku'])) {

    $sku = trim($_POST['sku'] ?? '');
    $ten_bien_the = trim($_POST['ten_bien_the'] ?? '');
    $gia_ban = (float)($_POST['gia_ban'] ?? 0);
    $ton_toi_thieu = (int)($_POST['ton_toi_thieu'] ?? 5);

    if ($sku === '') {
        $errors[] = 'SKU không được để trống.';
    }

    if ($ten_bien_the === '') {
        $errors[] = 'Tên biến thể không được để trống.';
    }

    if ($gia_ban < 0) {
        $errors[] = 'Giá bán không hợp lệ.';
    }

    if ($ton_toi_thieu < 0) {
        $errors[] = 'Tồn tối thiểu không hợp lệ.';
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO BIEN_THESANPHAM
                (
                    ma_san_pham,
                    sku,
                    ten_bien_the,
                    gia_ban,
                    ton_kho,
                    ton_toi_thieu,
                    trang_thai
                )
                VALUES
                (?, ?, ?, ?, 0, ?, 1)
            ");

            $stmt->execute([
                $ma_sp,
                $sku,
                $ten_bien_the,
                $gia_ban,
                $ton_toi_thieu
            ]);

            $ma_bien_the = (int)$pdo->lastInsertId();

            /*
             * Trigger database sẽ tự tạo TONKHO.
             */

            $stmt = $pdo->prepare("
                INSERT INTO LICHSUGIA
                (
                    ma_bien_the,
                    gia_cu,
                    gia_moi,
                    nguoi_thay_doi,
                    ly_do
                )
                VALUES
                (?, 0, ?, ?, 'Tạo SKU')
            ");

            $stmt->execute([
                $ma_bien_the,
                $gia_ban,
                $user_id
            ]);

            $pdo->commit();

            redirect('sanpham/bienthe.php?ma_sp=' . $ma_sp);

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() == 23000) {
                $errors[] = 'SKU đã tồn tại.';
            } else {
                $errors[] = 'Không thể thêm SKU: ' . $e->getMessage();
            }
        }
    }
}

/*
 * Cập nhật SKU
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sku'])) {

    $ma_bien_the = (int)$_POST['ma_bien_the'];
    $sku = trim($_POST['sku'] ?? '');
    $ten_bien_the = trim($_POST['ten_bien_the'] ?? '');
    $gia_ban = (float)($_POST['gia_ban'] ?? 0);
    $ton_toi_thieu = (int)($_POST['ton_toi_thieu'] ?? 5);
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;

    if ($sku === '' || $ten_bien_the === '') {
        $errors[] = 'SKU và tên biến thể không được để trống.';
    }

    if ($gia_ban < 0 || $ton_toi_thieu < 0) {
        $errors[] = 'Giá bán hoặc tồn tối thiểu không hợp lệ.';
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT gia_ban
                FROM BIEN_THESANPHAM
                WHERE ma_bien_the = ?
                  AND ma_san_pham = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $ma_bien_the,
                $ma_sp
            ]);

            $old = $stmt->fetch();

            if (!$old) {
                throw new Exception('Không tìm thấy SKU.');
            }

            $gia_cu = (float)$old['gia_ban'];

            $stmt = $pdo->prepare("
                UPDATE BIEN_THESANPHAM
                SET
                    sku = ?,
                    ten_bien_the = ?,
                    gia_ban = ?,
                    ton_toi_thieu = ?,
                    trang_thai = ?
                WHERE ma_bien_the = ?
                  AND ma_san_pham = ?
            ");

            $stmt->execute([
                $sku,
                $ten_bien_the,
                $gia_ban,
                $ton_toi_thieu,
                $trang_thai,
                $ma_bien_the,
                $ma_sp
            ]);

            /*
             * BIEN_THESANPHAM chưa có trigger lịch sử giá,
             * nên ghi thủ công khi giá thay đổi.
             */
            if ($gia_cu != $gia_ban) {

                $stmt = $pdo->prepare("
                    INSERT INTO LICHSUGIA
                    (
                        ma_bien_the,
                        gia_cu,
                        gia_moi,
                        nguoi_thay_doi,
                        ly_do
                    )
                    VALUES
                    (?, ?, ?, ?, 'Cập nhật giá SKU')
                ");

                $stmt->execute([
                    $ma_bien_the,
                    $gia_cu,
                    $gia_ban,
                    $user_id
                ]);
            }

            /*
             * Đồng bộ tồn tối thiểu sang TONKHO.
             */
            $stmt = $pdo->prepare("
                UPDATE TONKHO
                SET so_luong_toi_thieu = ?
                WHERE ma_bien_the = ?
            ");

            $stmt->execute([
                $ton_toi_thieu,
                $ma_bien_the
            ]);

            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Không thể cập nhật SKU: ' . $e->getMessage();
        }
    }
}

/*
 * Lấy danh sách SKU
 */
$stmt = $pdo->prepare("
    SELECT
        bt.*,
        COALESCE(tk.so_luong, 0) AS ton_thuc_te
    FROM BIEN_THESANPHAM bt
    LEFT JOIN TONKHO tk
        ON bt.ma_bien_the = tk.ma_bien_the
    WHERE bt.ma_san_pham = ?
    ORDER BY bt.ma_bien_the ASC
");

$stmt->execute([$ma_sp]);
$variants = $stmt->fetchAll();

$page_title = 'Biến thể / SKU';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">

    <div class="card-header">

        <div>
            <h2>
                <i class="fas fa-cubes"></i>
                Biến thể / SKU
            </h2>

            <div style="margin-top:5px;color:#64748b;">
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

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>


        <!-- FORM THÊM SKU -->

        <div style="
            background:#f8fafc;
            padding:20px;
            border-radius:10px;
            margin-bottom:25px;
        ">

            <h3 style="margin-top:0;">
                <i class="fas fa-plus-circle"></i>
                Thêm biến thể
            </h3>

            <form method="POST">

                <div class="form-row">

                    <div class="form-group">

                        <label>SKU <span class="text-danger">*</span></label>

                        <input
                            type="text"
                            name="sku"
                            class="form-control"
                            placeholder="VD: SP001-DO-M"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Tên biến thể <span class="text-danger">*</span></label>

                        <input
                            type="text"
                            name="ten_bien_the"
                            class="form-control"
                            placeholder="VD: Áo thun - Đỏ - M"
                            required
                        >

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label>Giá bán</label>

                        <input
                            type="number"
                            name="gia_ban"
                            class="form-control"
                            min="0"
                            step="1000"
                            value="<?= htmlspecialchars($product['gia_ban'] ?? 0) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Tồn tối thiểu</label>

                        <input
                            type="number"
                            name="ton_toi_thieu"
                            class="form-control"
                            min="0"
                            value="5"
                        >

                    </div>

                </div>

                <button
                    type="submit"
                    name="add_sku"
                    class="btn btn-primary"
                >
                    <i class="fas fa-plus"></i>
                    Thêm SKU
                </button>

            </form>

        </div>


        <!-- DANH SÁCH SKU -->

        <div style="overflow-x:auto;">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>SKU</th>
                        <th>Tên biến thể</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Tồn tối thiểu</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (empty($variants)): ?>

                    <tr>
                        <td colspan="7" class="text-center">
                            Chưa có biến thể nào.
                        </td>
                    </tr>

                <?php endif; ?>


                <?php foreach ($variants as $variant): ?>

                    <tr>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="ma_bien_the"
                                value="<?= $variant['ma_bien_the'] ?>"
                            >

                            <td>

                                <input
                                    type="text"
                                    name="sku"
                                    class="form-control"
                                    value="<?= htmlspecialchars($variant['sku']) ?>"
                                    required
                                >

                            </td>

                            <td>

                                <input
                                    type="text"
                                    name="ten_bien_the"
                                    class="form-control"
                                    value="<?= htmlspecialchars($variant['ten_bien_the']) ?>"
                                    required
                                >

                            </td>

                            <td style="min-width:140px;">

                                <input
                                    type="number"
                                    name="gia_ban"
                                    class="form-control"
                                    min="0"
                                    step="1000"
                                    value="<?= htmlspecialchars($variant['gia_ban']) ?>"
                                >

                            </td>

                            <td class="text-center">

                                <strong>
                                    <?= number_format($variant['ton_thuc_te']) ?>
                                </strong>

                            </td>

                            <td style="min-width:120px;">

                                <input
                                    type="number"
                                    name="ton_toi_thieu"
                                    class="form-control"
                                    min="0"
                                    value="<?= htmlspecialchars($variant['ton_toi_thieu']) ?>"
                                >

                            </td>

                            <td>

                                <label style="display:flex;gap:5px;align-items:center;">

                                    <input
                                        type="checkbox"
                                        name="trang_thai"
                                        value="1"
                                        <?= $variant['trang_thai'] ? 'checked' : '' ?>
                                    >

                                    <?= $variant['trang_thai'] ? 'Hiện' : 'Ẩn' ?>

                                </label>

                            </td>

                            <td>

                                <button
                                    type="submit"
                                    name="update_sku"
                                    class="btn btn-sm btn-primary"
                                    title="Lưu"
                                >
                                    <i class="fas fa-save"></i>
                                </button>

                                <?php if ($variant['trang_thai']): ?>

                                    <button
                                        type="submit"
                                        name="delete_sku"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Ẩn SKU này khỏi hệ thống?')"
                                        title="Ẩn SKU"
                                    >
                                        <i class="fas fa-eye-slash"></i>
                                    </button>

                                <?php endif; ?>

                            </td>

                        </form>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>