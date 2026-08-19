<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requirePermission('sanpham');

$page_title = 'Thêm sản phẩm';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ma_sp = trim($_POST['ma_sp'] ?? '');
    $ten_san_pham = trim($_POST['ten_san_pham'] ?? '');
    $ma_loai = (int)($_POST['ma_loai'] ?? 0);
    $ma_thuong_hieu = (int)($_POST['ma_thuong_hieu'] ?? 0);
    $ma_dvt = (int)($_POST['ma_dvt'] ?? 0);
    $gia_ban = (float)($_POST['gia_ban'] ?? 0);
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($ma_sp === '') {
        $errors[] = 'Vui lòng nhập mã sản phẩm.';
    }

    if ($ten_san_pham === '') {
        $errors[] = 'Vui lòng nhập tên sản phẩm.';
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
        $errors[] = 'Giá bán không được nhỏ hơn 0.';
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            | Kiểm tra mã sản phẩm trùng
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM SANPHAM
                WHERE ma_sp = ?
            ");

            $stmt->execute([$ma_sp]);

            if ((int)$stmt->fetchColumn() > 0) {
                throw new Exception('Mã sản phẩm đã tồn tại.');
            }

            /*
            | Thêm sản phẩm
            */

            $stmt = $pdo->prepare("
                INSERT INTO SANPHAM
                (
                    ma_sp,
                    ten_san_pham,
                    ma_loai,
                    ma_thuong_hieu,
                    ma_dvt,
                    gia_ban,
                    trang_thai
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $ma_sp,
                $ten_san_pham,
                $ma_loai,
                $ma_thuong_hieu,
                $ma_dvt,
                $gia_ban,
                $trang_thai
            ]);

            $pdo->commit();

            /*
            | Sau khi tạo sản phẩm:
            | chuyển thẳng sang trang quản lý SKU.
            */

            header(
                'Location: ' .
                url('sanpham/bienthe.php?ma_sp=' . urlencode($ma_sp))
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| LẤY DANH MỤC
|--------------------------------------------------------------------------
*/

$loaiList = $pdo->query("
    SELECT ma_loai, ten_loai
    FROM LOAISANPHAM
    WHERE trang_thai = 1
    ORDER BY ten_loai
")->fetchAll();

$thuongHieuList = $pdo->query("
    SELECT ma_thuong_hieu, ten_thuong_hieu
    FROM THUONGHIEU
    WHERE trang_thai = 1
    ORDER BY ten_thuong_hieu
")->fetchAll();

$dvtList = $pdo->query("
    SELECT ma_dvt, ten_dvt
    FROM DONVITINH
    WHERE trang_thai = 1
    ORDER BY ten_dvt
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>

.product-create-page {
    max-width: 1050px;
    margin: 0 auto;
}

/* HEADER */

.product-create-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.product-create-title h2 {
    margin: 0;
    font-size: 26px;
}

.product-create-title p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 14px;
}

/* ERROR */

.form-errors {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
}

.form-errors ul {
    margin: 8px 0 0 20px;
    padding: 0;
}

.form-errors li {
    margin-bottom: 4px;
}

/* CARD */

.product-form-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
}

.form-section {
    padding: 24px;
    border-bottom: 1px solid #e5e7eb;
}

.form-section:last-child {
    border-bottom: 0;
}

.form-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.form-section-title i {
    color: #2563eb;
}

.form-section-title h3 {
    margin: 0;
    font-size: 17px;
}

.form-section-title p {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 13px;
}

/* GRID */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}

.required {
    color: #dc2626;
}

.form-control {
    width: 100%;
    box-sizing: border-box;
}

.form-help {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 12px;
}

/* PRICE */

.price-input {
    position: relative;
}

.price-input .form-control {
    padding-right: 60px;
}

.price-unit {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 12px;
}

/* STATUS */

.status-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 15px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
}

.status-box input {
    width: 18px;
    height: 18px;
}

.status-box label {
    margin: 0;
    cursor: pointer;
}

.status-box strong {
    display: block;
    font-size: 13px;
}

.status-box span {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    color: #64748b;
}

/* NOTE */

.create-note {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    color: #1e40af;
}

.create-note i {
    margin-top: 2px;
}

.create-note strong {
    display: block;
    margin-bottom: 3px;
}

.create-note span {
    font-size: 12px;
}

/* FOOTER */

.form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px 24px;
    background: #f8fafc;
}

/* RESPONSIVE */

@media (max-width: 750px) {

    .product-create-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .form-grid,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="product-create-page">

    <!-- HEADER -->

    <div class="product-create-header">

        <div class="product-create-title">

            <h2>
                <i class="fas fa-box-open"></i>
                Thêm sản phẩm
            </h2>

            <p>
                Tạo sản phẩm mới trước khi quản lý các biến thể / SKU.
            </p>

        </div>

        <a
            href="<?= url('sanpham/index.php') ?>"
            class="btn btn-secondary"
        >
            <i class="fas fa-arrow-left"></i>
            Quay lại
        </a>

    </div>


    <!-- ERROR -->

    <?php if (!empty($errors)): ?>

        <div class="form-errors">

            <strong>
                <i class="fas fa-exclamation-triangle"></i>
                Không thể lưu sản phẩm
            </strong>

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- FORM -->

    <form
        method="POST"
        class="product-form-card"
        id="productCreateForm"
    >

        <!-- THÔNG TIN CƠ BẢN -->

        <div class="form-section">

            <div class="form-section-title">

                <i class="fas fa-box"></i>

                <div>

                    <h3>
                        Thông tin sản phẩm
                    </h3>

                    <p>
                        Thông tin chung của sản phẩm.
                    </p>

                </div>

            </div>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Mã sản phẩm
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="ma_sp"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['ma_sp'] ?? '') ?>"
                        placeholder="VD: SP001"
                        maxlength="30"
                        required
                        autocomplete="off"
                    >

                    <span class="form-help">
                        Mã phải duy nhất trong hệ thống.
                    </span>

                </div>


                <div class="form-group">

                    <label>
                        Tên sản phẩm
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="ten_san_pham"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['ten_san_pham'] ?? '') ?>"
                        placeholder="VD: Áo thun nam"
                        maxlength="255"
                        required
                    >

                </div>

            </div>

        </div>


        <!-- PHÂN LOẠI -->

        <div class="form-section">

            <div class="form-section-title">

                <i class="fas fa-tags"></i>

                <div>

                    <h3>
                        Phân loại sản phẩm
                    </h3>

                    <p>
                        Xác định loại, thương hiệu và đơn vị tính.
                    </p>

                </div>

            </div>


            <div class="form-grid-3">

                <!-- LOẠI -->

                <div class="form-group">

                    <label>
                        Loại sản phẩm
                        <span class="required">*</span>
                    </label>

                    <select
                        name="ma_loai"
                        class="form-control"
                        required
                    >

                        <option value="">
                            -- Chọn loại --
                        </option>

                        <?php foreach ($loaiList as $item): ?>

                            <option
                                value="<?= (int)$item['ma_loai'] ?>"
                                <?= (int)($_POST['ma_loai'] ?? 0) === (int)$item['ma_loai']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars($item['ten_loai']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- THƯƠNG HIỆU -->

                <div class="form-group">

                    <label>
                        Thương hiệu
                        <span class="required">*</span>
                    </label>

                    <select
                        name="ma_thuong_hieu"
                        class="form-control"
                        required
                    >

                        <option value="">
                            -- Chọn thương hiệu --
                        </option>

                        <?php foreach ($thuongHieuList as $item): ?>

                            <option
                                value="<?= (int)$item['ma_thuong_hieu'] ?>"
                                <?= (int)($_POST['ma_thuong_hieu'] ?? 0) === (int)$item['ma_thuong_hieu']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars($item['ten_thuong_hieu']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ĐVT -->

                <div class="form-group">

                    <label>
                        Đơn vị tính
                        <span class="required">*</span>
                    </label>

                    <select
                        name="ma_dvt"
                        class="form-control"
                        required
                    >

                        <option value="">
                            -- Chọn đơn vị --
                        </option>

                        <?php foreach ($dvtList as $item): ?>

                            <option
                                value="<?= (int)$item['ma_dvt'] ?>"
                                <?= (int)($_POST['ma_dvt'] ?? 0) === (int)$item['ma_dvt']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars($item['ten_dvt']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

        </div>


        <!-- GIÁ -->

        <div class="form-section">

            <div class="form-section-title">

                <i class="fas fa-money-bill-wave"></i>

                <div>

                    <h3>
                        Giá bán mặc định
                    </h3>

                    <p>
                        Giá này dùng làm giá tham khảo khi tạo SKU.
                    </p>

                </div>

            </div>


            <div style="max-width:380px;">

                <div class="form-group">

                    <label>
                        Giá bán
                    </label>

                    <div class="price-input">

                        <input
                            type="number"
                            name="gia_ban"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['gia_ban'] ?? '0') ?>"
                            min="0"
                            step="1000"
                            placeholder="0"
                        >

                        <span class="price-unit">
                            VNĐ
                        </span>

                    </div>

                    <span class="form-help">
                        Giá thực tế của từng SKU có thể được thiết lập riêng.
                    </span>

                </div>

            </div>

        </div>


        <!-- TRẠNG THÁI -->

        <div class="form-section">

            <div class="form-section-title">

                <i class="fas fa-toggle-on"></i>

                <div>

                    <h3>
                        Trạng thái
                    </h3>

                    <p>
                        Xác định sản phẩm có đang được sử dụng hay không.
                    </p>

                </div>

            </div>


            <div style="max-width:500px;">

                <div class="status-box">

                    <input
                        type="checkbox"
                        id="trang_thai"
                        name="trang_thai"
                        value="1"
                        <?= !isset($_POST['trang_thai']) || $_POST['trang_thai'] == 1
                            ? 'checked'
                            : ''
                        ?>
                    >

                    <label for="trang_thai">

                        <strong>
                            Sản phẩm đang hoạt động
                        </strong>

                        <span>
                            Sản phẩm có thể được sử dụng để tạo SKU và bán hàng.
                        </span>

                    </label>

                </div>

            </div>

        </div>


        <!-- NOTE -->

        <div class="form-section">

            <div class="create-note">

                <i class="fas fa-info-circle"></i>

                <div>

                    <strong>
                        Lưu ý về biến thể / SKU
                    </strong>

                    <span>
                        Sau khi tạo sản phẩm, hệ thống sẽ chuyển bạn đến
                        trang quản lý biến thể để tạo các SKU như
                        Đỏ / M, Đỏ / L, Đen / M...
                    </span>

                </div>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="form-footer">

            <a
                href="<?= url('sanpham/index.php') ?>"
                class="btn btn-secondary"
            >
                Hủy
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="fas fa-save"></i>

                Lưu sản phẩm

            </button>

        </div>

    </form>

</div>


<script>

document
    .getElementById('productCreateForm')
    .addEventListener('submit', function(e) {

        const maSp = document
            .querySelector('[name="ma_sp"]')
            .value
            .trim();

        const tenSp = document
            .querySelector('[name="ten_san_pham"]')
            .value
            .trim();

        if (!maSp || !tenSp) {

            e.preventDefault();

            alert('Vui lòng nhập đầy đủ mã và tên sản phẩm.');

            return;
        }

        const submitButton = this.querySelector(
            'button[type="submit"]'
        );

        submitButton.disabled = true;

        submitButton.innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

    });

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>