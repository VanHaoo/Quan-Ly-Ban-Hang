<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('sanpham');

$page_title = 'Thêm sản phẩm';

$errors = [];

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

            /*
             * Sinh mã sản phẩm dạng SP001, SP002...
             */
            $stmt = $pdo->query("
                SELECT COALESCE(
                    MAX(CAST(SUBSTRING(ma_sp, 3) AS UNSIGNED)),
                    0
                ) + 1 AS next_number
                FROM SANPHAM
                WHERE ma_sp REGEXP '^SP[0-9]+$'
            ");

            $next_number = (int)$stmt->fetchColumn();
            $ma_sp = 'SP' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO SANPHAM
                (
                    ma_sp,
                    ten_san_pham,
                    ma_loai,
                    ma_thuong_hieu,
                    ma_dvt,
                    mo_ta,
                    gia_ban,
                    trang_thai
                )
                VALUES
                (
                    :ma_sp,
                    :ten_san_pham,
                    :ma_loai,
                    :ma_thuong_hieu,
                    :ma_dvt,
                    :mo_ta,
                    :gia_ban,
                    :trang_thai
                )
            ");

            $stmt->execute([
                'ma_sp'          => $ma_sp,
                'ten_san_pham'   => $ten_san_pham,
                'ma_loai'        => $ma_loai,
                'ma_thuong_hieu' => $ma_thuong_hieu,
                'ma_dvt'         => $ma_dvt,
                'mo_ta'          => $mo_ta !== '' ? $mo_ta : null,
                'gia_ban'        => $gia_ban,
                'trang_thai'     => $trang_thai
            ]);

            $ma_san_pham = (int)$pdo->lastInsertId();

            /*
             * Ghi lịch sử giá ban đầu.
             */
            $stmt = $pdo->prepare("
                INSERT INTO LICHSUGIA
                (
                    ma_san_pham,
                    gia_cu,
                    gia_moi,
                    nguoi_thay_doi,
                    ly_do
                )
                VALUES
                (
                    :ma_san_pham,
                    0,
                    :gia_moi,
                    :nguoi_thay_doi,
                    'Tạo sản phẩm'
                )
            ");

            $stmt->execute([
                'ma_san_pham'   => $ma_san_pham,
                'gia_moi'       => $gia_ban,
                'nguoi_thay_doi'=> $user_id
            ]);

            $pdo->commit();

            redirect('sanpham/edit.php?id=' . $ma_san_pham);

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() == 23000) {
                $errors[] = 'Mã sản phẩm đã tồn tại. Vui lòng thử lại.';
            } else {
                $errors[] = 'Không thể thêm sản phẩm: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">

    <div class="card-header">
        <h2>
            <i class="fas fa-box"></i>
            Thêm sản phẩm
        </h2>

        <a href="<?= url('sanpham/index.php') ?>" class="btn btn-secondary">
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

        <form method="POST">

            <div class="form-row">

                <div class="form-group">
                    <label>Tên sản phẩm <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="ten_san_pham"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['ten_san_pham'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Giá bán</label>
                    <input
                        type="number"
                        name="gia_ban"
                        class="form-control"
                        min="0"
                        step="1000"
                        value="<?= htmlspecialchars($_POST['gia_ban'] ?? '0') ?>"
                    >
                </div>

            </div>

            <div class="form-row">

                <div class="form-group">
                    <label>Loại sản phẩm <span class="text-danger">*</span></label>

                    <select name="ma_loai" class="form-control" required>
                        <option value="">-- Chọn loại --</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= $category['ma_loai'] ?>"
                                <?= (($_POST['ma_loai'] ?? '') == $category['ma_loai']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($category['ten_loai']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Thương hiệu <span class="text-danger">*</span></label>

                    <select name="ma_thuong_hieu" class="form-control" required>
                        <option value="">-- Chọn thương hiệu --</option>

                        <?php foreach ($brands as $brand): ?>
                            <option
                                value="<?= $brand['ma_thuong_hieu'] ?>"
                                <?= (($_POST['ma_thuong_hieu'] ?? '') == $brand['ma_thuong_hieu']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($brand['ten_thuong_hieu']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <div class="form-row">

                <div class="form-group">
                    <label>Đơn vị tính <span class="text-danger">*</span></label>

                    <select name="ma_dvt" class="form-control" required>
                        <option value="">-- Chọn đơn vị --</option>

                        <?php foreach ($units as $unit): ?>
                            <option
                                value="<?= $unit['ma_dvt'] ?>"
                                <?= (($_POST['ma_dvt'] ?? '') == $unit['ma_dvt']) ? 'selected' : '' ?>
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
                            <?= !isset($_POST['trang_thai']) || $_POST['trang_thai'] ? 'checked' : '' ?>
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
                    placeholder="Mô tả sản phẩm..."
                ><?= htmlspecialchars($_POST['mo_ta'] ?? '') ?></textarea>
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
                    Lưu sản phẩm
                </button>

            </div>

        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>