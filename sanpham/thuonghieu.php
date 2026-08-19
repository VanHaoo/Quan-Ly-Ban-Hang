<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requirePermission('sanpham');

$page_title = 'Thương hiệu';

$errors = [];

/*
|--------------------------------------------------------------------------
| THÊM THƯƠNG HIỆU
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_brand'])) {

    $ten_thuong_hieu = trim($_POST['ten_thuong_hieu'] ?? '');
    $mo_ta = trim($_POST['mo_ta'] ?? '');

    if ($ten_thuong_hieu === '') {
        $errors[] = 'Tên thương hiệu không được để trống.';
    } elseif (mb_strlen($ten_thuong_hieu) > 100) {
        $errors[] = 'Tên thương hiệu không được vượt quá 100 ký tự.';
    }

    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA TRÙNG TÊN
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM THUONGHIEU
            WHERE LOWER(TRIM(ten_thuong_hieu))
                = LOWER(TRIM(?))
        ");

        $stmt->execute([$ten_thuong_hieu]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Thương hiệu này đã tồn tại.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO THUONGHIEU
                (
                    ten_thuong_hieu,
                    mo_ta,
                    trang_thai
                )
                VALUES
                (
                    ?,
                    ?,
                    1
                )
            ");

            $stmt->execute([
                $ten_thuong_hieu,
                $mo_ta !== '' ? $mo_ta : null
            ]);

            $_SESSION['success'] =
                'Thêm thương hiệu thành công.';

            redirect('sanpham/thuonghieu.php');

        } catch (Throwable $e) {

            $errors[] =
                'Không thể thêm thương hiệu: '
                . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| CẬP NHẬT THƯƠNG HIỆU
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brand'])) {

    $ma_thuong_hieu = (int)(
        $_POST['ma_thuong_hieu'] ?? 0
    );

    $ten_thuong_hieu = trim(
        $_POST['ten_thuong_hieu'] ?? ''
    );

    $mo_ta = trim(
        $_POST['mo_ta'] ?? ''
    );

    if ($ma_thuong_hieu <= 0) {
        $errors[] = 'Thương hiệu không hợp lệ.';
    }

    if ($ten_thuong_hieu === '') {
        $errors[] = 'Tên thương hiệu không được để trống.';
    } elseif (mb_strlen($ten_thuong_hieu) > 100) {
        $errors[] = 'Tên thương hiệu không được vượt quá 100 ký tự.';
    }

    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA TRÙNG
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM THUONGHIEU
            WHERE LOWER(TRIM(ten_thuong_hieu))
                = LOWER(TRIM(?))
              AND ma_thuong_hieu <> ?
        ");

        $stmt->execute([
            $ten_thuong_hieu,
            $ma_thuong_hieu
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Tên thương hiệu đã tồn tại.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                UPDATE THUONGHIEU

                SET
                    ten_thuong_hieu = ?,
                    mo_ta = ?

                WHERE ma_thuong_hieu = ?
            ");

            $stmt->execute([
                $ten_thuong_hieu,
                $mo_ta !== '' ? $mo_ta : null,
                $ma_thuong_hieu
            ]);

            $_SESSION['success'] =
                'Cập nhật thương hiệu thành công.';

            redirect('sanpham/thuonghieu.php');

        } catch (Throwable $e) {

            $errors[] =
                'Không thể cập nhật thương hiệu: '
                . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| BẬT / TẮT THƯƠNG HIỆU
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_brand'])) {

    $ma_thuong_hieu = (int)(
        $_POST['ma_thuong_hieu'] ?? 0
    );

    if ($ma_thuong_hieu <= 0) {

        $errors[] = 'Thương hiệu không hợp lệ.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT trang_thai
                FROM THUONGHIEU
                WHERE ma_thuong_hieu = ?
            ");

            $stmt->execute([
                $ma_thuong_hieu
            ]);

            $brand = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$brand) {
                throw new Exception(
                    'Không tìm thấy thương hiệu.'
                );
            }

            $new_status =
                (int)$brand['trang_thai'] === 1
                    ? 0
                    : 1;

            $stmt = $pdo->prepare("
                UPDATE THUONGHIEU

                SET
                    trang_thai = ?

                WHERE ma_thuong_hieu = ?
            ");

            $stmt->execute([
                $new_status,
                $ma_thuong_hieu
            ]);

            $_SESSION['success'] =
                $new_status === 1
                    ? 'Đã kích hoạt thương hiệu.'
                    : 'Đã tắt thương hiệu.';

            redirect('sanpham/thuonghieu.php');

        } catch (Throwable $e) {

            $errors[] =
                'Không thể thay đổi trạng thái: '
                . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH THƯƠNG HIỆU
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query("
    SELECT
        th.ma_thuong_hieu,
        th.ten_thuong_hieu,
        th.mo_ta,
        th.trang_thai,
        th.ngay_tao,

        COUNT(sp.ma_san_pham) AS so_san_pham

    FROM THUONGHIEU th

    LEFT JOIN SANPHAM sp
        ON sp.ma_thuong_hieu = th.ma_thuong_hieu

    GROUP BY
        th.ma_thuong_hieu,
        th.ten_thuong_hieu,
        th.mo_ta,
        th.trang_thai,
        th.ngay_tao

    ORDER BY
        th.trang_thai DESC,
        th.ten_thuong_hieu ASC
");

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| THỐNG KÊ
|--------------------------------------------------------------------------
*/
$total_brands = count($brands);

$active_brands = 0;
$total_products = 0;

foreach ($brands as $brand) {

    if ((int)$brand['trang_thai'] === 1) {
        $active_brands++;
    }

    $total_products +=
        (int)$brand['so_san_pham'];
}


require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* ==========================================================
   PAGE
========================================================== */

.brand-page {
    max-width: 1400px;
    margin: 0 auto;
}


/* ==========================================================
   HEADER
========================================================== */

.brand-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.brand-title h2 {
    margin: 0;
    font-size: 26px;
    color: #0f172a;
}

.brand-title p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 14px;
}


/* ==========================================================
   STATISTICS
========================================================== */

.brand-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.brand-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px 20px;

    display: flex;
    align-items: center;
    gap: 14px;
}

.brand-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #eff6ff;
    color: #2563eb;
}

.brand-stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.brand-stat-label {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}


/* ==========================================================
   CARD
========================================================== */

.brand-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}

.brand-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.brand-card-header h3 {
    margin: 0;
    font-size: 17px;
    color: #0f172a;
}

.brand-search {
    max-width: 350px;
    width: 100%;
}


/* ==========================================================
   TABLE
========================================================== */

.brand-table-wrapper {
    overflow-x: auto;
}

.brand-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.brand-table th {
    background: #f8fafc;
    padding: 13px 15px;

    border-bottom: 1px solid #e5e7eb;

    color: #475569;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
}

.brand-table td {
    padding: 14px 15px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.brand-table tbody tr:hover {
    background: #f8fafc;
}


/* ==========================================================
   NAME
========================================================== */

.brand-name {
    font-weight: 600;
    color: #0f172a;
}

.brand-description {
    max-width: 400px;
    color: #64748b;
    font-size: 13px;
}


/* ==========================================================
   PRODUCT COUNT
========================================================== */

.brand-product-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: 5px 9px;

    border-radius: 7px;

    background: #f1f5f9;
    color: #334155;

    font-size: 12px;
    font-weight: 600;
}


/* ==========================================================
   STATUS
========================================================== */

.brand-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: 5px 9px;
    border-radius: 20px;

    font-size: 11px;
    font-weight: 600;
}

.brand-status.active {
    background: #dcfce7;
    color: #166534;
}

.brand-status.inactive {
    background: #f1f5f9;
    color: #64748b;
}


/* ==========================================================
   ACTION
========================================================== */

.brand-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}


/* ==========================================================
   MODAL
========================================================== */

.brand-modal {
    display: none;

    position: fixed;
    inset: 0;

    background: rgba(15, 23, 42, .55);

    z-index: 9999;

    align-items: center;
    justify-content: center;

    padding: 20px;
}

.brand-modal-box {
    width: 100%;
    max-width: 560px;

    background: #fff;
    border-radius: 14px;

    overflow: hidden;

    box-shadow:
        0 20px 50px rgba(0, 0, 0, .18);
}

.brand-modal-header {
    padding: 18px 20px;

    border-bottom: 1px solid #e5e7eb;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.brand-modal-header h3 {
    margin: 0;
    font-size: 17px;
}

.brand-modal-close {
    border: 0;
    background: none;
    cursor: pointer;

    font-size: 22px;
    color: #64748b;
}

.brand-modal-body {
    padding: 20px;
}

.brand-modal-footer {
    padding: 15px 20px;

    background: #f8fafc;

    border-top: 1px solid #e5e7eb;

    display: flex;
    justify-content: flex-end;
    gap: 8px;
}


/* ==========================================================
   FORM
========================================================== */

.brand-form-group {
    margin-bottom: 16px;
}

.brand-form-group:last-child {
    margin-bottom: 0;
}

.brand-form-group label {
    display: block;

    margin-bottom: 7px;

    font-size: 13px;
    font-weight: 600;

    color: #334155;
}

.brand-form-group textarea {
    min-height: 110px;
    resize: vertical;
}


/* ==========================================================
   ERROR
========================================================== */

.brand-error {
    background: #fef2f2;

    border: 1px solid #fecaca;

    color: #991b1b;

    padding: 14px 18px;

    border-radius: 10px;

    margin-bottom: 20px;
}

.brand-error ul {
    margin: 8px 0 0 20px;
}


/* ==========================================================
   EMPTY
========================================================== */

.brand-empty {
    text-align: center;
    padding: 55px 20px;
    color: #64748b;
}

.brand-empty i {
    display: block;

    font-size: 42px;

    color: #cbd5e1;

    margin-bottom: 12px;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 800px) {

    .brand-stats {
        grid-template-columns: 1fr;
    }

    .brand-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .brand-card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .brand-search {
        max-width: none;
    }
}

</style>


<div class="brand-page">

    <!-- ======================================================
         HEADER
    ======================================================= -->

    <div class="brand-header">

        <div class="brand-title">

            <h2>
                <i class="fas fa-copyright"></i>
                Thương hiệu
            </h2>

            <p>
                Quản lý các thương hiệu của sản phẩm.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            onclick="openAddBrand()"
        >

            <i class="fas fa-plus"></i>

            Thêm thương hiệu

        </button>

    </div>


    <!-- ======================================================
         ERROR
    ======================================================= -->

    <?php if (!empty($errors)): ?>

        <div class="brand-error">

            <strong>
                <i class="fas fa-exclamation-triangle"></i>
                Có lỗi xảy ra
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


    <!-- ======================================================
         STATISTICS
    ======================================================= -->

    <div class="brand-stats">


        <div class="brand-stat">

            <div class="brand-stat-icon">

                <i class="fas fa-copyright"></i>

            </div>

            <div>

                <div class="brand-stat-value">
                    <?= $total_brands ?>
                </div>

                <div class="brand-stat-label">
                    Tổng thương hiệu
                </div>

            </div>

        </div>


        <div class="brand-stat">

            <div class="brand-stat-icon">

                <i class="fas fa-check-circle"></i>

            </div>

            <div>

                <div class="brand-stat-value">
                    <?= $active_brands ?>
                </div>

                <div class="brand-stat-label">
                    Đang hoạt động
                </div>

            </div>

        </div>


        <div class="brand-stat">

            <div class="brand-stat-icon">

                <i class="fas fa-box"></i>

            </div>

            <div>

                <div class="brand-stat-value">
                    <?= $total_products ?>
                </div>

                <div class="brand-stat-label">
                    Sản phẩm đang sử dụng
                </div>

            </div>

        </div>


    </div>


    <!-- ======================================================
         LIST
    ======================================================= -->

    <div class="brand-card">


        <div class="brand-card-header">

            <h3>

                <i class="fas fa-list"></i>

                Danh sách thương hiệu

            </h3>


            <div class="brand-search">

                <input
                    type="text"
                    id="brandSearch"
                    class="form-control"
                    placeholder="Tìm thương hiệu..."
                >

            </div>

        </div>


        <div class="brand-table-wrapper">

            <table
                class="brand-table"
                id="brandTable"
            >

                <thead>

                    <tr>

                        <th width="70">
                            #
                        </th>

                        <th>
                            Tên thương hiệu
                        </th>

                        <th>
                            Mô tả
                        </th>

                        <th>
                            Sản phẩm
                        </th>

                        <th>
                            Trạng thái
                        </th>

                        <th width="150">
                            Thao tác
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($brands)): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="brand-empty"
                        >

                            <i class="fas fa-copyright"></i>

                            <div>
                                Chưa có thương hiệu nào.
                            </div>

                            <small>
                                Hãy thêm thương hiệu đầu tiên.
                            </small>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach ($brands as $index => $brand): ?>

                    <tr
                        data-search="<?= htmlspecialchars(
                            strtolower(
                                $brand['ten_thuong_hieu']
                                . ' '
                                . ($brand['mo_ta'] ?? '')
                            )
                        ) ?>"
                    >

                        <td>
                            <?= $index + 1 ?>
                        </td>


                        <td>

                            <div class="brand-name">

                                <?= htmlspecialchars(
                                    $brand['ten_thuong_hieu']
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <div class="brand-description">

                                <?php if (!empty($brand['mo_ta'])): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $brand['mo_ta']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span>
                                        Chưa có mô tả
                                    </span>

                                <?php endif; ?>

                            </div>

                        </td>


                        <td>

                            <span class="brand-product-count">

                                <i class="fas fa-box"></i>

                                <?= number_format(
                                    $brand['so_san_pham']
                                ) ?>

                                sản phẩm

                            </span>

                        </td>


                        <td>

                            <?php if (
                                (int)$brand['trang_thai'] === 1
                            ): ?>

                                <span class="brand-status active">

                                    <i class="fas fa-check-circle"></i>

                                    Hoạt động

                                </span>

                            <?php else: ?>

                                <span class="brand-status inactive">

                                    <i class="fas fa-pause-circle"></i>

                                    Đang tắt

                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="brand-actions">


                                <!-- SỬA -->

                                <button
                                    type="button"
                                    class="btn btn-sm btn-secondary"
                                    title="Sửa"
                                    onclick="openEditBrand(
                                        <?= (int)$brand['ma_thuong_hieu'] ?>,
                                        '<?= htmlspecialchars(
                                            $brand['ten_thuong_hieu'],
                                            ENT_QUOTES
                                        ) ?>',
                                        '<?= htmlspecialchars(
                                            $brand['mo_ta'] ?? '',
                                            ENT_QUOTES
                                        ) ?>'
                                    )"
                                >

                                    <i class="fas fa-edit"></i>

                                </button>


                                <!-- BẬT / TẮT -->

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Bạn có chắc muốn thay đổi trạng thái thương hiệu này?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="ma_thuong_hieu"
                                        value="<?= (int)$brand['ma_thuong_hieu'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="toggle_brand"
                                        class="
                                            btn btn-sm
                                            <?= (int)$brand['trang_thai'] === 1
                                                ? 'btn-danger'
                                                : 'btn-success'
                                            ?>
                                        "
                                        title="<?= (int)$brand['trang_thai'] === 1
                                            ? 'Tắt'
                                            : 'Kích hoạt'
                                        ?>"
                                    >

                                        <i
                                            class="
                                                fas
                                                <?= (int)$brand['trang_thai'] === 1
                                                    ? 'fa-toggle-off'
                                                    : 'fa-toggle-on'
                                                ?>
                                            "
                                        ></i>

                                    </button>

                                </form>


                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- ==========================================================
     MODAL
========================================================== -->

<div
    id="brandModal"
    class="brand-modal"
>

    <div class="brand-modal-box">


        <div class="brand-modal-header">

            <h3 id="brandModalTitle">

                <i class="fas fa-plus-circle"></i>

                Thêm thương hiệu

            </h3>


            <button
                type="button"
                class="brand-modal-close"
                onclick="closeBrandModal()"
            >
                &times;
            </button>

        </div>


        <form
            method="POST"
            id="brandForm"
        >


            <div class="brand-modal-body">


                <input
                    type="hidden"
                    name="ma_thuong_hieu"
                    id="brandId"
                >


                <div class="brand-form-group">

                    <label>

                        Tên thương hiệu

                        <span style="color:#dc2626;">
                            *
                        </span>

                    </label>

                    <input
                        type="text"
                        name="ten_thuong_hieu"
                        id="brandName"
                        class="form-control"
                        maxlength="100"
                        placeholder="VD: Samsung"
                        required
                    >

                </div>


                <div class="brand-form-group">

                    <label>
                        Mô tả
                    </label>

                    <textarea
                        name="mo_ta"
                        id="brandDescription"
                        class="form-control"
                        maxlength="1000"
                        placeholder="Mô tả thương hiệu..."
                    ></textarea>

                </div>


            </div>


            <div class="brand-modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeBrandModal()"
                >
                    Hủy
                </button>


                <button
                    type="submit"
                    name="add_brand"
                    id="brandSubmit"
                    class="btn btn-primary"
                >

                    <i class="fas fa-save"></i>

                    Thêm thương hiệu

                </button>

            </div>


        </form>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| THÊM
|--------------------------------------------------------------------------
*/

function openAddBrand() {

    document.getElementById(
        'brandModalTitle'
    ).innerHTML =
        '<i class="fas fa-plus-circle"></i> ' +
        'Thêm thương hiệu';

    document.getElementById(
        'brandId'
    ).value = '';

    document.getElementById(
        'brandName'
    ).value = '';

    document.getElementById(
        'brandDescription'
    ).value = '';

    document.getElementById(
        'brandSubmit'
    ).name = 'add_brand';

    document.getElementById(
        'brandSubmit'
    ).innerHTML =
        '<i class="fas fa-save"></i> ' +
        'Thêm thương hiệu';

    document.getElementById(
        'brandModal'
    ).style.display = 'flex';

    setTimeout(function () {

        document.getElementById(
            'brandName'
        ).focus();

    }, 100);
}


/*
|--------------------------------------------------------------------------
| SỬA
|--------------------------------------------------------------------------
*/

function openEditBrand(
    id,
    name,
    description
) {

    document.getElementById(
        'brandModalTitle'
    ).innerHTML =
        '<i class="fas fa-edit"></i> ' +
        'Sửa thương hiệu';

    document.getElementById(
        'brandId'
    ).value = id;

    document.getElementById(
        'brandName'
    ).value = name;

    document.getElementById(
        'brandDescription'
    ).value = description;

    document.getElementById(
        'brandSubmit'
    ).name = 'update_brand';

    document.getElementById(
        'brandSubmit'
    ).innerHTML =
        '<i class="fas fa-save"></i> ' +
        'Lưu thay đổi';

    document.getElementById(
        'brandModal'
    ).style.display = 'flex';

    setTimeout(function () {

        document.getElementById(
            'brandName'
        ).focus();

    }, 100);
}


/*
|--------------------------------------------------------------------------
| ĐÓNG MODAL
|--------------------------------------------------------------------------
*/

function closeBrandModal() {

    document.getElementById(
        'brandModal'
    ).style.display = 'none';
}


/*
|--------------------------------------------------------------------------
| CLICK NGOÀI MODAL
|--------------------------------------------------------------------------
*/

document
    .getElementById('brandModal')
    .addEventListener(
        'click',
        function (event) {

            if (event.target === this) {
                closeBrandModal();
            }

        }
    );


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {
            closeBrandModal();
        }

    }
);


/*
|--------------------------------------------------------------------------
| TÌM KIẾM
|--------------------------------------------------------------------------
*/

document
    .getElementById('brandSearch')
    .addEventListener(
        'input',
        function () {

            const keyword =
                this.value
                    .toLowerCase()
                    .trim();

            const rows =
                document.querySelectorAll(
                    '#brandTable tbody tr[data-search]'
                );

            rows.forEach(function (row) {

                const text =
                    row.dataset.search
                        .toLowerCase();

                row.style.display =
                    text.includes(keyword)
                        ? ''
                        : 'none';

            });

        }
    );

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>