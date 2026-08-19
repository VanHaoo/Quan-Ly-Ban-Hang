<?php

require_once __DIR__ . '/../includes/auth.php';

requirePermission('sanpham');

/*
|--------------------------------------------------------------------------
| LẤY ID SẢN PHẨM
|--------------------------------------------------------------------------
|
| URL:
| sanpham/bienthe.php?ma_sp=5
|
| ma_sp ở đây là ma_san_pham (INT)
|
*/

$ma_san_pham = (int)($_GET['ma_sp'] ?? 0);

if ($ma_san_pham <= 0) {
    redirect('sanpham/index.php');
}


/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN SẢN PHẨM CHA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sp.ma_san_pham,
        sp.ma_sp,
        sp.ten_san_pham,
        sp.gia_ban,
        sp.trang_thai,

        lsp.ten_loai,
        th.ten_thuong_hieu,
        dvt.ten_dvt

    FROM SANPHAM sp

    LEFT JOIN LOAISANPHAM lsp
        ON sp.ma_loai = lsp.ma_loai

    LEFT JOIN THUONGHIEU th
        ON sp.ma_thuong_hieu = th.ma_thuong_hieu

    LEFT JOIN DONVITINH dvt
        ON sp.ma_dvt = dvt.ma_dvt

    WHERE sp.ma_san_pham = ?

    LIMIT 1
");

$stmt->execute([$ma_san_pham]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = 'Không tìm thấy sản phẩm.';
    redirect('sanpham/index.php');
}


$errors = [];


/*
|--------------------------------------------------------------------------
| THÊM SKU
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_sku'])
) {

    $sku = trim($_POST['sku'] ?? '');

    $ten_bien_the = trim(
        $_POST['ten_bien_the'] ?? ''
    );

    $gia_ban = (float)(
        $_POST['gia_ban'] ?? 0
    );

    $ton_toi_thieu = (int)(
        $_POST['ton_toi_thieu'] ?? 5
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA SKU TRÙNG
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM BIEN_THESANPHAM
            WHERE sku = ?
        ");

        $stmt->execute([$sku]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'SKU đã tồn tại trong hệ thống.';
        }
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
            | Thêm SKU
            */

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
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    0,
                    ?,
                    1
                )
            ");

            $stmt->execute([
                $ma_san_pham,
                $sku,
                $ten_bien_the,
                $gia_ban,
                $ton_toi_thieu
            ]);


            $ma_bien_the = (int)$pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | TẠO TONKHO
            |--------------------------------------------------------------------------
            |
            | Database hiện tại của repo có bảng TONKHO riêng.
            | Không nên giả định Trigger đã tạo bản ghi.
            |
            */

            $stmt = $pdo->prepare("
                INSERT INTO TONKHO
                (
                    ma_bien_the,
                    so_luong,
                    so_luong_toi_thieu
                )
                VALUES
                (?, 0, ?)
            ");

            $stmt->execute([
                $ma_bien_the,
                $ton_toi_thieu
            ]);


            /*
            |--------------------------------------------------------------------------
            | LƯU LỊCH SỬ GIÁ
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO LICHSUGIA
                (
                    ma_san_pham,
                    ma_bien_the,
                    gia_cu,
                    gia_moi,
                    nguoi_thay_doi,
                    ly_do
                )
                VALUES
                (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $ma_san_pham,
                $ma_bien_the,
                0,
                $gia_ban,
                $user_id,
                'Tạo SKU'
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            $_SESSION['success'] =
                'Thêm biến thể / SKU thành công.';


            redirect(
                'sanpham/bienthe.php?ma_sp='
                . $ma_san_pham
            );


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Không thể thêm SKU: '
                . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| CẬP NHẬT SKU
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_sku'])
) {

    $ma_bien_the = (int)(
        $_POST['ma_bien_the'] ?? 0
    );

    $sku = trim(
        $_POST['sku'] ?? ''
    );

    $ten_bien_the = trim(
        $_POST['ten_bien_the'] ?? ''
    );

    $gia_ban = (float)(
        $_POST['gia_ban'] ?? 0
    );

    $ton_toi_thieu = (int)(
        $_POST['ton_toi_thieu'] ?? 0
    );

    $trang_thai = isset(
        $_POST['trang_thai']
    ) ? 1 : 0;


    if ($ma_bien_the <= 0) {
        $errors[] = 'SKU không hợp lệ.';
    }

    if ($sku === '') {
        $errors[] = 'SKU không được để trống.';
    }

    if ($ten_bien_the === '') {
        $errors[] =
            'Tên biến thể không được để trống.';
    }

    if ($gia_ban < 0) {
        $errors[] = 'Giá bán không hợp lệ.';
    }

    if ($ton_toi_thieu < 0) {
        $errors[] =
            'Tồn tối thiểu không hợp lệ.';
    }


    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA SKU TRÙNG
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM BIEN_THESANPHAM
            WHERE sku = ?
              AND ma_bien_the <> ?
        ");

        $stmt->execute([
            $sku,
            $ma_bien_the
        ]);

        if ((int)$stmt->fetchColumn() > 0) {

            $errors[] =
                'SKU này đã được sử dụng.';
        }
    }


    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LẤY GIÁ CŨ
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    gia_ban,
                    sku
                FROM BIEN_THESANPHAM
                WHERE ma_bien_the = ?
                  AND ma_san_pham = ?

                FOR UPDATE
            ");

            $stmt->execute([
                $ma_bien_the,
                $ma_san_pham
            ]);

            $old = $stmt->fetch(
                PDO::FETCH_ASSOC
            );


            if (!$old) {
                throw new Exception(
                    'Không tìm thấy biến thể.'
                );
            }


            $gia_cu = (float)$old['gia_ban'];


            /*
            |--------------------------------------------------------------------------
            | UPDATE SKU
            |--------------------------------------------------------------------------
            */

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
                $ma_san_pham
            ]);


            /*
            |--------------------------------------------------------------------------
            | ĐỒNG BỘ TONKHO
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE TONKHO

                SET
                    so_luong_toi_thieu = ?

                WHERE ma_bien_the = ?
            ");

            $stmt->execute([
                $ton_toi_thieu,
                $ma_bien_the
            ]);


            /*
            |--------------------------------------------------------------------------
            | LƯU LỊCH SỬ GIÁ
            |--------------------------------------------------------------------------
            */

            if ($gia_cu != $gia_ban) {

                $stmt = $pdo->prepare("
                    INSERT INTO LICHSUGIA
                    (
                        ma_san_pham,
                        ma_bien_the,
                        gia_cu,
                        gia_moi,
                        nguoi_thay_doi,
                        ly_do
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $ma_san_pham,
                    $ma_bien_the,
                    $gia_cu,
                    $gia_ban,
                    $user_id,
                    'Cập nhật giá SKU'
                ]);
            }


            $pdo->commit();


            $_SESSION['success'] =
                'Cập nhật SKU thành công.';


            redirect(
                'sanpham/bienthe.php?ma_sp='
                . $ma_san_pham
            );


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Không thể cập nhật SKU: '
                . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| ẨN SKU
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['hide_sku'])
) {

    $ma_bien_the = (int)(
        $_POST['ma_bien_the'] ?? 0
    );


    if ($ma_bien_the <= 0) {

        $errors[] =
            'Biến thể không hợp lệ.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE BIEN_THESANPHAM

                SET trang_thai = 0

                WHERE ma_bien_the = ?
                  AND ma_san_pham = ?
            ");

            $stmt->execute([
                $ma_bien_the,
                $ma_san_pham
            ]);


            $_SESSION['success'] =
                'Đã ẩn SKU.';


            redirect(
                'sanpham/bienthe.php?ma_sp='
                . $ma_san_pham
            );


        } catch (Throwable $e) {

            $errors[] =
                'Không thể ẩn SKU: '
                . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| HIỆN LẠI SKU
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['show_sku'])
) {

    $ma_bien_the = (int)(
        $_POST['ma_bien_the'] ?? 0
    );


    $stmt = $pdo->prepare("
        UPDATE BIEN_THESANPHAM

        SET trang_thai = 1

        WHERE ma_bien_the = ?
          AND ma_san_pham = ?
    ");

    $stmt->execute([
        $ma_bien_the,
        $ma_san_pham
    ]);


    $_SESSION['success'] =
        'Đã kích hoạt lại SKU.';


    redirect(
        'sanpham/bienthe.php?ma_sp='
        . $ma_san_pham
    );
}


/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH SKU
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        bt.ma_bien_the,
        bt.ma_san_pham,
        bt.sku,
        bt.ten_bien_the,
        bt.gia_ban,
        bt.ton_kho,
        bt.ton_toi_thieu,
        bt.trang_thai,
        bt.ngay_tao,

        COALESCE(
            tk.so_luong,
            bt.ton_kho,
            0
        ) AS ton_thuc_te

    FROM BIEN_THESANPHAM bt

    LEFT JOIN TONKHO tk
        ON tk.ma_bien_the =
           bt.ma_bien_the

    WHERE bt.ma_san_pham = ?

    ORDER BY
        bt.trang_thai DESC,
        bt.ma_bien_the DESC
");

$stmt->execute([
    $ma_san_pham
]);

$variants = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


$page_title = 'Biến thể / SKU';


require_once __DIR__ . '/../includes/header.php';

?>


<style>

.variant-page {
    max-width: 1400px;
    margin: 0 auto;
}


/* HEADER */

.variant-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 22px;
}

.variant-title h2 {
    margin: 0;

    font-size: 26px;
}

.variant-title p {
    margin: 6px 0 0;

    color: #64748b;
}


/* PRODUCT INFO */

.product-info {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}

.info-card {
    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 16px;
}

.info-card-label {
    font-size: 12px;

    color: #64748b;

    margin-bottom: 6px;
}

.info-card-value {
    font-size: 16px;

    font-weight: 600;

    color: #0f172a;
}


/* ADD FORM */

.add-variant-card {
    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    margin-bottom: 22px;

    overflow: hidden;
}

.add-variant-header {
    padding: 18px 20px;

    border-bottom: 1px solid #e5e7eb;

    display: flex;

    align-items: center;

    gap: 10px;
}

.add-variant-header h3 {
    margin: 0;

    font-size: 17px;
}

.add-variant-body {
    padding: 20px;
}

.form-grid {
    display: grid;

    grid-template-columns:
        1fr 1.5fr 1fr 1fr auto;

    gap: 15px;

    align-items: end;
}

.form-group label {
    display: block;

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 7px;

    color: #334155;
}

.required {
    color: #dc2626;
}


/* TABLE */

.variant-table-card {
    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    overflow: hidden;
}

.variant-table-header {
    padding: 18px 20px;

    border-bottom: 1px solid #e5e7eb;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.variant-table-header h3 {
    margin: 0;

    font-size: 17px;
}

.table-wrapper {
    overflow-x: auto;
}

.variant-table {
    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}

.variant-table th {
    background: #f8fafc;

    padding: 12px 14px;

    text-align: left;

    font-size: 12px;

    font-weight: 700;

    color: #475569;

    border-bottom: 1px solid #e5e7eb;
}

.variant-table td {
    padding: 12px 14px;

    border-bottom: 1px solid #f1f5f9;

    vertical-align: middle;
}

.variant-table tbody tr:hover {
    background: #f8fafc;
}


/* SKU */

.sku-code {
    font-family: monospace;

    font-weight: 700;

    color: #2563eb;

    background: #eff6ff;

    padding: 5px 8px;

    border-radius: 5px;

    display: inline-block;
}


/* STOCK */

.stock-number {
    font-weight: 700;
}

.stock-low {
    color: #dc2626;
}

.stock-ok {
    color: #16a34a;
}


/* STATUS */

.status-badge {
    display: inline-flex;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;
}

.status-active {
    background: #dcfce7;

    color: #166534;
}

.status-inactive {
    background: #f1f5f9;

    color: #64748b;
}


/* INLINE FORM */

.inline-form {
    display: flex;

    gap: 6px;

    align-items: center;
}

.inline-input {
    width: 100px;
}

.inline-name {
    width: 190px;
}


/* EMPTY */

.empty-state {
    text-align: center;

    padding: 50px 20px;

    color: #64748b;
}

.empty-state i {
    font-size: 40px;

    margin-bottom: 12px;

    color: #cbd5e1;
}


/* ERROR */

.error-box {
    background: #fef2f2;

    border: 1px solid #fecaca;

    color: #991b1b;

    border-radius: 10px;

    padding: 14px 18px;

    margin-bottom: 20px;
}

.error-box ul {
    margin: 8px 0 0 20px;
}


/* RESPONSIVE */

@media (max-width: 1000px) {

    .product-info {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .form-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .variant-header {
        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .product-info {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="variant-page">


    <!-- HEADER -->

    <div class="variant-header">

        <div class="variant-title">

            <h2>

                <i class="fas fa-cubes"></i>

                Biến thể / SKU

            </h2>

            <p>

                Quản lý các SKU thuộc sản phẩm:

                <strong>
                    <?= htmlspecialchars(
                        $product['ten_san_pham']
                    ) ?>
                </strong>

            </p>

        </div>


        <a
            href="<?= url('sanpham/index.php') ?>"
            class="btn btn-secondary"
        >

            <i class="fas fa-arrow-left"></i>

            Danh sách sản phẩm

        </a>

    </div>


    <!-- ERROR -->

    <?php if (!empty($errors)): ?>

        <div class="error-box">

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


    <!-- PRODUCT INFO -->

    <div class="product-info">


        <div class="info-card">

            <div class="info-card-label">
                Mã sản phẩm
            </div>

            <div class="info-card-value">
                <?= htmlspecialchars(
                    $product['ma_sp']
                ) ?>
            </div>

        </div>


        <div class="info-card">

            <div class="info-card-label">
                Loại sản phẩm
            </div>

            <div class="info-card-value">
                <?= htmlspecialchars(
                    $product['ten_loai']
                    ?? 'Chưa phân loại'
                ) ?>
            </div>

        </div>


        <div class="info-card">

            <div class="info-card-label">
                Thương hiệu
            </div>

            <div class="info-card-value">
                <?= htmlspecialchars(
                    $product['ten_thuong_hieu']
                    ?? 'Chưa có'
                ) ?>
            </div>

        </div>


        <div class="info-card">

            <div class="info-card-label">
                Số biến thể
            </div>

            <div class="info-card-value">

                <?= count($variants) ?>

                SKU

            </div>

        </div>


    </div>


    <!-- ADD SKU -->

    <div class="add-variant-card">

        <div class="add-variant-header">

            <i class="fas fa-plus-circle"></i>

            <h3>
                Thêm biến thể / SKU
            </h3>

        </div>


        <div class="add-variant-body">

            <form method="POST">

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            SKU
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="sku"
                            class="form-control"
                            placeholder="VD: SP001-DO-M"
                            maxlength="50"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tên biến thể
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="ten_bien_the"
                            class="form-control"
                            placeholder="VD: Đỏ / Size M"
                            maxlength="200"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Giá bán
                        </label>

                        <input
                            type="number"
                            name="gia_ban"
                            class="form-control"
                            min="0"
                            step="1000"
                            value="<?= htmlspecialchars(
                                $product['gia_ban'] ?? 0
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tồn tối thiểu
                        </label>

                        <input
                            type="number"
                            name="ton_toi_thieu"
                            class="form-control"
                            min="0"
                            value="5"
                        >

                    </div>


                    <div>

                        <button
                            type="submit"
                            name="add_sku"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-plus"></i>

                            Thêm

                        </button>

                    </div>


                </div>

            </form>

        </div>

    </div>


    <!-- LIST -->

    <div class="variant-table-card">


        <div class="variant-table-header">

            <h3>

                <i class="fas fa-list"></i>

                Danh sách SKU

            </h3>

            <span>
                <?= count($variants) ?> biến thể
            </span>

        </div>


        <div class="table-wrapper">

            <table class="variant-table">

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

                        <td
                            colspan="7"
                            class="empty-state"
                        >

                            <i class="fas fa-cubes"></i>

                            <div>
                                Sản phẩm chưa có biến thể.
                            </div>

                            <small>
                                Hãy thêm SKU ở phía trên.
                            </small>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach ($variants as $variant): ?>

                    <tr>


                        <!-- SKU -->

                        <td>

                            <span class="sku-code">

                                <?= htmlspecialchars(
                                    $variant['sku']
                                ) ?>

                            </span>

                        </td>


                        <!-- NAME -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $variant['ten_bien_the']
                                ) ?>

                            </strong>

                        </td>


                        <!-- PRICE -->

                        <td>

                            <?= number_format(
                                $variant['gia_ban'],
                                0,
                                ',',
                                '.'
                            ) ?>

                            ₫

                        </td>


                        <!-- STOCK -->

                        <td>

                            <?php

                            $stock =
                                (int)$variant[
                                    'ton_thuc_te'
                                ];

                            $minStock =
                                (int)$variant[
                                    'ton_toi_thieu'
                                ];

                            ?>

                            <span
                                class="stock-number
                                <?= $stock <= $minStock
                                    ? 'stock-low'
                                    : 'stock-ok'
                                ?>"
                            >

                                <?= number_format(
                                    $stock
                                ) ?>

                            </span>

                        </td>


                        <!-- MIN -->

                        <td>

                            <?= number_format(
                                $variant[
                                    'ton_toi_thieu'
                                ]
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?php if (
                                $variant['trang_thai']
                            ): ?>

                                <span
                                    class="status-badge
                                    status-active"
                                >
                                    Đang hoạt động
                                </span>

                            <?php else: ?>

                                <span
                                    class="status-badge
                                    status-inactive"
                                >
                                    Đang ẩn
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <div
                                style="
                                    display:flex;
                                    gap:6px;
                                "
                            >

                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    onclick="openEditSKU(
                                        <?= (int)$variant['ma_bien_the'] ?>,
                                        '<?= htmlspecialchars(
                                            $variant['sku'],
                                            ENT_QUOTES
                                        ) ?>',
                                        '<?= htmlspecialchars(
                                            $variant['ten_bien_the'],
                                            ENT_QUOTES
                                        ) ?>',
                                        <?= (float)$variant['gia_ban'] ?>,
                                        <?= (int)$variant['ton_toi_thieu'] ?>,
                                        <?= (int)$variant['trang_thai'] ?>
                                    )"
                                >

                                    <i class="fas fa-edit"></i>

                                </button>


                                <?php if (
                                    $variant['trang_thai']
                                ): ?>

                                    <form
                                        method="POST"
                                        onsubmit="
                                            return confirm(
                                                'Bạn có chắc muốn ẩn SKU này?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="ma_bien_the"
                                            value="<?= (int)$variant[
                                                'ma_bien_the'
                                            ] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="hide_sku"
                                            class="btn btn-sm btn-danger"
                                            title="Ẩn SKU"
                                        >

                                            <i
                                                class="
                                                fas fa-eye-slash
                                                "
                                            ></i>

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="ma_bien_the"
                                            value="<?= (int)$variant[
                                                'ma_bien_the'
                                            ] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="show_sku"
                                            class="btn btn-sm btn-success"
                                            title="Hiện SKU"
                                        >

                                            <i
                                                class="
                                                fas fa-eye
                                                "
                                            ></i>

                                        </button>

                                    </form>

                                <?php endif; ?>

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
     MODAL SỬA SKU
========================================================== -->

<div
    id="editSkuModal"
    style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15,23,42,.55);
        z-index:9999;
        align-items:center;
        justify-content:center;
        padding:20px;
    "
>


    <div
        style="
            width:100%;
            max-width:600px;
            background:#fff;
            border-radius:14px;
            overflow:hidden;
        "
    >


        <div
            style="
                padding:18px 20px;
                border-bottom:1px solid #e5e7eb;
                display:flex;
                justify-content:space-between;
                align-items:center;
            "
        >

            <h3 style="margin:0;">

                <i class="fas fa-edit"></i>

                Sửa biến thể / SKU

            </h3>


            <button
                type="button"
                onclick="closeEditSKU()"
                style="
                    border:0;
                    background:none;
                    font-size:20px;
                    cursor:pointer;
                "
            >
                &times;
            </button>

        </div>


        <form method="POST">


            <div style="padding:20px;">

                <input
                    type="hidden"
                    name="ma_bien_the"
                    id="edit_ma_bien_the"
                >


                <div class="form-group">

                    <label>
                        SKU
                    </label>

                    <input
                        type="text"
                        name="sku"
                        id="edit_sku"
                        class="form-control"
                        maxlength="50"
                        required
                    >

                </div>


                <div
                    class="form-group"
                    style="margin-top:15px;"
                >

                    <label>
                        Tên biến thể
                    </label>

                    <input
                        type="text"
                        name="ten_bien_the"
                        id="edit_ten_bien_the"
                        class="form-control"
                        maxlength="200"
                        required
                    >

                </div>


                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:15px;
                        margin-top:15px;
                    "
                >

                    <div class="form-group">

                        <label>
                            Giá bán
                        </label>

                        <input
                            type="number"
                            name="gia_ban"
                            id="edit_gia_ban"
                            class="form-control"
                            min="0"
                            step="1000"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tồn tối thiểu
                        </label>

                        <input
                            type="number"
                            name="ton_toi_thieu"
                            id="edit_ton_toi_thieu"
                            class="form-control"
                            min="0"
                            required
                        >

                    </div>

                </div>


                <div
                    style="
                        margin-top:15px;
                    "
                >

                    <label
                        style="
                            display:flex;
                            align-items:center;
                            gap:8px;
                            cursor:pointer;
                        "
                    >

                        <input
                            type="checkbox"
                            name="trang_thai"
                            id="edit_trang_thai"
                            value="1"
                        >

                        SKU đang hoạt động

                    </label>

                </div>

            </div>


            <div
                style="
                    padding:16px 20px;
                    background:#f8fafc;
                    border-top:1px solid #e5e7eb;
                    display:flex;
                    justify-content:flex-end;
                    gap:8px;
                "
            >

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeEditSKU()"
                >
                    Hủy
                </button>


                <button
                    type="submit"
                    name="update_sku"
                    class="btn btn-primary"
                >

                    <i class="fas fa-save"></i>

                    Lưu thay đổi

                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openEditSKU(
    id,
    sku,
    name,
    price,
    minStock,
    status
) {

    document.getElementById(
        'edit_ma_bien_the'
    ).value = id;

    document.getElementById(
        'edit_sku'
    ).value = sku;

    document.getElementById(
        'edit_ten_bien_the'
    ).value = name;

    document.getElementById(
        'edit_gia_ban'
    ).value = price;

    document.getElementById(
        'edit_ton_toi_thieu'
    ).value = minStock;

    document.getElementById(
        'edit_trang_thai'
    ).checked = status == 1;

    document.getElementById(
        'editSkuModal'
    ).style.display = 'flex';
}


function closeEditSKU() {

    document.getElementById(
        'editSkuModal'
    ).style.display = 'none';
}


document
    .getElementById('editSkuModal')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {
                closeEditSKU();
            }

        }
    );

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>