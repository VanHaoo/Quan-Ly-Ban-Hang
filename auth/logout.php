<?php
require_once __DIR__ . '/../config/config.php';
session_start();
session_destroy();
redirect('auth/login.php');
