<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($page_title)) $page_title = 'Quan Ly Ban Hang';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Quan Ly Ban Hang</title>
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
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($ho_ten ?? $_SESSION['ho_ten'] ?? 'User') ?></span>
                        <span class="user-role"><?= htmlspecialchars($vai_tro ?? $_SESSION['vai_tro'] ?? '') ?></span>
                    </div>
                    <a href="<?= url('auth/logout.php') ?>" class="btn-logout" title="Dang xuat">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </header>
            <main class="content-area">
