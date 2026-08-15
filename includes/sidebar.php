<?php
require_once __DIR__ . '/../config/config.php';
$current = getCurrentModule();
$menuItems = [
    ['icon' => 'fa-chart-line', 'label' => 'Dashboard', 'module' => 'dashboard', 'perm' => 'dashboard_view', 'link' => 'dashboard/index.php'],
    ['icon' => 'fa-cash-register', 'label' => 'Bán hàng', 'module' => 'banhang', 'perm' => 'banhang', 'link' => 'banhang/index.php', 'submenu' => [
        ['label' => 'Tạo hóa đơn', 'link' => 'banhang/index.php'],
        ['label' => 'Lịch sử hóa đơn', 'link' => 'banhang/hoadon.php'],
        ['label' => 'Trả hàng / Đổi hàng', 'link' => 'banhang/trahang.php'],
    ]],
    ['icon' => 'fa-box', 'label' => 'Sản phẩm', 'module' => 'sanpham', 'perm' => 'sanpham', 'link' => 'sanpham/index.php', 'submenu' => [
        ['label' => 'Sản phẩm', 'link' => 'sanpham/index.php'],
        ['label' => 'Biến thể / SKU', 'link' => 'sanpham/bienthe.php'],
        ['label' => 'Loại sản phẩm', 'link' => 'sanpham/loai.php'],
        ['label' => 'Thương hiệu', 'link' => 'sanpham/thuonghieu.php'],
        ['label' => 'Lịch sử giá', 'link' => 'sanpham/lichsugia.php'],
    ]],
    ['icon' => 'fa-truck-loading', 'label' => 'Nhập hàng', 'module' => 'nhaphang', 'perm' => 'nhaphang', 'link' => 'nhaphang/index.php', 'submenu' => [
        ['label' => 'Nhà cung cấp', 'link' => 'nhaphang/nhacungcap.php'],
        ['label' => 'Phiếu nhập', 'link' => 'nhaphang/index.php'],
        ['label' => 'Trả hàng NCC', 'link' => 'nhaphang/trahang.php'],
    ]],
    ['icon' => 'fa-warehouse', 'label' => 'Kho', 'module' => 'kho', 'perm' => 'kho', 'link' => 'kho/index.php', 'submenu' => [
        ['label' => 'Tồn kho', 'link' => 'kho/index.php'],
        ['label' => 'Điều chỉnh tồn kho', 'link' => 'kho/dieuchinh.php'],
        ['label' => 'Lịch sử kho', 'link' => 'kho/lichsu.php'],
    ]],
    ['icon' => 'fa-users', 'label' => 'Khách hàng', 'module' => 'khachhang', 'perm' => 'khachhang', 'link' => 'khachhang/index.php', 'submenu' => [
        ['label' => 'Danh sách KH', 'link' => 'khachhang/index.php'],
        ['label' => 'Lịch sử mua hàng', 'link' => 'khachhang/lichsu.php'],
        ['label' => 'Tích điểm', 'link' => 'khachhang/tichdiem.php'],
    ]],
    ['icon' => 'fa-tags', 'label' => 'Khuyến mãi', 'module' => 'khuyenmai', 'perm' => 'khuyenmai', 'link' => 'khuyenmai/index.php'],
    ['icon' => 'fa-user-tie', 'label' => 'Nhân viên', 'module' => 'nhanvien', 'perm' => 'nhanvien', 'link' => 'nhanvien/index.php', 'submenu' => [
        ['label' => 'Nhân viên', 'link' => 'nhanvien/index.php'],
        ['label' => 'Tài khoản', 'link' => 'nhanvien/taikhoan.php'],
        ['label' => 'Vai trò', 'link' => 'nhanvien/vaitro.php'],
    ]],
    ['icon' => 'fa-chart-bar', 'label' => 'Báo cáo', 'module' => 'baocao', 'perm' => 'baocao', 'link' => 'baocao/index.php', 'submenu' => [
        ['label' => 'Doanh thu', 'link' => 'baocao/doanhthu.php'],
        ['label' => 'SP bán chạy', 'link' => 'baocao/banchay.php'],
        ['label' => 'Tồn kho', 'link' => 'baocao/tonkho.php'],
        ['label' => 'Khách hàng', 'link' => 'baocao/khachhang.php'],
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
    echo '<span>' . htmlspecialchars($item['label']) . '</span>';
    if ($hasSub) echo '<i class="fas fa-chevron-right submenu-arrow"></i>';
    echo '</a>';
    if ($hasSub) {
        echo '<ul class="submenu">';
        foreach ($item['submenu'] as $sub) {
            echo '<li><a href="' . url($sub['link']) . '">' . htmlspecialchars($sub['label']) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</li>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-store"></i>
        <span>QUẢN LÝ BÁN HÀNG</span>
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
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</aside>