<?php
require_once __DIR__ . '/../config/config.php';
$current = getCurrentModule();
$menuItems = [
    ['icon' => 'fa-chart-line', 'label' => 'Dashboard', 'module' => 'dashboard', 'perm' => 'dashboard_view', 'link' => 'dashboard/index.php'],
    ['icon' => 'fa-cash-register', 'label' => 'Ban hang', 'module' => 'banhang', 'perm' => 'banhang', 'link' => 'banhang/index.php', 'submenu' => [
        ['label' => 'Tao hoa don', 'link' => 'banhang/index.php'],
        ['label' => 'Lich su hoa don', 'link' => 'banhang/hoadon.php'],
        ['label' => 'Tra hang / Doi hang', 'link' => 'banhang/trahang.php'],
    ]],
    ['icon' => 'fa-box', 'label' => 'San pham', 'module' => 'sanpham', 'perm' => 'sanpham', 'link' => 'sanpham/index.php', 'submenu' => [
        ['label' => 'San pham', 'link' => 'sanpham/index.php'],
        ['label' => 'Bien the / SKU', 'link' => 'sanpham/bienthe.php'],
        ['label' => 'Loai san pham', 'link' => 'sanpham/loai.php'],
        ['label' => 'Thuong hieu', 'link' => 'sanpham/thuonghieu.php'],
        ['label' => 'Lich su gia', 'link' => 'sanpham/lichsugia.php'],
    ]],
    ['icon' => 'fa-truck-loading', 'label' => 'Nhap hang', 'module' => 'nhaphang', 'perm' => 'nhaphang', 'link' => 'nhaphang/index.php', 'submenu' => [
        ['label' => 'Nha cung cap', 'link' => 'nhaphang/nhacungcap.php'],
        ['label' => 'Phieu nhap', 'link' => 'nhaphang/index.php'],
        ['label' => 'Tra hang NCC', 'link' => 'nhaphang/trahang.php'],
    ]],
    ['icon' => 'fa-warehouse', 'label' => 'Kho', 'module' => 'kho', 'perm' => 'kho', 'link' => 'kho/index.php', 'submenu' => [
        ['label' => 'Ton kho', 'link' => 'kho/index.php'],
        ['label' => 'Dieu chinh ton kho', 'link' => 'kho/dieuchinh.php'],
        ['label' => 'Lich su kho', 'link' => 'kho/lichsu.php'],
    ]],
    ['icon' => 'fa-users', 'label' => 'Khach hang', 'module' => 'khachhang', 'perm' => 'khachhang', 'link' => 'khachhang/index.php', 'submenu' => [
        ['label' => 'Danh sach KH', 'link' => 'khachhang/index.php'],
        ['label' => 'Lich su mua hang', 'link' => 'khachhang/lichsu.php'],
        ['label' => 'Tich diem', 'link' => 'khachhang/tichdiem.php'],
    ]],
    ['icon' => 'fa-tags', 'label' => 'Khuyen mai', 'module' => 'khuyenmai', 'perm' => 'khuyenmai', 'link' => 'khuyenmai/index.php'],
    ['icon' => 'fa-user-tie', 'label' => 'Nhan vien', 'module' => 'nhanvien', 'perm' => 'nhanvien', 'link' => 'nhanvien/index.php', 'submenu' => [
        ['label' => 'Nhan vien', 'link' => 'nhanvien/index.php'],
        ['label' => 'Tai khoan', 'link' => 'nhanvien/taikhoan.php'],
        ['label' => 'Vai tro', 'link' => 'nhanvien/vaitro.php'],
    ]],
    ['icon' => 'fa-chart-bar', 'label' => 'Bao cao', 'module' => 'baocao', 'perm' => 'baocao', 'link' => 'baocao/index.php', 'submenu' => [
        ['label' => 'Doanh thu', 'link' => 'baocao/doanhthu.php'],
        ['label' => 'SP ban chay', 'link' => 'baocao/banchay.php'],
        ['label' => 'Ton kho', 'link' => 'baocao/tonkho.php'],
        ['label' => 'Khach hang', 'link' => 'baocao/khachhang.php'],
        ['label' => 'Doanh thu NV', 'link' => 'baocao/doanhthu_nv.php'],
    ]],
];

function renderMenuItem($item, $current) {
    $hasPerm = hasPermission($item['perm']);
    if (!$hasPerm) return;
    $isActive = ($current === $item['module']);
    $hasSub = isset($item['submenu']);
    $activeClass = $isActive ? 'active' : '';
    $subClass = ($hasSub && $isActive) ? 'expanded' : '';
    echo '<li class="menu-item ' . $activeClass . ' ' . $subClass . '">';
    echo '<a href="' . url($item['link']) . '" class="menu-link" ' . ($hasSub ? 'onclick="toggleSubmenu(this);return false;"' : '') . '>';
    echo '<i class="fas ' . $item['icon'] . '"></i>';
    echo '<span>' . $item['label'] . '</span>';
    if ($hasSub) echo '<i class="fas fa-chevron-right submenu-arrow"></i>';
    echo '</a>';
    if ($hasSub) {
        echo '<ul class="submenu">';
        foreach ($item['submenu'] as $sub) {
            echo '<li><a href="' . url($sub['link']) . '">' . $sub['label'] . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</li>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-store"></i>
        <span>QUAN LY BAN HANG</span>
    </div>
    <nav class="sidebar-nav">
        <ul class="menu-list">
            <?php foreach ($menuItems as $item) renderMenuItem($item, $current); ?>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-mini">
            <i class="fas fa-user-circle"></i>
            <div>
                <div class="mini-name"><?= htmlspecialchars($ho_ten ?? $_SESSION['ho_ten'] ?? '') ?></div>
                <div class="mini-role"><?= htmlspecialchars($vai_tro ?? $_SESSION['vai_tro'] ?? '') ?></div>
            </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Dang xuat
        </a>
    </div>
</aside>
