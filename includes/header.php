<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($page_title)) $page_title = 'Quan Ly Ban Hang';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Quản Lý Bán Hàng</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="page-title"><?= htmlspecialchars($page_title) ?></h2>
                </div>
                <div class="header-right">
                    <!-- Thông tin user đầy đủ đã có ở sidebar-footer, header chỉ cần icon đăng xuất -->
                    <a href="<?= url('auth/logout.php') ?>" class="btn-logout" title="Đăng xuất">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </header>
            <main class="content-area">