<?php
require_once __DIR__ . '/../config/config.php';   // FIX #2: load config TRƯỚC header
require_once __DIR__ . '/../includes/auth.php';
requirePermission('dashboard_view');
$page_title = 'Dashboard';

const DASHBOARD_LIST_LIMIT = 10;
const DASHBOARD_TOPSP_LIMIT = 5;
const DASHBOARD_CHART_DAYS = 7;

$today = date('Y-m-d');

// Doanh thu hôm nay
$stmt = $pdo->prepare("SELECT COALESCE(SUM(tong_thanh_toan), 0) as doanh_thu, COUNT(*) as so_hd
                        FROM HOADON WHERE DATE(ngay_tao) = :today AND trang_thai = 'HOANTAT'");
$stmt->execute(['today' => $today]);
$stats_today = $stmt->fetch();

// Tổng sản phẩm
$total_sp = $pdo->query("SELECT COUNT(*) as total FROM SANPHAM WHERE trang_thai = 1")->fetch()['total'];

// Tổng khách hàng
$total_kh = $pdo->query("SELECT COUNT(*) as total FROM KHACHHANG WHERE trang_thai = 1")->fetch()['total'];

// Sản phẩm sắp hết hàng - đếm + danh sách gom chung 1 chỗ (FIX #5)
$low_stock_list = $pdo->query("
    SELECT * FROM V_TONKHO_CANHBAO
    WHERE trang_thai_ton = 'SAP_HET' COLLATE utf8mb4_unicode_ci
    LIMIT " . DASHBOARD_LIST_LIMIT
)->fetchAll();
$sap_het = count($low_stock_list) === DASHBOARD_LIST_LIMIT
    ? $pdo->query("SELECT COUNT(*) as total FROM V_TONKHO_CANHBAO WHERE trang_thai_ton = 'SAP_HET'")->fetch()['total']
    : count($low_stock_list);

// Hóa đơn gần đây
$recent_invoices = $pdo->query("
    SELECT hd.ma_hd, nv.ho_ten as nhan_vien, hd.tong_thanh_toan, hd.ngay_tao
    FROM HOADON hd
    JOIN NHANVIEN nv ON hd.ma_nhan_vien = nv.ma_nhan_vien
    WHERE hd.trang_thai = 'HOANTAT'
    ORDER BY hd.ngay_tao DESC
    LIMIT " . DASHBOARD_LIST_LIMIT
)->fetchAll();

// Doanh thu N ngày gần nhất - FIX #1: tự sinh đủ ngày, ngày không có DT thì = 0
$raw_revenue = $pdo->query("
    SELECT ngay, doanh_thu FROM V_DOANHTHU_NGAY
    WHERE ngay >= DATE_SUB(CURDATE(), INTERVAL " . (DASHBOARD_CHART_DAYS - 1) . " DAY)
")->fetchAll(PDO::FETCH_KEY_PAIR); // ['2026-08-10' => 150000, ...]

$chart_data = [];
for ($i = DASHBOARD_CHART_DAYS - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $chart_data[] = [
        'ngay'      => $d,
        'doanh_thu' => (float)($raw_revenue[$d] ?? 0),
    ];
}

// Sản phẩm bán chạy
$banchay = $pdo->query("SELECT * FROM V_SANPHAM_BANCHAY LIMIT " . DASHBOARD_TOPSP_LIMIT)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-revenue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <span class="stat-label">Doanh thu hôm nay</span>
                <span class="stat-value"><?= formatMoney($stats_today['doanh_thu']) ?></span>
            </div>
        </div>
        <div class="stat-card stat-invoice">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <span class="stat-label">Hóa đơn hôm nay</span>
                <span class="stat-value"><?= (int)$stats_today['so_hd'] ?> đơn</span>
            </div>
        </div>
        <div class="stat-card stat-product">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <span class="stat-label">Sản phẩm</span>
                <span class="stat-value"><?= (int)$total_sp ?> SP</span>
            </div>
        </div>
        <div class="stat-card stat-customer">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <span class="stat-label">Khách hàng</span>
                <span class="stat-value"><?= (int)$total_kh ?> KH</span>
            </div>
        </div>
        <div class="stat-card stat-warning<?= $sap_het > 0 ? ' has-alert' : '' ?>">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Sắp hết hàng</span>
                <span class="stat-value"><?= (int)$sap_het ?> SKU</span>
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Chart -->
        <div class="dashboard-card chart-card">
            <h3><i class="fas fa-chart-line"></i> Doanh thu <?= DASHBOARD_CHART_DAYS ?> ngày gần nhất</h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>

        <!-- Recent Invoices -->
        <div class="dashboard-card">
            <h3><i class="fas fa-receipt"></i> Hóa đơn gần đây</h3>
            <table class="data-table compact">
                <thead>
                    <tr><th>Mã HD</th><th>Nhân viên</th><th>Tổng tiền</th><th>Thời gian</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_invoices)): ?>
                    <tr class="empty-row">
                        <td colspan="4">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <span>Chưa có hóa đơn nào</span>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($recent_invoices as $hd): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($hd['ma_hd']) ?></strong></td>
                        <td><?= htmlspecialchars($hd['nhan_vien']) ?></td>
                        <td class="text-right"><?= formatMoney($hd['tong_thanh_toan']) ?></td>
                        <td><?= formatDate($hd['ngay_tao']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Top Products -->
        <div class="dashboard-card">
            <h3><i class="fas fa-fire"></i> Sản phẩm bán chạy</h3>
            <table class="data-table compact">
                <thead>
                    <tr><th>SKU</th><th>Tên sản phẩm</th><th>Đã bán</th><th>Doanh thu</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($banchay)): ?>
                    <tr class="empty-row">
                        <td colspan="4">
                            <div class="empty-state">
                                <i class="fas fa-chart-bar"></i>
                                <span>Chưa có dữ liệu bán hàng</span>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($banchay as $sp): ?>
                    <tr>
                        <td><?= htmlspecialchars($sp['sku']) ?></td>
                        <td><?= htmlspecialchars($sp['ten_san_pham'] . ' - ' . $sp['ten_bien_the']) ?></td>
                        <td class="text-center"><?= (int)$sp['tong_ban'] ?></td>
                        <td class="text-right"><?= formatMoney($sp['tong_doanh_thu']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Low Stock -->
        <div class="dashboard-card">
            <h3><i class="fas fa-exclamation-triangle"></i> Sản phẩm sắp hết hàng</h3>
            <table class="data-table compact">
                <thead>
                    <tr><th>SKU</th><th>Tên</th><th>Tồn kho</th><th>Tối thiểu</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($low_stock_list)): ?>
                    <tr class="empty-row">
                        <td colspan="4">
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <span>Không có sản phẩm nào sắp hết hàng</span>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($low_stock_list as $tk): ?>
                    <tr>
                        <td><?= htmlspecialchars($tk['sku']) ?></td>
                        <td><?= htmlspecialchars($tk['ten_san_pham']) ?></td>
                        <td class="text-center text-danger"><strong><?= (int)$tk['so_luong'] ?></strong></td>
                        <td class="text-center"><?= (int)$tk['ton_toi_thieu'] ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
const labels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d['ngay'])), $chart_data)) ?>;
const data = <?= json_encode(array_map(fn($d) => (float)$d['doanh_thu'], $chart_data)) ?>;
const hasData = data.some(v => v > 0);

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Doanh thu (VNĐ)',
            data: data,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#4f46e5'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            // FIX: khi chưa có doanh thu ngày nào, vẫn vẽ trục 0-7 ngày thay vì canvas trắng
            title: { display: !hasData, text: 'Chưa có doanh thu trong ' + labels.length + ' ngày qua', color: '#9ca3af' }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('vi-VN') } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>