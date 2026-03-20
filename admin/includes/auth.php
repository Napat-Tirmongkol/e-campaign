<?php
// admin/includes/auth.php
session_start();

// ถ้ายังไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// ดึง config.php จากโฟลเดอร์หลัก (ถอยกลับไป 1 ขั้น) เพื่อให้ใช้ฟังก์ชัน db() ได้
require_once __DIR__ . '/../../config.php';