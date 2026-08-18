-- ============================================================
-- HỆ THỐNG QUẢN LÝ BÁN HÀNG - DATABASE SCHEMA
-- Sử dụng: MySQL 8.0+ (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS quanlybanhang 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE quanlybanhang;

-- ============================================================
-- 1. NHÂN VIÊN & PHÂN QUYỀN
-- ============================================================

CREATE TABLE VAITRO (
    ma_vai_tro INT AUTO_INCREMENT PRIMARY KEY,
    ten_vai_tro VARCHAR(50) NOT NULL UNIQUE,
    mo_ta TEXT,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE QUYEN (
    ma_quyen INT AUTO_INCREMENT PRIMARY KEY,
    ten_quyen VARCHAR(100) NOT NULL UNIQUE,
    ma_quyen_code VARCHAR(50) NOT NULL UNIQUE,
    mo_ta TEXT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE VAITRO_QUYEN (
    ma_vai_tro INT NOT NULL,
    ma_quyen INT NOT NULL,
    PRIMARY KEY (ma_vai_tro, ma_quyen),
    FOREIGN KEY (ma_vai_tro) REFERENCES VAITRO(ma_vai_tro) ON DELETE CASCADE,
    FOREIGN KEY (ma_quyen) REFERENCES QUYEN(ma_quyen) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE CHUCVU (
    ma_chuc_vu INT AUTO_INCREMENT PRIMARY KEY,
    ten_chuc_vu VARCHAR(100) NOT NULL,
    mo_ta TEXT,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE NHANVIEN (
    ma_nhan_vien INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(20) NOT NULL UNIQUE,
    ho_ten VARCHAR(100) NOT NULL,
    so_dien_thoai VARCHAR(15),
    email VARCHAR(100),
    dia_chi TEXT,
    ma_chuc_vu INT,
    ngay_vao_lam DATE,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_chuc_vu) REFERENCES CHUCVU(ma_chuc_vu) ON DELETE SET NULL,
    INDEX idx_ma_nv (ma_nv),
    INDEX idx_ho_ten (ho_ten)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE TAIKHOAN (
    ma_tai_khoan INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ma_nhan_vien INT,
    ma_vai_tro INT NOT NULL,
    lan_dang_nhap_cuoi DATETIME,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_nhan_vien) REFERENCES NHANVIEN(ma_nhan_vien) ON DELETE SET NULL,
    FOREIGN KEY (ma_vai_tro) REFERENCES VAITRO(ma_vai_tro) ON DELETE RESTRICT,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. SẢN PHẨM
-- ============================================================

CREATE TABLE LOAISANPHAM (
    ma_loai INT AUTO_INCREMENT PRIMARY KEY,
    ten_loai VARCHAR(100) NOT NULL,
    mo_ta TEXT,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE THUONGHIEU (
    ma_thuong_hieu INT AUTO_INCREMENT PRIMARY KEY,
    ten_thuong_hieu VARCHAR(100) NOT NULL,
    quoc_gia VARCHAR(50),
    mo_ta TEXT,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE DONVITINH (
    ma_dvt INT AUTO_INCREMENT PRIMARY KEY,
    ten_dvt VARCHAR(50) NOT NULL UNIQUE,
    mo_ta TEXT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE SANPHAM (
    ma_san_pham INT AUTO_INCREMENT PRIMARY KEY,
    ma_sp VARCHAR(20) NOT NULL UNIQUE,
    ten_san_pham VARCHAR(200) NOT NULL,
    ma_loai INT,
    ma_thuong_hieu INT,
    ma_dvt INT DEFAULT 1,
    mo_ta TEXT,
    gia_ban DECIMAL(15,2) DEFAULT 0,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ngay_cap_nhat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_loai) REFERENCES LOAISANPHAM(ma_loai) ON DELETE SET NULL,
    FOREIGN KEY (ma_thuong_hieu) REFERENCES THUONGHIEU(ma_thuong_hieu) ON DELETE SET NULL,
    FOREIGN KEY (ma_dvt) REFERENCES DONVITINH(ma_dvt) ON DELETE SET NULL,
    INDEX idx_ma_sp (ma_sp),
    INDEX idx_ten_sp (ten_san_pham)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE BIEN_THESANPHAM (
    ma_bien_the INT AUTO_INCREMENT PRIMARY KEY,
    ma_san_pham INT NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    ten_bien_the VARCHAR(200) NOT NULL,
    gia_ban DECIMAL(15,2) DEFAULT 0,
    ton_kho INT DEFAULT 0,
    ton_toi_thieu INT DEFAULT 5,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_san_pham) REFERENCES SANPHAM(ma_san_pham) ON DELETE CASCADE,
    INDEX idx_sku (sku),
    INDEX idx_ma_sp_bt (ma_san_pham)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE LICHSUGIA (
    ma_lich_su INT AUTO_INCREMENT PRIMARY KEY,
    ma_san_pham INT,
    ma_bien_the INT,
    gia_cu DECIMAL(15,2) NOT NULL,
    gia_moi DECIMAL(15,2) NOT NULL,
    nguoi_thay_doi INT,
    ly_do TEXT,
    ngay_thay_doi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_san_pham) REFERENCES SANPHAM(ma_san_pham) ON DELETE CASCADE,
    FOREIGN KEY (ma_bien_the) REFERENCES BIEN_THESANPHAM(ma_bien_the) ON DELETE CASCADE,
    FOREIGN KEY (nguoi_thay_doi) REFERENCES TAIKHOAN(ma_tai_khoan) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. KHO
-- ============================================================

CREATE TABLE TONKHO (
    ma_ton_kho INT AUTO_INCREMENT PRIMARY KEY,
    ma_bien_the INT NOT NULL,
    so_luong INT DEFAULT 0,
    so_luong_toi_thieu INT DEFAULT 5,
    ngay_cap_nhat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_bien_the) REFERENCES BIEN_THESANPHAM(ma_bien_the) ON DELETE CASCADE,
    UNIQUE KEY uk_tonkho_bienthe (ma_bien_the)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE LICHSUTONKHO (
    ma_lich_su INT AUTO_INCREMENT PRIMARY KEY,
    ma_bien_the INT NOT NULL,
    loai_giao_dich ENUM('NHAP','BAN','TRA','DIEUCHINH','HOANTRA') NOT NULL,
    so_luong_truoc INT NOT NULL,
    so_luong_thay_doi INT NOT NULL,
    so_luong_sau INT NOT NULL,
    ma_hoa_don INT,
    ma_phieu_nhap INT,
    ma_tra_hang INT,
    ghi_chu TEXT,
    nguoi_thuc_hien INT,
    ngay_giao_dich TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_bien_the) REFERENCES BIEN_THESANPHAM(ma_bien_the) ON DELETE CASCADE,
    FOREIGN KEY (nguoi_thuc_hien) REFERENCES TAIKHOAN(ma_tai_khoan) ON DELETE SET NULL,
    INDEX idx_loai_gd (loai_giao_dich),
    INDEX idx_ngay_gd (ngay_giao_dich)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. NHẬP HÀNG
-- ============================================================

CREATE TABLE NHACUNGCAP (
    ma_nha_cung_cap INT AUTO_INCREMENT PRIMARY KEY,
    ma_ncc VARCHAR(20) NOT NULL UNIQUE,
    ten_nha_cung_cap VARCHAR(200) NOT NULL,
    so_dien_thoai VARCHAR(15),
    email VARCHAR(100),
    dia_chi TEXT,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ma_ncc (ma_ncc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE PHIEUNHAP (
    ma_phieu_nhap INT AUTO_INCREMENT PRIMARY KEY,
    ma_pn VARCHAR(20) NOT NULL UNIQUE,
    ma_nha_cung_cap INT NOT NULL,
    tong_tien DECIMAL(15,2) DEFAULT 0,
    ghi_chu TEXT,
    trang_thai ENUM('DRAFT','HOANTAT','HUY') DEFAULT 'DRAFT',
    nguoi_tao INT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_nha_cung_cap) REFERENCES NHACUNGCAP(ma_nha_cung_cap) ON DELETE RESTRICT,
    FOREIGN KEY (nguoi_tao) REFERENCES TAIKHOAN(ma_tai_khoan) ON DELETE SET NULL,
    INDEX idx_ma_pn (ma_pn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE CHITIETPHIEUNHAP (
    ma_ct_pn INT AUTO_INCREMENT PRIMARY KEY,
    ma_phieu_nhap INT NOT NULL,
    ma_bien_the INT NOT NULL,
    so_luong INT NOT NULL,
    don_gia_nhap DECIMAL(15,2) NOT NULL,
    thanh_tien DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (ma_phieu_nhap) REFERENCES PHIEUNHAP(ma_phieu_nhap) ON DELETE CASCADE,
    FOREIGN KEY (ma_bien_the) REFERENCES BIEN_THESANPHAM(ma_bien_the) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. KHÁCH HÀNG
-- ============================================================

CREATE TABLE KHACHHANG (
    ma_khach_hang INT AUTO_INCREMENT PRIMARY KEY,
    ma_kh VARCHAR(20) NOT NULL UNIQUE,
    ho_ten VARCHAR(100) NOT NULL,
    so_dien_thoai VARCHAR(15),
    email VARCHAR(100),
    dia_chi TEXT,
    tong_chi_tieu DECIMAL(15,2) DEFAULT 0,
    diem_tich_luy INT DEFAULT 0,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ma_kh (ma_kh),
    INDEX idx_sdt (so_dien_thoai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE LICHSUTICHDIEM (
    ma_lich_su INT AUTO_INCREMENT PRIMARY KEY,
    ma_khach_hang INT NOT NULL,
    loai_giao_dich ENUM('CONG','TRU') NOT NULL,
    diem_truoc INT NOT NULL,
    diem_thay_doi INT NOT NULL,
    diem_sau INT NOT NULL,
    ma_hoa_don INT,
    ma_tra_hang INT,
    ghi_chu TEXT,
    ngay_giao_dich TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_khach_hang) REFERENCES KHACHHANG(ma_khach_hang) ON DELETE CASCADE,
    INDEX idx_kh (ma_khach_hang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. KHUYẾN MÃI
-- ============================================================

CREATE TABLE KHUYENMAI (
    ma_khuyen_mai INT AUTO_INCREMENT PRIMARY KEY,
    ma_km VARCHAR(20) NOT NULL UNIQUE,
    ten_chuong_trinh VARCHAR(200) NOT NULL,
    loai_giam ENUM('PHANTRAM','SOTIEN') NOT NULL,
    gia_tri_giam DECIMAL(15,2) NOT NULL,
    dieu_kien_toi_thieu DECIMAL(15,2) DEFAULT 0,
    ngay_bat_dau DATE NOT NULL,
    ngay_ket_thuc DATE NOT NULL,
    trang_thai TINYINT DEFAULT 1,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ma_km (ma_km)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. BÁN HÀNG
-- ============================================================

CREATE TABLE HOADON (
    ma_hoa_don INT AUTO_INCREMENT PRIMARY KEY,
    ma_hd VARCHAR(20) NOT NULL UNIQUE,
    ma_khach_hang INT,
    ma_nhan_vien INT NOT NULL,
    tong_tam_tinh DECIMAL(15,2) DEFAULT 0,
    giam_gia DECIMAL(15,2) DEFAULT 0,
    diem_su_dung INT DEFAULT 0,
    diem_quy_doi DECIMAL(15,2) DEFAULT 0,
    tong_thanh_toan DECIMAL(15,2) DEFAULT 0,
    trang_thai ENUM('HOANTAT','TRAHANG','HUY') DEFAULT 'HOANTAT',
    ghi_chu TEXT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_khach_hang) REFERENCES KHACHHANG(ma_khach_hang) ON DELETE SET NULL,
    FOREIGN KEY (ma_nhan_vien) REFERENCES NHANVIEN(ma_nhan_vien) ON DELETE RESTRICT,
    INDEX idx_ma_hd (ma_hd),
    INDEX idx_ngay_tao (ngay_tao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE CHITIETHOADON (
    ma_ct_hd INT AUTO_INCREMENT PRIMARY KEY,
    ma_hoa_don INT NOT NULL,
    ma_bien_the INT NOT NULL,
    so_luong INT NOT NULL,
    don_gia DECIMAL(15,2) NOT NULL,
    thanh_tien DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (ma_hoa_don) REFERENCES HOADON(ma_hoa_don) ON DELETE CASCADE,
    FOREIGN KEY (ma_bien_the) REFERENCES BIEN_THESANPHAM(ma_bien_the) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE THANHTOAN (
    ma_thanh_toan INT AUTO_INCREMENT PRIMARY KEY,
    ma_hoa_don INT NOT NULL,
    phuong_thuc ENUM('TIENMAT','CHUYENKHOAN','THE','VI') NOT NULL,
    so_tien DECIMAL(15,2) NOT NULL,
    ghi_chu TEXT,
    ngay_thanh_toan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_hoa_don) REFERENCES HOADON(ma_hoa_don) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. TRẢ HÀNG
-- ============================================================

CREATE TABLE TRAHANG (
    ma_tra_hang INT AUTO_INCREMENT PRIMARY KEY,
    ma_th VARCHAR(20) NOT NULL UNIQUE,
    ma_hoa_don INT NOT NULL,
    ma_khach_hang INT,
    tong_tien_hoan DECIMAL(15,2) DEFAULT 0,
    ly_do TEXT,
    trang_thai ENUM('CHO_XAC_NHAN','HOANTAT','HUY') DEFAULT 'CHO_XAC_NHAN',
    nguoi_tao INT,
    nguoi_xac_nhan INT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ngay_xac_nhan TIMESTAMP NULL,
    FOREIGN KEY (ma_hoa_don) REFERENCES HOADON(ma_hoa_don) ON DELETE RESTRICT,
    FOREIGN KEY (ma_khach_hang) REFERENCES KHACHHANG(ma_khach_hang) ON DELETE SET NULL,
    FOREIGN KEY (nguoi_tao) REFERENCES TAIKHOAN(ma_tai_khoan) ON DELETE SET NULL,
    FOREIGN KEY (nguoi_xac_nhan) REFERENCES TAIKHOAN(ma_tai_khoan) ON DELETE SET NULL,
    INDEX idx_ma_th (ma_th)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE CHITIETTRAHANG (
    ma_ct_th INT AUTO_INCREMENT PRIMARY KEY,
    ma_tra_hang INT NOT NULL,
    ma_bien_the INT NOT NULL,
    so_luong INT NOT NULL,
    don_gia_hoan DECIMAL(15,2) NOT NULL,
    thanh_tien DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (ma_tra_hang) REFERENCES TRAHANG(ma_tra_hang) ON DELETE CASCADE,
    FOREIGN KEY (ma_bien_the) REFERENCES BIEN_THESANPHAM(ma_bien_the) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. AUDIT LOG
-- ============================================================

CREATE TABLE AUDIT_LOG (
    ma_log INT AUTO_INCREMENT PRIMARY KEY,
    bang VARCHAR(50) NOT NULL,
    khoa_chinh VARCHAR(50),
    hanh_dong ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    du_lieu_cu JSON,
    du_lieu_moi JSON,
    nguoi_thuc_hien INT,
    ip_address VARCHAR(45),
    ngay_giao_dich TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nguoi_thuc_hien) REFERENCES TAIKHOAN(ma_tai_khoan) ON DELETE SET NULL,
    INDEX idx_bang (bang),
    INDEX idx_ngay (ngay_giao_dich)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- VIEWS
-- ============================================================

CREATE VIEW V_TONKHO_CANHBAO AS
SELECT 
    bt.ma_bien_the,
    bt.sku,
    bt.ten_bien_the,
    sp.ten_san_pham,
    tk.so_luong,
    bt.ton_toi_thieu,
    CASE 
        WHEN tk.so_luong <= 0 THEN 'HET_HANG'
        WHEN tk.so_luong <= bt.ton_toi_thieu THEN 'SAP_HET'
        ELSE 'DU'
    END AS trang_thai_ton
FROM TONKHO tk
JOIN BIEN_THESANPHAM bt ON tk.ma_bien_the = bt.ma_bien_the
JOIN SANPHAM sp ON bt.ma_san_pham = sp.ma_san_pham
WHERE tk.so_luong <= bt.ton_toi_thieu;

CREATE VIEW V_DOANHTHU_NGAY AS
SELECT 
    DATE(ngay_tao) AS ngay,
    COUNT(*) AS so_hoa_don,
    SUM(tong_thanh_toan) AS doanh_thu
FROM HOADON
WHERE trang_thai = 'HOANTAT'
GROUP BY DATE(ngay_tao);

CREATE VIEW V_SANPHAM_BANCHAY AS
SELECT 
    bt.ma_bien_the,
    bt.sku,
    bt.ten_bien_the,
    sp.ten_san_pham,
    SUM(ct.so_luong) AS tong_ban,
    SUM(ct.thanh_tien) AS tong_doanh_thu
FROM CHITIETHOADON ct
JOIN BIEN_THESANPHAM bt ON ct.ma_bien_the = bt.ma_bien_the
JOIN SANPHAM sp ON bt.ma_san_pham = sp.ma_san_pham
JOIN HOADON hd ON ct.ma_hoa_don = hd.ma_hoa_don
WHERE hd.trang_thai = 'HOANTAT'
GROUP BY bt.ma_bien_the
ORDER BY tong_ban DESC;

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DELIMITER //

CREATE PROCEDURE SP_THANHTOAN_HOADON(
    IN p_ma_hd VARCHAR(20),
    IN p_ma_kh INT,
    IN p_ma_nv INT,
    IN p_giam_gia DECIMAL(15,2),
    IN p_diem_su_dung INT,
    IN p_phuong_thuc VARCHAR(20),
    IN p_nguoi_tao INT
)
BEGIN
    DECLARE v_tong_tam_tinh DECIMAL(15,2) DEFAULT 0;
    DECLARE v_tong_thanh_toan DECIMAL(15,2) DEFAULT 0;
    DECLARE v_diem_quy_doi DECIMAL(15,2) DEFAULT 0;
    DECLARE v_diem_hien_tai INT DEFAULT 0;
    DECLARE v_ma_hd_int INT;
    DECLARE v_ma_kh_int INT;

    START TRANSACTION;

    SELECT ma_hoa_don INTO v_ma_hd_int FROM HOADON WHERE ma_hd = p_ma_hd;
    SELECT ma_khach_hang INTO v_ma_kh_int FROM HOADON WHERE ma_hoa_don = v_ma_hd_int;

    SELECT SUM(thanh_tien) INTO v_tong_tam_tinh
    FROM CHITIETHOADON WHERE ma_hoa_don = v_ma_hd_int;

    SET v_diem_quy_doi = p_diem_su_dung * 100;
    SET v_tong_thanh_toan = v_tong_tam_tinh - p_giam_gia - v_diem_quy_doi;
    IF v_tong_thanh_toan < 0 THEN SET v_tong_thanh_toan = 0; END IF;

    UPDATE HOADON SET
        tong_tam_tinh = v_tong_tam_tinh,
        giam_gia = p_giam_gia,
        diem_su_dung = p_diem_su_dung,
        diem_quy_doi = v_diem_quy_doi,
        tong_thanh_toan = v_tong_thanh_toan,
        trang_thai = 'HOANTAT'
    WHERE ma_hoa_don = v_ma_hd_int;

    INSERT INTO THANHTOAN(ma_hoa_don, phuong_thuc, so_tien, ghi_chu)
    VALUES (v_ma_hd_int, p_phuong_thuc, v_tong_thanh_toan, 'Thanh toan hoa don');

    UPDATE TONKHO tk
    JOIN CHITIETHOADON ct ON tk.ma_bien_the = ct.ma_bien_the
    SET tk.so_luong = tk.so_luong - ct.so_luong
    WHERE ct.ma_hoa_don = v_ma_hd_int;

    INSERT INTO LICHSUTONKHO(ma_bien_the, loai_giao_dich, so_luong_truoc, so_luong_thay_doi, so_luong_sau, ma_hoa_don, ghi_chu, nguoi_thuc_hien)
    SELECT ct.ma_bien_the, 'BAN', tk.so_luong + ct.so_luong, -ct.so_luong, tk.so_luong, v_ma_hd_int, 'Ban hang', p_nguoi_tao
    FROM CHITIETHOADON ct
    JOIN TONKHO tk ON ct.ma_bien_the = tk.ma_bien_the
    WHERE ct.ma_hoa_don = v_ma_hd_int;

    IF v_ma_kh_int IS NOT NULL THEN
        SET v_diem_hien_tai = FLOOR(v_tong_thanh_toan / 10000);
        UPDATE KHACHHANG SET
            tong_chi_tieu = tong_chi_tieu + v_tong_thanh_toan,
            diem_tich_luy = diem_tich_luy + v_diem_hien_tai - p_diem_su_dung
        WHERE ma_khach_hang = v_ma_kh_int;

        INSERT INTO LICHSUTICHDIEM(ma_khach_hang, loai_giao_dich, diem_truoc, diem_thay_doi, diem_sau, ma_hoa_don, ghi_chu)
        SELECT v_ma_kh_int, 'CONG', kh.diem_tich_luy - v_diem_hien_tai + p_diem_su_dung, v_diem_hien_tai, kh.diem_tich_luy, v_ma_hd_int, 'Tich diem tu hoa don'
        FROM KHACHHANG kh WHERE kh.ma_khach_hang = v_ma_kh_int;
    END IF;

    COMMIT;
END //

CREATE PROCEDURE SP_NHAPHANG_HOANTAT(IN p_ma_pn VARCHAR(20), IN p_nguoi_tao INT)
BEGIN
    DECLARE v_ma_pn_int INT;
    START TRANSACTION;

    SELECT ma_phieu_nhap INTO v_ma_pn_int FROM PHIEUNHAP WHERE ma_pn = p_ma_pn;

    UPDATE TONKHO tk
    JOIN CHITIETPHIEUNHAP ct ON tk.ma_bien_the = ct.ma_bien_the
    SET tk.so_luong = tk.so_luong + ct.so_luong
    WHERE ct.ma_phieu_nhap = v_ma_pn_int;

    INSERT INTO LICHSUTONKHO(ma_bien_the, loai_giao_dich, so_luong_truoc, so_luong_thay_doi, so_luong_sau, ma_phieu_nhap, ghi_chu, nguoi_thuc_hien)
    SELECT ct.ma_bien_the, 'NHAP', tk.so_luong - ct.so_luong, ct.so_luong, tk.so_luong, v_ma_pn_int, 'Nhap hang', p_nguoi_tao
    FROM CHITIETPHIEUNHAP ct
    JOIN TONKHO tk ON ct.ma_bien_the = tk.ma_bien_the
    WHERE ct.ma_phieu_nhap = v_ma_pn_int;

    UPDATE PHIEUNHAP SET trang_thai = 'HOANTAT' WHERE ma_phieu_nhap = v_ma_pn_int;

    COMMIT;
END //

CREATE PROCEDURE SP_TRAHANG(IN p_ma_th VARCHAR(20), IN p_ma_hd VARCHAR(20), IN p_ly_do TEXT, IN p_nguoi_tao INT)
BEGIN
    DECLARE v_ma_hd_int INT;
    DECLARE v_ma_kh_int INT;
    DECLARE v_tong_hoan DECIMAL(15,2) DEFAULT 0;
    START TRANSACTION;

    SELECT ma_hoa_don INTO v_ma_hd_int FROM HOADON WHERE ma_hd = p_ma_hd;
    SELECT ma_khach_hang INTO v_ma_kh_int FROM HOADON WHERE ma_hoa_don = v_ma_hd_int;

    SELECT SUM(thanh_tien) INTO v_tong_hoan FROM CHITIETTRAHANG 
    WHERE ma_tra_hang = (SELECT ma_tra_hang FROM TRAHANG WHERE ma_th = p_ma_th);

    UPDATE TRAHANG SET tong_tien_hoan = v_tong_hoan, trang_thai = 'HOANTAT', ngay_xac_nhan = NOW()
    WHERE ma_th = p_ma_th;

    UPDATE HOADON SET trang_thai = 'TRAHANG' WHERE ma_hoa_don = v_ma_hd_int;

    UPDATE TONKHO tk
    JOIN CHITIETTRAHANG ct ON tk.ma_bien_the = ct.ma_bien_the
    SET tk.so_luong = tk.so_luong + ct.so_luong
    WHERE ct.ma_tra_hang = (SELECT ma_tra_hang FROM TRAHANG WHERE ma_th = p_ma_th);

    INSERT INTO LICHSUTONKHO(ma_bien_the, loai_giao_dich, so_luong_truoc, so_luong_thay_doi, so_luong_sau, ma_tra_hang, ghi_chu, nguoi_thuc_hien)
    SELECT ct.ma_bien_the, 'HOANTRA', tk.so_luong - ct.so_luong, ct.so_luong, tk.so_luong, 
    (SELECT ma_tra_hang FROM TRAHANG WHERE ma_th = p_ma_th), 'Hoan tra hang', p_nguoi_tao
    FROM CHITIETTRAHANG ct
    JOIN TONKHO tk ON ct.ma_bien_the = tk.ma_bien_the
    WHERE ct.ma_tra_hang = (SELECT ma_tra_hang FROM TRAHANG WHERE ma_th = p_ma_th);

    IF v_ma_kh_int IS NOT NULL THEN
        UPDATE KHACHHANG SET
            tong_chi_tieu = tong_chi_tieu - v_tong_hoan,
            diem_tich_luy = GREATEST(0, diem_tich_luy - FLOOR(v_tong_hoan / 10000))
        WHERE ma_khach_hang = v_ma_kh_int;
    END IF;

    COMMIT;
END //

DELIMITER ;

-- ============================================================
-- TRIGGERS
-- ============================================================

DELIMITER //

CREATE TRIGGER TRG_TONKHO_INSERT AFTER INSERT ON BIEN_THESANPHAM
FOR EACH ROW
BEGIN
    INSERT INTO TONKHO(ma_bien_the, so_luong, so_luong_toi_thieu)
    VALUES (NEW.ma_bien_the, NEW.ton_kho, NEW.ton_toi_thieu);
END //

CREATE TRIGGER TRG_LICHSUGIA_INSERT AFTER UPDATE ON SANPHAM
FOR EACH ROW
BEGIN
    IF OLD.gia_ban != NEW.gia_ban THEN
        INSERT INTO LICHSUGIA(ma_san_pham, gia_cu, gia_moi, ly_do)
        VALUES (NEW.ma_san_pham, OLD.gia_ban, NEW.gia_ban, 'Cap nhat gia san pham');
    END IF;
END //

CREATE TRIGGER TRG_AUDIT_SANPHAM_INSERT AFTER INSERT ON SANPHAM
FOR EACH ROW
BEGIN
    INSERT INTO AUDIT_LOG(bang, khoa_chinh, hanh_dong, du_lieu_moi)
    VALUES ('SANPHAM', NEW.ma_san_pham, 'INSERT', JSON_OBJECT('ma_sp', NEW.ma_sp, 'ten_san_pham', NEW.ten_san_pham, 'gia_ban', NEW.gia_ban));
END //

CREATE TRIGGER TRG_AUDIT_SANPHAM_UPDATE AFTER UPDATE ON SANPHAM
FOR EACH ROW
BEGIN
    INSERT INTO AUDIT_LOG(bang, khoa_chinh, hanh_dong, du_lieu_cu, du_lieu_moi)
    VALUES ('SANPHAM', NEW.ma_san_pham, 'UPDATE', 
    JSON_OBJECT('ten_san_pham', OLD.ten_san_pham, 'gia_ban', OLD.gia_ban),
    JSON_OBJECT('ten_san_pham', NEW.ten_san_pham, 'gia_ban', NEW.gia_ban));
END //

DELIMITER ;

-- ============================================================
-- DỮ LIỆU MẪU
-- ============================================================

INSERT INTO VAITRO (ten_vai_tro, mo_ta) VALUES 
('ADMIN', 'Quan tri he thong, toan quyen'),
('QUANLY', 'Quan ly cua hang'),
('BANHANG', 'Nhan vien ban hang'),
('KHO', 'Nhan vien kho');

INSERT INTO QUYEN (ten_quyen, ma_quyen_code, mo_ta) VALUES
('Xem Dashboard', 'dashboard_view', 'Xem trang tong quan'),
('Ban hang', 'banhang', 'Tao hoa don ban hang'),
('Quan ly san pham', 'sanpham', 'Them/sua/xoa san pham'),
('Quan ly kho', 'kho', 'Quan ly ton kho'),
('Quan ly nhap hang', 'nhaphang', 'Tao phieu nhap'),
('Quan ly khach hang', 'khachhang', 'Quan ly thong tin khach hang'),
('Quan ly khuyen mai', 'khuyenmai', 'Quan ly chuong trinh khuyen mai'),
('Quan ly nhan vien', 'nhanvien', 'Quan ly nhan vien va tai khoan'),
('Xem bao cao', 'baocao', 'Xem cac bao cao'),
('Tra hang', 'trahang', 'Xu ly tra hang/doi hang'),
('Quan ly vai tro', 'vaitro', 'Phan quyen he thong');

INSERT INTO VAITRO_QUYEN VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,9),(2,10),
(3,1),(3,2),(3,6),(3,9),
(4,1),(4,4),(4,5),(4,9);

INSERT INTO CHUCVU (ten_chuc_vu) VALUES 
('Giam doc'), ('Quan ly'), ('Nhan vien ban hang'), ('Nhan vien kho');

INSERT INTO NHANVIEN (ma_nv, ho_ten, so_dien_thoai, email, ma_chuc_vu, ngay_vao_lam) VALUES
('NV001', 'Nguyen Van Admin', '0900000001', 'admin@shop.com', 1, '2024-01-01'),
('NV002', 'Tran Thi Quan Ly', '0900000002', 'quanly@shop.com', 2, '2024-01-15'),
('NV003', 'Le Van Ban Hang', '0900000003', 'banhang@shop.com', 3, '2024-02-01'),
('NV004', 'Pham Thi Kho', '0900000004', 'kho@shop.com', 4, '2024-02-01');

-- Password: 123456 (all accounts)
INSERT INTO TAIKHOAN (username, password_hash, ma_nhan_vien, ma_vai_tro) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1),
('quanly', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 2),
('banhang', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 3),
('kho', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 4);

INSERT INTO DONVITINH (ten_dvt) VALUES ('Cai'), ('Bo'), ('Hop'), ('Kg'), ('Lit');

INSERT INTO LOAISANPHAM (ten_loai) VALUES ('Ao thun'), ('Quan jean'), ('Giay dep'), ('Phu kien'), ('Do dien tu');

INSERT INTO THUONGHIEU (ten_thuong_hieu, quoc_gia) VALUES 
('Nike', 'My'), ('Adidas', 'Duc'), ('Zara', 'Tay Ban Nha'), ('Uniqlo', 'Nhat Ban'), ('Samsung', 'Han Quoc');

INSERT INTO SANPHAM (ma_sp, ten_san_pham, ma_loai, ma_thuong_hieu, ma_dvt, gia_ban) VALUES
('SP001', 'Ao thun nam basic', 1, 4, 1, 250000),
('SP002', 'Quan jean slim fit', 2, 3, 1, 450000),
('SP003', 'Giay the thao Nike Air', 3, 1, 2, 2500000),
('SP004', 'Tai nghe Samsung Galaxy Buds', 5, 5, 3, 1500000);

INSERT INTO BIEN_THESANPHAM (ma_san_pham, sku, ten_bien_the, gia_ban, ton_kho, ton_toi_thieu) VALUES
(1, 'SP001-DO-M', 'Ao thun nam - Do - M', 250000, 50, 10),
(1, 'SP001-DO-L', 'Ao thun nam - Do - L', 250000, 30, 10),
(1, 'SP001-DEN-M', 'Ao thun nam - Den - M', 250000, 45, 10),
(1, 'SP001-DEN-L', 'Ao thun nam - Den - L', 250000, 20, 10),
(2, 'SP002-XANH-30', 'Quan jean - Xanh - 30', 450000, 25, 5),
(2, 'SP002-XANH-32', 'Quan jean - Xanh - 32', 450000, 15, 5),
(3, 'SP003-TRANG-42', 'Giay Nike Air - Trang - 42', 2500000, 10, 3),
(3, 'SP003-DEN-42', 'Giay Nike Air - Den - 42', 2500000, 8, 3),
(4, 'SP004-TRANG', 'Tai nghe Galaxy Buds - Trang', 1500000, 20, 5);

INSERT INTO NHACUNGCAP (ma_ncc, ten_nha_cung_cap, so_dien_thoai, dia_chi) VALUES
('NCC001', 'Cong ty May Viet Nam', '0281234567', 'TP.HCM'),
('NCC002', 'Samsung VN', '0287654321', 'Ha Noi'),
('NCC003', 'Nike Distribution VN', '0289998888', 'TP.HCM');

INSERT INTO KHACHHANG (ma_kh, ho_ten, so_dien_thoai, email, diem_tich_luy) VALUES
('KH001', 'Nguyen Van A', '0912345678', 'a@gmail.com', 100),
('KH002', 'Tran Thi B', '0923456789', 'b@gmail.com', 50),
('KH003', 'Le Van C', '0934567890', 'c@gmail.com', 0);

INSERT INTO KHUYENMAI (ma_km, ten_chuong_trinh, loai_giam, gia_tri_giam, dieu_kien_toi_thieu, ngay_bat_dau, ngay_ket_thuc) VALUES
('KM001', 'Giam 10% don tu 500K', 'PHANTRAM', 10, 500000, '2025-01-01', '2025-12-31'),
('KM002', 'Giam 50K don tu 300K', 'SOTIEN', 50000, 300000, '2025-01-01', '2025-12-31');
