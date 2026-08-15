<?php
/**
 * Cấu hình chung cho hệ thống
 * Nếu đặt project ở thư mục khác, sửa BASE_URL cho phù hợp
 */

// Tự động detect thư mục gốc của project
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$parts = explode('/', trim($script_name, '/'));

// Nếu project nằm trong thư mục con (vd: /Quan-Ly-Ban-Hang/)
// thì lấy phần tử đầu tiên làm base
if (count($parts) > 0 && !empty($parts[0])) {
    $detected_base = '/' . $parts[0] . '/';
} else {
    $detected_base = '/';
}

// Có thể ghi đè bằng cách định nghĩa trước khi include file này
if (!defined('BASE_URL')) {
    define('BASE_URL', $detected_base);
}

/**
 * Hàm tạo URL đầy đủ từ đường dẫn tương đối
 */
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Hàm redirect
 */
function redirect($path = '') {
    header('Location: ' . url($path));
    exit;
}
