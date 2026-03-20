<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();

// 1. ตรวจสอบเงื่อนไขก่อนเริ่ม
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php', true, 303);
    exit;
}

$lineUserId = $_SESSION['line_user_id'] ?? '';
if ($lineUserId === '') {
    die("Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้งผ่าน LINE");
}

// 2. รับค่าและทำความสะอาดข้อมูล (Sanitize)
$fullName    = trim((string)($_POST['full_name'] ?? ''));
$idNumber    = trim((string)($_POST['id_number'] ?? ''));
$phoneNumber = trim((string)($_POST['phone_number'] ?? ''));

if ($fullName === '' || $idNumber === '' || $phoneNumber === '') {
    header('Location: profile.php?error=empty', true, 303);
    exit;
}

try {
    $pdo = db();
    
    // 3. อัปเดตข้อมูลนักศึกษาลงใน Record ที่มี line_user_id ตรงกับ Session
    // (ซึ่ง Record นี้ถูกสร้างไว้แล้วตั้งแต่หน้า index.php)
    $sql = "UPDATE med_students 
            SET full_name = :name, 
                student_personnel_id = :sid, 
                phone_number = :phone 
            WHERE line_user_id = :line_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'    => $fullName,
        ':sid'     => $idNumber,
        ':phone'   => $phoneNumber,
        ':line_id' => $lineUserId
    ]);

    // 4. ดึง ID (PK) ของนักศึกษาเก็บใส่ Session เพื่อใช้งานในหน้าถัดไป
    $stmtGetId = $pdo->prepare("SELECT id FROM med_students WHERE line_user_id = :line_id LIMIT 1");
    $stmtGetId->execute([':line_id' => $lineUserId]);
    $user = $stmtGetId->fetch();
    
    if ($user) {
        $studentPkId = (int)$user['id'];
        $_SESSION['evax_student_id'] = $studentPkId;
        $_SESSION['evax_full_name']  = $fullName;
    } else {
        throw new Exception("ไม่พบข้อมูลผู้ใช้งานในระบบ");
    }

    // 5. เงื่อนไขพิเศษ: เช็คประวัติการจอง
    // ถ้ามีคิวที่ยังไม่ถูกยกเลิก (confirmed/booked) ให้ไปหน้า My Bookings เลย
    $checkBookingSql = "
        SELECT COUNT(*) 
        FROM vac_appointments 
        WHERE student_id = :student_id AND status IN ('confirmed', 'booked')
    ";
    $stmtCheck = $pdo->prepare($checkBookingSql);
    $stmtCheck->execute([':student_id' => $studentPkId]);
    $hasBooking = (int)$stmtCheck->fetchColumn() > 0;

    if ($hasBooking) {
        header('Location: my_bookings.php', true, 303);
    } else {
        header('Location: booking_campaign.php', true, 303);
    }
    exit;

} catch (Exception $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}