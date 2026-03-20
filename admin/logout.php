<?php
// admin/logout.php
session_start();

// ลบข้อมูล Session ของ Admin ออกทั้งหมด
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);

// ทำลาย Session 
session_destroy();

// เด้งกลับไปที่หน้า Login ของ Admin
header('Location: login.php');
exit;