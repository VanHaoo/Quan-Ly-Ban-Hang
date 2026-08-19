<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requirePermission('sanpham');

$page_title = 'Quản lý sản phẩm';

/* =========================================================
 * THỐNG KÊ
 * ========================================================= */

$totalProducts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM SANPHAM
")->fetchColumn();

$totalVariants = (int)$pdo->query("
    SELECT COUNT(*)
    FROM BIEN_THESANPHAM
")->fetchColumn();

$activeProducts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM SANPHAM
    WHERE trang_thai = 1
")->fetchColumn();

$lowStockCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM BIEN_THESANPHAM bt
    LEFT JOIN TONKHO tk
        ON tk.ma_bien_the = bt.ma_bien_the
    WHERE bt.trang_thai = 1
      AND COALESCE(tk.so_luong,0) <= bt.ton_toi_thieu
")->fetchColumn();


/* =========================================================
 * BỘ LỌC
 * ========================================================= */

$search = trim($_GET['search'] ?? '');
$maLoai = (int)($_GET['ma_loai'] ?? 0);
$maThuongHieu = (int)($_GET['ma_thuong_hieu'] ?? 0);
$status = $_GET['status'] ?? '';

$categories = $pdo->query("
    SELECT *
    FROM LOAISANPHAM
    ORDER BY ten_loai ASC
")->fetchAll();

$brands = $pdo->query("
    SELECT *
    FROM THUONGHIEU
    ORDER BY ten_thuong_hieu ASC
")->fetchAll();


/* =========================================================
 * QUERY SẢN PHẨM
 * ========================================================= */

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "(
        sp.ma_sp LIKE ?
        OR sp.ten_san_pham LIKE ?
        OR l.ten_loai LIKE ?
        OR th.ten_thuong_hieu LIKE ?
        OR EXISTS (
            SELECT 1
            FROM BIEN_THESANPHAM bt
            WHERE bt.ma_san_pham = sp.ma_san_pham
            AND (
                bt.sku LIKE ?
                OR bt.ten_bien_the LIKE ?
            )
        )
    )";

    $keyword = "%$search%";

    $params = [
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ];
}

if ($maLoai > 0) {
    $where[] = "sp.ma_loai = ?";
    $params[] = $maLoai;
}

if ($maThuongHieu > 0) {
    $where[] = "sp.ma_thuong_hieu = ?";
    $params[] = $maThuongHieu;
}

if ($status === '1' || $status === '0') {
    $where[] = "sp.trang_thai = ?";
    $params[] = $status;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}


$sql = "

SELECT

    sp.ma_san_pham,
    sp.ma_sp,
    sp.ten_san_pham,
    sp.gia_ban,
    sp.trang_thai,
    sp.ngay_tao,

    l.ten_loai,
    th.ten_thuong_hieu,
    d.ten_dvt,

    COUNT(DISTINCT bt.ma_bien_the) AS so_sku,

    MIN(bt.gia_ban) AS gia_min,
    MAX(bt.gia_ban) AS gia_max

FROM SANPHAM sp

LEFT JOIN LOAISANPHAM l
ON sp.ma_loai = l.ma_loai

LEFT JOIN THUONGHIEU th
ON sp.ma_thuong_hieu = th.ma_thuong_hieu

LEFT JOIN DONVITINH d
ON sp.ma_dvt = d.ma_dvt

LEFT JOIN BIEN_THESANPHAM bt
ON sp.ma_san_pham = bt.ma_san_pham

$whereSql

GROUP BY
    sp.ma_san_pham,
    sp.ma_sp,
    sp.ten_san_pham,
    sp.gia_ban,
    sp.trang_thai,
    sp.ngay_tao,
    l.ten_loai,
    th.ten_thuong_hieu,
    d.ten_dvt

ORDER BY sp.ma_san_pham DESC

";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll();


/* =========================================================
 * LẤY SKU
 * ========================================================= */

$productIds = array_column($products, 'ma_san_pham');

$variantsByProduct = [];

if (!empty($productIds)) {

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare("
        SELECT

            bt.ma_bien_the,
            bt.ma_san_pham,
            bt.sku,
            bt.ten_bien_the,
            bt.gia_ban,
            bt.ton_toi_thieu,
            bt.trang_thai,

            COALESCE(tk.so_luong,0) AS ton_kho

        FROM BIEN_THESANPHAM bt

        LEFT JOIN TONKHO tk
        ON tk.ma_bien_the = bt.ma_bien_the

        WHERE bt.ma_san_pham IN ($placeholders)

        ORDER BY bt.ma_san_pham, bt.ma_bien_the
    ");

    $stmt->execute($productIds);

    $variants = $stmt->fetchAll();

    foreach ($variants as $item) {

        $variantsByProduct[$item['ma_san_pham']][] = $item;

    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>

/* ================= PAGE ================= */

.product-page{
    width:100%;
}

.product-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:22px;
}

.product-header h2{
    margin:0;
    font-size:26px;
}

.product-header p{
    margin-top:5px;
    color:#64748b;
}


/* ================= STAT ================= */

.stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:20px;
}

.stat-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:18px;
    display:flex;
    align-items:center;
    gap:14px;
}

.stat-icon{
    width:46px;
    height:46px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
}

.blue{
    background:#dbeafe;
    color:#2563eb;
}

.purple{
    background:#ede9fe;
    color:#7c3aed;
}

.green{
    background:#dcfce7;
    color:#16a34a;
}

.red{
    background:#fee2e2;
    color:#dc2626;
}

.stat-title{
    font-size:13px;
    color:#64748b;
}

.stat-number{
    font-size:22px;
    font-weight:bold;
    margin-top:3px;
}


/* ================= FILTER ================= */

.filter-box{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:15px;
    margin-bottom:20px;
}

.filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr 1fr auto;
    gap:10px;
}

.search-input{
    position:relative;
}

.search-input i{
    position:absolute;
    left:13px;
    top:50%;
    transform:translateY(-50%);
    color:#9ca3af;
}

.search-input input{
    padding-left:38px!important;
}


/* ================= TABLE ================= */

.table-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    overflow:hidden;
}

.table-responsive{
    overflow-x:auto;
}

.product-table{
    width:100%;
    border-collapse:collapse;
    min-width:1000px;
}

.product-table thead{
    background:#f8fafc;
}

.product-table th{
    padding:14px 12px;
    font-size:12px;
    text-transform:uppercase;
    color:#64748b;
}

.product-table td{
    padding:14px 12px;
    border-top:1px solid #eef2f7;
}

.product-row:hover{
    background:#f8fbff;
}


/* ================= SKU ================= */

.expand-btn{
    width:30px;
    height:30px;
    border-radius:7px;
    border:1px solid #d1d5db;
    background:#fff;
    cursor:pointer;
}

.expand-btn:hover{
    background:#eff6ff;
    color:#2563eb;
}

.expand-btn i{
    transition:.2s;
}

.expand-btn.open i{
    transform:rotate(90deg);
}

.sku-row{
    display:none;
    background:#f8fafc;
}

.sku-panel{
    padding:18px 35px 22px 55px;
}

.sku-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:12px;
}

.sku-title{
    font-size:15px;
    font-weight:bold;
}

.sku-table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:8px;
    overflow:hidden;
}

.sku-table th{
    background:#f1f5f9;
    padding:10px;
    font-size:11px;
    text-transform:uppercase;
}

.sku-table td{
    padding:11px;
    border-top:1px solid #e5e7eb;
}

.stock-low{
    color:#dc2626;
    font-weight:bold;
}

.stock-ok{
    color:#16a34a;
    font-weight:bold;
}


/* ================= BADGE ================= */

.badge-sku{
    background:#dbeafe;
    color:#2563eb;
    padding:5px 10px;
    border-radius:6px;
    font-weight:bold;
}

.status-active{
    color:#15803d;
    background:#dcfce7;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}

.status-stop{
    color:#b91c1c;
    background:#fee2e2;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}


/* ================= ACTION ================= */

.action-group{
    display:flex;
    justify-content:center;
    gap:6px;
}

.action-btn{
    width:32px;
    height:32px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:7px;
    text-decoration:none;
}

.btn-edit{
    background:#f3f4f6;
    color:#374151;
}

.btn-sku{
    background:#dbeafe;
    color:#2563eb;
}

.btn-history{
    background:#ede9fe;
    color:#7c3aed;
}


/* ================= MOBILE ================= */

@media(max-width:900px){

.stats-grid{
    grid-template-columns:repeat(2,1fr);
}

.filter-grid{
    grid-template-columns:1fr;
}

.product-header{
    flex-direction:column;
    align-items:flex-start;
    gap:15px;
}

}

@media(max-width:550px){

.stats-grid{
    grid-template-columns:1fr;
}

}

</style>


<div class="product-page">

<!-- HEADER -->

<div class="product-header">

    <div>

        <h2>
            <i class="fas fa-box-open"></i>
            Quản lý sản phẩm
        </h2>

        <p>
            Quản lý sản phẩm, biến thể SKU và giá bán.
        </p>

    </div>

    <a href="<?= url('sanpham/create.php') ?>" class="btn btn-primary">

        <i class="fas fa-plus"></i>

        Thêm sản phẩm

    </a>

</div>


<!-- THỐNG KÊ -->

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-icon blue">
            <i class="fas fa-box"></i>
        </div>

        <div>
            <div class="stat-title">Tổng sản phẩm</div>
            <div class="stat-number"><?= $totalProducts ?></div>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon purple">
            <i class="fas fa-cubes"></i>
        </div>

        <div>
            <div class="stat-title">Tổng SKU</div>
            <div class="stat-number"><?= $totalVariants ?></div>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>

        <div>
            <div class="stat-title">Đang kinh doanh</div>
            <div class="stat-number"><?= $activeProducts ?></div>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>

        <div>
            <div class="stat-title">SKU sắp hết</div>
            <div class="stat-number"><?= $lowStockCount ?></div>
        </div>

    </div>

</div>


<!-- FILTER -->

<div class="filter-box">

<form method="GET">

<div class="filter-grid">

<div class="search-input">

<i class="fas fa-search"></i>

<input
type="text"
name="search"
class="form-control"
placeholder="Tìm tên sản phẩm hoặc SKU..."
value="<?= htmlspecialchars($search) ?>"
>

</div>


<select name="ma_loai" class="form-control">

<option value="0">Tất cả loại</option>

<?php foreach($categories as $c): ?>

<option
value="<?= $c['ma_loai'] ?>"
<?= $maLoai==$c['ma_loai']?'selected':'' ?>
>

<?= htmlspecialchars($c['ten_loai']) ?>

</option>

<?php endforeach; ?>

</select>


<select name="ma_thuong_hieu" class="form-control">

<option value="0">Tất cả thương hiệu</option>

<?php foreach($brands as $b): ?>

<option
value="<?= $b['ma_thuong_hieu'] ?>"
<?= $maThuongHieu==$b['ma_thuong_hieu']?'selected':'' ?>
>

<?= htmlspecialchars($b['ten_thuong_hieu']) ?>

</option>

<?php endforeach; ?>

</select>


<select name="status" class="form-control">

<option value="">Trạng thái</option>

<option value="1" <?= $status==='1'?'selected':'' ?>>
Đang bán
</option>

<option value="0" <?= $status==='0'?'selected':'' ?>>
Ngừng bán
</option>

</select>


<div style="display:flex;gap:8px">

<button class="btn btn-primary">

<i class="fas fa-filter"></i>

Lọc

</button>

<a
href="<?= url('sanpham/index.php') ?>"
class="btn btn-secondary"
>

<i class="fas fa-redo"></i>

</a>

</div>

</div>

</form>

</div>


<!-- TABLE -->

<div class="table-card">

<div class="table-responsive">

<table class="product-table">

<thead>

<tr>

<th width="45"></th>

<th>Mã SP</th>

<th>Sản phẩm</th>

<th>Loại</th>

<th>Thương hiệu</th>

<th class="text-center">SKU</th>

<th class="text-right">Giá bán</th>

<th class="text-center">Trạng thái</th>

<th class="text-center">Thao tác</th>

</tr>

</thead>


<tbody>

<?php if(empty($products)): ?>

<tr>

<td colspan="9" style="text-align:center;padding:40px">

<i class="fas fa-box-open" style="font-size:35px;color:#cbd5e1"></i>

<p>Không có sản phẩm.</p>

</td>

</tr>

<?php endif; ?>


<?php foreach($products as $p): ?>

<?php

$productId=$p['ma_san_pham'];

$list=$variantsByProduct[$productId]??[];

?>

<tr class="product-row">

<td class="text-center">

<?php if(!empty($list)): ?>

<button
class="expand-btn"
onclick="toggleSKU(<?= $productId ?>,this)"
type="button"
>

<i class="fas fa-chevron-right"></i>

</button>

<?php endif; ?>

</td>


<td>

<strong style="color:#2563eb">

<?= htmlspecialchars($p['ma_sp']) ?>

</strong>

</td>


<td>

<strong>

<?= htmlspecialchars($p['ten_san_pham']) ?>

</strong>

<div style="font-size:11px;color:#9ca3af;margin-top:3px">

<?= htmlspecialchars($p['ten_dvt']??'') ?>

</div>

</td>


<td>

<?= htmlspecialchars($p['ten_loai']??'-') ?>

</td>


<td>

<?= htmlspecialchars($p['ten_thuong_hieu']??'-') ?>

</td>


<td class="text-center">

<span class="badge-sku">

<?= $p['so_sku'] ?>

</span>

</td>


<td class="text-right">

<?php if($p['gia_min']): ?>

<strong>

<?php if($p['gia_min']==$p['gia_max']): ?>

<?= formatMoney($p['gia_min']) ?>

<?php else: ?>

<?= formatMoney($p['gia_min']) ?>

-

<?= formatMoney($p['gia_max']) ?>

<?php endif; ?>

</strong>

<?php else: ?>

<?= formatMoney($p['gia_ban']) ?>

<?php endif; ?>

</td>


<td class="text-center">

<?php if($p['trang_thai']==1): ?>

<span class="status-active">

<i class="fas fa-check-circle"></i>

Đang bán

</span>

<?php else: ?>

<span class="status-stop">

<i class="fas fa-times-circle"></i>

Ngừng

</span>

<?php endif; ?>

</td>


<td>

<div class="action-group">

<a
href="<?= url('sanpham/edit.php?id='.$productId) ?>"
class="action-btn btn-edit"
title="Sửa"
>

<i class="fas fa-edit"></i>

</a>


<a
href="<?= url('sanpham/bienthe.php?ma_sp='.$productId) ?>"
class="action-btn btn-sku"
title="Quản lý SKU"
>

<i class="fas fa-cubes"></i>

</a>


<a
href="<?= url('sanpham/lichsugia.php?ma_sp='.$productId) ?>"
class="action-btn btn-history"
title="Lịch sử giá"
>

<i class="fas fa-history"></i>

</a>

</div>

</td>

</tr>


<!-- SKU -->

<tr
id="sku<?= $productId ?>"
class="sku-row"
>

<td colspan="9">

<div class="sku-panel">

<div class="sku-header">

<div>

<div class="sku-title">

<i class="fas fa-layer-group"></i>

Biến thể / SKU

</div>

<div style="font-size:12px;color:#64748b">

<?= htmlspecialchars($p['ma_sp']) ?>

-

<?= htmlspecialchars($p['ten_san_pham']) ?>

</div>

</div>

<a
href="<?= url('sanpham/bienthe.php?ma_sp='.$productId) ?>"
class="btn btn-primary btn-sm"
>

<i class="fas fa-cog"></i>

Quản lý SKU

</a>

</div>


<?php if(empty($list)): ?>

<div style="text-align:center;padding:20px;color:#9ca3af">

Chưa có biến thể.

</div>

<?php else: ?>

<table class="sku-table">

<thead>

<tr>

<th>SKU</th>

<th>Biến thể</th>

<th class="text-right">Giá</th>

<th class="text-right">Tồn kho</th>

<th class="text-right">Tối thiểu</th>

<th class="text-center">Trạng thái</th>

</tr>

</thead>

<tbody>

<?php foreach($list as $v): ?>

<tr>

<td>

<strong style="color:#2563eb">

<?= htmlspecialchars($v['sku']) ?>

</strong>

</td>

<td>

<?= htmlspecialchars($v['ten_bien_the']) ?>

</td>

<td class="text-right">

<?= formatMoney($v['gia_ban']) ?>

</td>

<td class="text-right">

<?php if($v['ton_kho'] <= $v['ton_toi_thieu']): ?>

<span class="stock-low">

<i class="fas fa-exclamation-triangle"></i>

<?= $v['ton_kho'] ?>

</span>

<?php else: ?>

<span class="stock-ok">

<?= $v['ton_kho'] ?>

</span>

<?php endif; ?>

</td>

<td class="text-right">

<?= $v['ton_toi_thieu'] ?>

</td>

<td class="text-center">

<?php if($v['trang_thai']==1): ?>

<span class="status-active">

Đang bán

</span>

<?php else: ?>

<span class="status-stop">

Ngừng

</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

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


<script>

function toggleSKU(id,btn){

const row=document.getElementById('sku'+id);

const opened=row.style.display==='table-row';

document.querySelectorAll('.sku-row').forEach(r=>{
r.style.display='none';
});

document.querySelectorAll('.expand-btn').forEach(b=>{
b.classList.remove('open');
});

if(!opened){

row.style.display='table-row';

btn.classList.add('open');

}

}

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>