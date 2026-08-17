<?php
require_once __DIR__ . '/../includes/auth.php';
requirePermission('banhang');
$page_title = 'Bán hàng (POS)';

// Lấy danh sách sản phẩm có tồn kho
$stmt = $pdo->query("
    SELECT bt.ma_bien_the, bt.sku, bt.ten_bien_the, bt.gia_ban, tk.so_luong, sp.ten_san_pham
    FROM BIEN_THESANPHAM bt
    JOIN SANPHAM sp ON bt.ma_san_pham = sp.ma_san_pham
    JOIN TONKHO tk ON bt.ma_bien_the = tk.ma_bien_the
    WHERE bt.trang_thai = 1 AND tk.so_luong > 0
    ORDER BY sp.ten_san_pham, bt.ten_bien_the
");
$products = $stmt->fetchAll();

// Lấy khuyến mãi đang hoạt động
$stmt = $pdo->query("
    SELECT * FROM KHUYENMAI 
    WHERE trang_thai = 1 AND ngay_bat_dau <= CURDATE() AND ngay_ket_thuc >= CURDATE()
");
$promotions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pos-container">
    <!-- LEFT: Product Selection -->
    <div class="pos-left">
        <div class="pos-search">
            <input type="text" id="productSearch" placeholder="🔍 Tìm kiếm sản phẩm theo tên hoặc SKU..." onkeyup="filterProducts()">
        </div>
        <div class="pos-products" id="productList">
            <?php foreach ($products as $p): ?>
            <div class="pos-product-item" onclick="addToCart(<?= $p['ma_bien_the'] ?>, '<?= htmlspecialchars(addslashes($p['ten_san_pham'] . ' - ' . $p['ten_bien_the'])) ?>', <?= $p['gia_ban'] ?>, '<?= htmlspecialchars($p['sku']) ?>', <?= $p['so_luong'] ?>)">
                <div class="sku"><?= htmlspecialchars($p['sku']) ?></div>
                <div class="name"><?= htmlspecialchars($p['ten_san_pham']) ?><br><small><?= htmlspecialchars($p['ten_bien_the']) ?></small></div>
                <div class="price"><?= formatMoney($p['gia_ban']) ?></div>
                <div class="stock">Tồn: <?= $p['so_luong'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: Cart & Payment -->
    <div class="pos-right">
        <div class="pos-cart-header">
            <i class="fas fa-shopping-cart"></i> HÓA ĐƠN
        </div>

        <div class="pos-cart-items" id="cartItems">
            <div style="text-align:center; padding: 40px; color: var(--gray-400);">
                <i class="fas fa-shopping-basket" style="font-size: 48px; margin-bottom: 16px; display:block;"></i>
                Chưa có sản phẩm nào
            </div>
        </div>

        <div class="pos-summary">
            <div class="pos-summary-row">
                <span>Tạm tính:</span>
                <span id="subtotal">0 VNĐ</span>
            </div>
            <div class="pos-summary-row">
                <span>Giảm giá:</span>
                <span id="discount">0 VNĐ</span>
            </div>
            <div class="pos-summary-row">
                <span>Điểm sử dụng:</span>
                <span id="pointsUsed">0 điểm</span>
            </div>
            <div class="pos-summary-row total">
                <span>TỔNG TIỀN:</span>
                <span id="grandTotal">0 VNĐ</span>
            </div>
        </div>

        <div class="pos-payment">
            <div class="customer-picker">
                <div class="customer-picker-selected" id="customerSelected" style="display:none">
                    <span id="customerSelectedName"></span>
                    <button type="button" onclick="clearCustomer()"><i class="fas fa-times"></i></button>
                </div>
                <input type="text" id="customerSearchInput" placeholder="🔍 Gõ tên hoặc SĐT khách hàng... (để trống = khách lẻ)"
                    autocomplete="off" oninput="timKhachHang(this.value)">
                <div class="customer-suggestions" id="customerSuggestions" style="display:none"></div>
                <input type="hidden" id="customerIdHidden" value="">
            </div>

            <select id="promotionSelect" onchange="calculateTotal()">
                <option value="">-- Không áp dụng khuyến mãi --</option>
                <?php foreach ($promotions as $km): ?>
                <option value="<?= $km['ma_khuyen_mai'] ?>" 
                        data-type="<?= $km['loai_giam'] ?>" 
                        data-value="<?= $km['gia_tri_giam'] ?>"
                        data-min="<?= $km['dieu_kien_toi_thieu'] ?>">
                    <?= htmlspecialchars($km['ten_chuong_trinh']) ?> 
                    (<?= $km['loai_giam'] === 'PHANTRAM' ? $km['gia_tri_giam'].'%' : formatMoney($km['gia_tri_giam']) ?>)
                </option>
                <?php endforeach; ?>
            </select>

            <input type="number" id="pointsInput" placeholder="Số điểm muốn sử dụng" min="0" onchange="calculateTotal()">

            <select id="paymentMethod">
                <option value="TIENMAT">Tiền mặt</option>
                <option value="CHUYENKHOAN">Chuyển khoản</option>
                <option value="THE">Thẻ</option>
            </select>

            <button class="pos-pay-btn" onclick="processPayment()">
                <i class="fas fa-check-circle"></i> THANH TOÁN
            </button>
        </div>
    </div>
</div>

<script>
let cart = [];

function filterProducts() {
    const term = document.getElementById('productSearch').value.toLowerCase();
    document.querySelectorAll('.pos-product-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(term) ? '' : 'none';
    });
}

function addToCart(id, name, price, sku, stock) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty >= stock) {
            alert('Không đủ tồn kho!');
            return;
        }
        existing.qty++;
    } else {
        cart.push({ id, name, price, sku, qty: 1, stock });
    }
    renderCart();
    calculateTotal();
}

function updateQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    if (newQty < 1) {
        cart.splice(index, 1);
    } else if (newQty > item.stock) {
        alert('Không đủ tồn kho!');
        return;
    } else {
        item.qty = newQty;
    }
    renderCart();
    calculateTotal();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
    calculateTotal();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    if (cart.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--gray-400);"><i class="fas fa-shopping-basket" style="font-size: 48px; margin-bottom: 16px; display:block;"></i>Chưa có sản phẩm nào</div>';
        return;
    }

    let html = '';
    cart.forEach((item, i) => {
        html += `
        <div class="pos-cart-item">
            <div class="info">
                <div class="name">${item.name}</div>
                <div class="price">${item.sku} - ${formatCurrency(item.price)}</div>
            </div>
            <div class="qty-control">
                <button onclick="updateQty(${i}, -1)">-</button>
                <input type="text" value="${item.qty}" readonly>
                <button onclick="updateQty(${i}, 1)">+</button>
            </div>
            <div class="total">${formatCurrency(item.price * item.qty)}</div>
            <div class="remove" onclick="removeItem(${i})"><i class="fas fa-trash"></i></div>
        </div>`;
    });
    container.innerHTML = html;
}

function calculateTotal() {
    let subtotal = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    let discount = 0;

    const promoSelect = document.getElementById('promotionSelect');
    const selectedPromo = promoSelect.options[promoSelect.selectedIndex];
    if (selectedPromo.value) {
        const minAmount = parseFloat(selectedPromo.dataset.min) || 0;
        if (subtotal >= minAmount) {
            if (selectedPromo.dataset.type === 'PHANTRAM') {
                discount = subtotal * (parseFloat(selectedPromo.dataset.value) / 100);
            } else {
                discount = parseFloat(selectedPromo.dataset.value);
            }
        }
    }

    const pointsInput = document.getElementById('pointsInput');
    const pointsUsed = parseInt(pointsInput.value) || 0;
    const pointsValue = pointsUsed * 100;

    const grandTotal = Math.max(0, subtotal - discount - pointsValue);

    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('discount').textContent = formatCurrency(discount);
    document.getElementById('pointsUsed').textContent = pointsUsed + ' điểm (-' + formatCurrency(pointsValue) + ')';
    document.getElementById('grandTotal').textContent = formatCurrency(grandTotal);
}

let customerSearchTimeout = null;
let khachHangDangChon = null; // { id, ten, diem }

function timKhachHang(tuKhoa) {
    document.getElementById('customerIdHidden').value = '';
    khachHangDangChon = null;
    calculateTotal();

    clearTimeout(customerSearchTimeout);
    const box = document.getElementById('customerSuggestions');

    if (tuKhoa.trim().length < 2) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    // Debounce 300ms để không gọi API liên tục khi đang gõ
    customerSearchTimeout = setTimeout(() => {
        fetch('<?= url('banhang/api_khach_hang_search.php') ?>?q=' + encodeURIComponent(tuKhoa))
            .then(r => r.json())
            .then(result => {
                if (!result.success || result.customers.length === 0) {
                    box.innerHTML = '<div class="suggestion-empty">Không tìm thấy khách hàng</div>';
                    box.style.display = 'block';
                    return;
                }
                box.innerHTML = result.customers.map(c => `
                    <div class="suggestion-item" onclick="chonKhachHang(${c.ma_khach_hang}, '${escJs(c.ho_ten)}', ${c.diem_tich_luy})">
                        <strong>${escHtml(c.ho_ten)}</strong> (${escHtml(c.ma_kh)})
                        <span class="muted">${escHtml(c.so_dien_thoai || '')} — ${c.diem_tich_luy} điểm</span>
                    </div>
                `).join('');
                box.style.display = 'block';
            })
            .catch(() => {
                box.innerHTML = '<div class="suggestion-empty">Lỗi tìm kiếm</div>';
                box.style.display = 'block';
            });
    }, 300);
}

function chonKhachHang(id, ten, diem) {
    khachHangDangChon = { id, ten, diem };
    document.getElementById('customerIdHidden').value = id;
    document.getElementById('customerSearchInput').style.display = 'none';
    document.getElementById('customerSuggestions').style.display = 'none';
    document.getElementById('customerSuggestions').innerHTML = '';

    const selected = document.getElementById('customerSelected');
    selected.style.display = 'flex';
    document.getElementById('customerSelectedName').textContent = `${ten} — ${diem} điểm`;

    const pointsInput = document.getElementById('pointsInput');
    pointsInput.max = diem;
    pointsInput.placeholder = 'Tối đa ' + diem + ' điểm';

    calculateTotal();
}

function clearCustomer() {
    khachHangDangChon = null;
    document.getElementById('customerIdHidden').value = '';
    document.getElementById('customerSearchInput').value = '';
    document.getElementById('customerSearchInput').style.display = 'block';
    document.getElementById('customerSelected').style.display = 'none';
    document.getElementById('pointsInput').value = '';
    document.getElementById('pointsInput').placeholder = 'Số điểm muốn sử dụng';
    calculateTotal();
}

// Đóng dropdown gợi ý khi click ra ngoài
document.addEventListener('click', function (e) {
    const box = document.getElementById('customerSuggestions');
    const input = document.getElementById('customerSearchInput');
    if (box && input && !box.contains(e.target) && e.target !== input) {
        box.style.display = 'none';
    }
});

function escHtml(s) { return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function escJs(s) { return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'"); }

function processPayment() {
    if (cart.length === 0) {
        alert('Vui lòng chọn ít nhất 1 sản phẩm!');
        return;
    }

    const promoSelect = document.getElementById('promotionSelect');
    const paymentMethod = document.getElementById('paymentMethod').value;
    const pointsUsed = parseInt(document.getElementById('pointsInput').value) || 0;

    const data = {
        cart: cart,
        customer_id: document.getElementById('customerIdHidden').value || null,
        promotion_id: promoSelect.value || null,
        payment_method: paymentMethod,
        points_used: pointsUsed
    };

    fetch('<?= url('banhang/api_checkout.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('Thanh toán thành công! Mã hóa đơn: ' + result.ma_hd);
            cart = [];
            renderCart();
            calculateTotal();
            location.reload();
        } else {
            alert('Lỗi: ' + result.message);
        }
    })
    .catch(err => {
        alert('Lỗi kết nối: ' + err.message);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>