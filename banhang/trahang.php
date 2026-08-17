<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('trahang');   // permission riêng đã có sẵn trong QUYEN, chỉ ADMIN/QUANLY được cấp
$page_title = 'Trả hàng / Đổi hàng';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="filter-bar">
    <div class="filter-form">
        <div class="filter-field grow">
            <label>Nhập mã hóa đơn cần trả (VD: HD003)</label>
            <input type="text" id="maHdInput" placeholder="HD003">
        </div>
        <div class="filter-field">
            <button type="button" class="btn-primary" onclick="timHoaDon()"><i class="fas fa-search"></i> Tìm hóa đơn</button>
        </div>
    </div>
</div>

<div id="ketQuaTim"></div>

<script>
function timHoaDon() {
    const ma = document.getElementById('maHdInput').value.trim();
    if (!ma) { alert('Nhập mã hóa đơn'); return; }
    document.getElementById('ketQuaTim').innerHTML = '<div class="loading">Đang tìm...</div>';

    fetch('<?= url('banhang/api_trahang.php') ?>?action=search&ma_hd=' + encodeURIComponent(ma))
        .then(r => r.json())
        .then(result => {
            if (!result.success) {
                document.getElementById('ketQuaTim').innerHTML = '<p class="text-danger">' + result.message + '</p>';
                return;
            }
            renderFormTraHang(result);
        })
        .catch(() => { document.getElementById('ketQuaTim').innerHTML = '<p class="text-danger">Lỗi kết nối</p>'; });
}

function renderFormTraHang(data) {
    const hd = data.hoadon;
    let rows = '';
    let coDongNaoConTra = false;
    data.chi_tiet.forEach(ct => {
        const conLai = ct.so_luong - ct.da_tra;
        if (conLai > 0) coDongNaoConTra = true;
        rows += `
        <tr>
            <td>${ct.sku}</td>
            <td>${ct.ten_san_pham} - ${ct.ten_bien_the}</td>
            <td class="text-center">${ct.so_luong}</td>
            <td class="text-center">${ct.da_tra}</td>
            <td class="text-center">${conLai}</td>
            <td class="text-right">${Number(ct.don_gia).toLocaleString('vi-VN')}</td>
            <td class="text-center">
                <input type="number" class="input-sl-tra" data-id="${ct.ma_bien_the}" data-gia="${ct.don_gia}"
                       min="0" max="${conLai}" value="0" style="width:70px"
                       onchange="tinhTienHoan()" ${conLai <= 0 ? 'disabled' : ''}>
            </td>
        </tr>`;
    });

    if (!coDongNaoConTra) {
        document.getElementById('ketQuaTim').innerHTML = `
        <div class="dashboard-card">
            <div class="empty-state"><i class="fas fa-check-circle"></i><span>Hóa đơn ${hd.ma_hd} đã được trả hết toàn bộ, không còn gì để trả thêm.</span></div>
        </div>`;
        return;
    }

    document.getElementById('ketQuaTim').innerHTML = `
    <div class="dashboard-card">
        <p><strong>Mã HD:</strong> ${hd.ma_hd} &nbsp; <strong>Khách:</strong> ${hd.ten_khach || 'Khách lẻ'}
           &nbsp; <strong>Ngày mua:</strong> ${hd.ngay_tao} &nbsp;
           <span class="badge ${hd.trang_thai === 'TRAHANG' ? 'badge-warning' : 'badge-success'}">${hd.trang_thai === 'TRAHANG' ? 'Đã trả 1 phần' : 'Hoàn tất'}</span></p>
        <table class="data-table compact">
            <thead><tr><th>SKU</th><th>Sản phẩm</th><th>Đã mua</th><th>Đã trả trước</th><th>Còn lại</th><th>Đơn giá</th><th>SL trả lần này</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
        <div class="filter-field grow" style="margin-top:12px">
            <label>Lý do trả hàng</label>
            <input type="text" id="lyDoTra" placeholder="VD: Sản phẩm lỗi, khách đổi ý...">
        </div>
        <div class="modal-summary">
            <div class="total"><strong>Tiền hoàn dự kiến: <span id="tienHoan">0</span> VNĐ</strong></div>
        </div>
        <button class="pos-pay-btn" onclick="xacNhanTraHang('${hd.ma_hd}')">
            <i class="fas fa-undo"></i> XÁC NHẬN TRẢ HÀNG
        </button>
    </div>`;
}

function tinhTienHoan() {
    let tong = 0;
    document.querySelectorAll('.input-sl-tra').forEach(inp => {
        tong += (parseInt(inp.value) || 0) * parseFloat(inp.dataset.gia);
    });
    document.getElementById('tienHoan').textContent = tong.toLocaleString('vi-VN');
}

function xacNhanTraHang(maHd) {
    const items = [];
    document.querySelectorAll('.input-sl-tra').forEach(inp => {
        const sl = parseInt(inp.value) || 0;
        if (sl > 0) items.push({ id: parseInt(inp.dataset.id), qty: sl });
    });
    if (items.length === 0) { alert('Vui lòng nhập số lượng cần trả cho ít nhất 1 sản phẩm'); return; }
    const lyDo = document.getElementById('lyDoTra').value.trim();
    if (!lyDo) { alert('Vui lòng nhập lý do trả hàng'); return; }
    if (!confirm('Xác nhận trả hàng? Thao tác này sẽ hoàn tồn kho và điều chỉnh điểm/doanh thu khách hàng.')) return;

    fetch('<?= url('banhang/api_trahang.php') ?>?action=confirm', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ma_hd: maHd, items: items, ly_do: lyDo })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('Trả hàng thành công! Mã phiếu trả: ' + result.ma_th);
            document.getElementById('ketQuaTim').innerHTML = '';
            document.getElementById('maHdInput').value = '';
        } else {
            alert('Lỗi: ' + result.message);
        }
    })
    .catch(err => alert('Lỗi kết nối: ' + err.message));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>