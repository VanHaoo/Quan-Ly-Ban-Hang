<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requirePermission('sanpham');

$page_title = 'Sửa sản phẩm';

$ma_sp = trim($_GET['ma_sp'] ?? '');

if ($ma_sp === '') {
    header('Location: ' . url('sanpham/index.php'));
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY SẢN PHẨM
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sp.ma_sp,
        sp.ten_san_pham,
        sp.ma_loai,
        sp.ma_thuong_hieu,
        sp.ma_dvt,
        sp.gia_ban,
        sp.trang_thai
    FROM SANPHAM sp
    WHERE sp.ma_sp = ?
    LIMIT 1
");

$stmt->execute([$ma_sp]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = 'Không tìm thấy sản phẩm.';
    header('Location: ' . url('sanpham/index.php'));
    exit;
}

$errors = [];


/*
|--------------------------------------------------------------------------
| XỬ LÝ CẬP NHẬT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
    | KIỂM TRA SKU
    |--------------------------------------------------------------------------
    |
    | Nếu sản phẩm đã có SKU thì việc tắt sản phẩm vẫn cho phép,
    | nhưng SKU sẽ không nên được sử dụng để bán sản phẩm mới.
    |
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            | Lấy dữ liệu cũ để kiểm tra thay đổi giá
            */

            $stmt = $pdo->prepare("
                SELECT
                    ten_san_pham,
                    ma_loai,
                    ma_thuong_hieu,
                    ma_dvt,
                    gia_ban,
                    trang_thai
                FROM SANPHAM
                WHERE ma_sp = ?
                FOR UPDATE
            ");

            $stmt->execute([$ma_sp]);

            $oldProduct = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldProduct) {
                throw new Exception('Sản phẩm không còn tồn tại.');
            }


            /*
            |--------------------------------------------------------------------------
            | CẬP NHẬT SẢN PHẨM
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE SANPHAM
                SET
                    ten_san_pham = ?,
                    ma_loai = ?,
                    ma_thuong_hieu = ?,
                    ma_dvt = ?,
                    gia_ban = ?,
                    trang_thai = ?
                WHERE ma_sp = ?
            ");

            $stmt->execute([
                $ten_san_pham,
                $ma_loai,
                $ma_thuong_hieu,
                $ma_dvt,
                $gia_ban,
                $trang_thai,
                $ma_sp
            ]);


            /*
            |--------------------------------------------------------------------------
            | GHI LỊCH SỬ GIÁ
            |--------------------------------------------------------------------------
            |
            | Chỉ ghi nếu giá mặc định thay đổi.
            |
            | Lưu ý:
            | Nếu database hiện tại của bạn có Trigger ghi LICHSUGIA
            | thì đoạn này không cần thêm dữ liệu thủ công.
            |
            */

            $oldPrice = (float)$oldProduct['gia_ban'];

            if ($oldPrice != $gia_ban) {

                /*
                 * Kiểm tra cấu trúc bảng lịch sử giá.
                 *
                 * Nếu bảng LICHSUGIA trong database của bạn có
                 * cấu trúc khác, không tự ý thêm dữ liệu ở đây.
                 *
                 * Việc ghi lịch sử giá nên để Trigger xử lý
                 * nếu repo hiện tại đã có Trigger.
                 */
            }


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            if (
                function_exists('writeAuditLog')
            ) {
                writeAuditLog(
                    $pdo,
                    'UPDATE',
                    'Cập nhật sản phẩm: ' . $ma_sp
                );
            }


            $pdo->commit();

            $_SESSION['success'] = 'Cập nhật sản phẩm thành công.';

            header(
                'Location: ' .
                url('sanpham/index.php')
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
    SELECT
        ma_loai,
        ten_loai
    FROM LOAISANPHAM
    WHERE trang_thai = 1
    ORDER BY ten_loai
")->fetchAll(PDO::FETCH_ASSOC);


$thuongHieuList = $pdo->query("
    SELECT
        ma_thuong_hieu,
        ten_thuong_hieu
    FROM THUONGHIEU
    WHERE trang_thai = 1
    ORDER BY ten_thuong_hieu
")->fetchAll(PDO::FETCH_ASSOC);


$dvtList = $pdo->query("
    SELECT
        ma_dvt,
        ten_dvt
    FROM DONVITINH
    WHERE trang_thai = 1
    ORDER BY ten_dvt
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GIÁ TRỊ HIỂN THỊ
|--------------------------------------------------------------------------
*/

$displayName = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['ten_san_pham'] ?? $product['ten_san_pham'])
    : $product['ten_san_pham'];

$displayLoai = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['ma_loai'] ?? $product['ma_loai'])
    : (int)$product['ma_loai'];

$displayBrand = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['ma_thuong_hieu'] ?? $product['ma_thuong_hieu'])
    : (int)$product['ma_thuong_hieu'];

$displayDvt = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['ma_dvt'] ?? $product['ma_dvt'])
    : (int)$product['ma_dvt'];

$displayPrice = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['gia_ban'] ?? $product['gia_ban'])
    : $product['gia_ban'];

$displayStatus = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? isset($_POST['trang_thai'])
    : (bool)$product['trang_thai'];


require_once __DIR__ . '/../includes/header.php';
?>

<style>

.product-edit-page {
    max-width: 1050px;
    margin: 0 auto;
}

.product-edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.product-edit-title h2 {
    margin: 0;
    font-size: 26px;
}

.product-edit-title p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 14px;
}

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

.form-section-title > i {
    color: #2563eb;
    width: 20px;
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

.readonly-box {
    background: #f8fafc;
    color: #475569;
    cursor: not-allowed;
}

.form-help {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 12px;
}

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

.status-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 15px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    max-width: 520px;
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

.product-code-box {
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-code {
    display: inline-flex;
    align-items: center;
    min-height: 40px;
    padding: 0 13px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-family: monospace;
    font-weight: 600;
    color: #334155;
}

.info-box {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    color: #1e40af;
}

.info-box i {
    margin-top: 2px;
}

.info-box strong {
    display: block;
    margin-bottom: 3px;
}

.info-box span {
    font-size: 12px;
}

.form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px 24px;
    background: #f8fafc;
}

@media (max-width: 750px) {

    .product-edit-header {
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


<div class="product-edit-page">

    <!-- HEADER -->

    <div class="product-edit-header">

        <div class="product-edit-title">

            <h2>
                <i class="fas fa-edit"></i>
                Sửa sản phẩm
            </h2>

            <p>
                Cập nhật thông tin sản phẩm
                <strong><?= htmlspecialchars($ma_sp) ?></strong>
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
                Không thể cập nhật sản phẩm
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


    <form
        method="POST"
        class="product-form-card"
        id="productEditForm"
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
                        Thông tin nhận diện của sản phẩm.
                    </p>

                </div>

            </div>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Mã sản phẩm
                    </label>

                    <div class="product-code-box">

                        <span class="product-code">
                            <?= htmlspecialchars($ma_sp) ?>
                        </span>

                    </div>

                    <span class="form-help">
                        Mã sản phẩm không thể thay đổi sau khi tạo.
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
                        value="<?= htmlspecialchars($displayName) ?>"
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
                        Phân loại
                    </h3>

                    <p>
                        Cập nhật nhóm sản phẩm và đơn vị tính.
                    </p>

                </div>

            </div>


            <div class="form-grid-3">

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
                                <?= $displayLoai === (int)$item['ma_loai']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= htmlspecialchars($item['ten_loai']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


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
                                <?= $displayBrand === (int)$item['ma_thuong_hieu']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= htmlspecialchars($item['ten_thuong_hieu']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


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
                                <?= $displayDvt === (int)$item['ma_dvt']
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
                        Giá tham khảo của sản phẩm.
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
                            value="<?= htmlspecialchars($displayPrice) ?>"
                            min="0"
                            step="1000"
                        >

                        <span class="price-unit">
                            VNĐ
                        </span>

                    </div>

                    <span class="form-help">
                        Giá bán của SKU có thể được quản lý riêng.
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
                        Kiểm soát việc sản phẩm có còn hoạt động hay không.
                    </p>

                </div>

            </div>


            <div class="status-box">

                <input
                    type="checkbox"
                    id="trang_thai"
                    name="trang_thai"
                    value="1"
                    <?= $displayStatus ? 'checked' : '' ?>
                >

                <label for="trang_thai">

                    <strong>
                        Sản phẩm đang hoạt động
                    </strong>

                    <span>
                        Có thể tiếp tục sử dụng sản phẩm trong hệ thống.
                    </span>

                </label>

            </div>

        </div>


        <!-- NOTE -->

        <div class="form-section">

            <div class="info-box">

                <i class="fas fa-info-circle"></i>

                <div>

                    <strong>
                        Lưu ý
                    </strong>

                    <span>
                        Thay đổi thông tin sản phẩm không làm thay đổi
                        SKU và số lượng tồn kho. Nếu muốn thay đổi SKU,
                        hãy sử dụng chức năng quản lý Biến thể / SKU.
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
                Lưu thay đổi
            </button>

        </div>

    </form>

</div>


<script>

document
    .getElementById('productEditForm')
    .addEventListener('submit', function(e) {

        const name = document
            .querySelector('[name="ten_san_pham"]')
            .value
            .trim();

        if (!name) {

            e.preventDefault();

            alert('Vui lòng nhập tên sản phẩm.');

            return;
        }

        const confirmed = confirm(
            'Bạn có chắc muốn lưu thay đổi sản phẩm này?'
        );

        if (!confirmed) {
            e.preventDefault();
            return;
        }

        const button = this.querySelector(
            'button[type="submit"]'
        );

        button.disabled = true;

        button.innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

    });

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>