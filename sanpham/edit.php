<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('sanpham');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('sanpham/index.php');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM SANPHAM
    WHERE ma_san_pham = ?
");
$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {
    redirect('sanpham/index.php');
}

$categories = $pdo->query("
    SELECT ma_loai, ten_loai
    FROM LOAISANPHAM
    WHERE trang_thai = 1
    ORDER BY ten_loai
")->fetchAll();

$brands = $pdo->query("
    SELECT ma_thuong_hieu, ten_thuong_hieu
    FROM THUONGHIEU
    WHERE trang_thai = 1
    ORDER BY ten_thuong_hieu
")->fetchAll();

$units = $pdo->query("
    SELECT ma_dvt, ten_dvt
    FROM DONVITINH
    ORDER BY ten_dvt
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ten_san_pham   = trim($_POST['ten_san_pham'] ?? '');
    $ma_loai        = (int)($_POST['ma_loai'] ?? 0);
    $ma_thuong_hieu = (int)($_POST['ma_thuong_hieu'] ?? 0);
    $ma_dvt         = (int)($_POST['ma_dvt'] ?? 0);
    $gia_ban        = (float)($_POST['gia_ban'] ?? 0);
    $mo_ta          = trim($_POST['mo_ta'] ?? '');
    $trang_thai     = isset($_POST['trang_thai']) ? 1 : 0;

    if ($ten_san_pham === '') {
        $errors[] = 'Tên sản phẩm không được để trống.';
    }

    if ($ma_loai <= 0) {
        $errors[] = 'Vui lòng chọn loại sản phẩm.';
    }

    if ($ma_thuong_hieu <= 0) {
        $errors[] = 'Vui lòng chọn thương hiệu.';
    }

    if ($ma_dvt <= 0) {
        $errors[] = 'Vui lòng chọn đơn vị tính.';
    }

    if ($gia_ban < 0) {
        $errors[] = 'Giá bán không hợp lệ.';
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $gia_cu = (float)$product['gia_ban'];

            $stmt = $pdo->prepare("
                UPDATE SANPHAM
                SET
                    ten_san_pham = ?,
                    ma_loai = ?,
                    ma_thuong_hieu = ?,
                    ma_dvt = ?,
                    mo_ta = ?,
                    gia_ban = ?,
                    trang_thai = ?
                WHERE ma_san_pham = ?
            ");

            $stmt->execute([
                $ten_san_pham,
                $ma_loai,
                $ma_thuong_hieu,
                $ma_dvt,
                $mo_ta !== '' ? $mo_ta : null,
                $gia_ban,
                $trang_thai,
                $id
            ]);

            /*
             * Trigger hiện tại của database đã ghi lịch sử
             * khi SANPHAM.gia_ban thay đổi.
             *
             * Tuy nhiên trigger chưa ghi người thay đổi,
             * nên cập nhật bản ghi lịch sử vừa tạo.
             */
            if ($gia_cu != $gia_ban) {

                $stmt = $pdo->prepare("
                    UPDATE LICHSUGIA
                    SET nguoi_thay_doi = ?
                    WHERE ma_san_pham = ?
                    ORDER BY ma_lich_su DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    $user_id,
                    $id
                ]);
            }

            $pdo->commit();

            redirect('sanpham/edit.php?id=' . $id . '&success=1');

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Không thể cập nhật sản phẩm: ' . $e->getMessage();
        }
    }

    /*
     * Hiển thị lại dữ liệu vừa nhập nếu có lỗi.
     */
    $product['ten_san_pham'] = $ten_san_pham;
    $product['ma_loai'] = $ma_loai;
    $product['ma_thuong_hieu'] = $ma_thuong_hieu;
    $product['ma_dvt'] = $ma_dvt;
    $product['gia_ban'] = $gia_ban;
    $product['mo_ta'] = $mo_ta;
    $product['trang_thai'] = $trang_thai;
}

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS so_bien_the,
        COALESCE(SUM(tk.so_luong), 0) AS tong_ton
    FROM BIEN_THESANPHAM bt
    LEFT JOIN TONKHO tk
        ON bt.ma_bien_the = tk.ma_bien_the
    WHERE bt.ma_san_pham = ?
");
$stmt->execute([$id]);
$inventory = $stmt->fetch();

$page_title = 'Chỉnh sửa sản phẩm';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">

    <div class="card-header">

        <h2>
            <i class="fas fa-edit"></i>
            Chỉnh sửa sản phẩm
        </h2>

        <div style="display:flex;gap:8px;">

            <a
                href="<?= url('sanpham/bienthe.php?ma_sp=' . $id) ?>"
                class="btn btn-primary"
            >
                <i class="fas fa-cubes"></i>
                Quản lý SKU
            </a>

            <a
                href="<?= url('sanpham/index.php') ?>"
                class="btn btn-secondary"
            >
                <i class="fas fa-arrow-left"></i>
                Quay lại
            </a>

        </div>

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

        <?php if (isset($_GET['success'])): ?>

            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Cập nhật sản phẩm thành công.
            </div>

        <?php endif; ?>

        <div class="form-row">

            <div class="form-group">

                <label>Mã sản phẩm</label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars($product['ma_sp']) ?>"
                    readonly
                >

            </div>

            <div class="form-group">

                <label>Tổng SKU</label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= (int)$inventory['so_bien_the'] ?>"
                    readonly
                >

            </div>

        </div>

        <form method="POST">

            <div class="form-row">

                <div class="form-group">

                    <label>Tên sản phẩm <span class="text-danger">*</span></label>

                    <input
                        type="text"
                        name="ten_san_pham"
                        class="form-control"
                        value="<?= htmlspecialchars($product['ten_san_pham']) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Giá bán cơ bản</label>

                    <input
                        type="number"
                        name="gia_ban"
                        class="form-control"
                        min="0"
                        step="1000"
                        value="<?= htmlspecialchars($product['gia_ban']) ?>"
                    >

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Loại sản phẩm</label>

                    <select name="ma_loai" class="form-control" required>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= $category['ma_loai'] ?>"
                                <?= $product['ma_loai'] == $category['ma_loai'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($category['ten_loai']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Thương hiệu</label>

                    <select name="ma_thuong_hieu" class="form-control" required>

                        <?php foreach ($brands as $brand): ?>

                            <option
                                value="<?= $brand['ma_thuong_hieu'] ?>"
                                <?= $product['ma_thuong_hieu'] == $brand['ma_thuong_hieu'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($brand['ten_thuong_hieu']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Đơn vị tính</label>

                    <select name="ma_dvt" class="form-control" required>

                        <?php foreach ($units as $unit): ?>

                            <option
                                value="<?= $unit['ma_dvt'] ?>"
                                <?= $product['ma_dvt'] == $unit['ma_dvt'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($unit['ten_dvt']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Trạng thái</label>

                    <label style="display:flex;align-items:center;gap:8px;margin-top:10px;">

                        <input
                            type="checkbox"
                            name="trang_thai"
                            value="1"
                            <?= $product['trang_thai'] ? 'checked' : '' ?>
                        >

                        Đang kinh doanh

                    </label>

                </div>

            </div>

            <div class="form-group">

                <label>Mô tả</label>

                <textarea
                    name="mo_ta"
                    class="form-control"
                    rows="4"
                ><?= htmlspecialchars($product['mo_ta'] ?? '') ?></textarea>

            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">

                <a
                    href="<?= url('sanpham/index.php') ?>"
                    class="btn btn-secondary"
                >
                    Hủy
                </a>

                <button type="submit" class="btn btn-primary">

                    <i class="fas fa-save"></i>
                    Lưu thay đổi

                </button>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>