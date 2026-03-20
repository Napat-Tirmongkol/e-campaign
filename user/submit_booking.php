<?php
// user/submit_booking.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
session_start();

// 1. ตรวจสอบ Login
$studentId = isset($_SESSION['evax_student_id']) ? (int)$_SESSION['evax_student_id'] : 0;
if ($studentId <= 0) {
    header('Location: index.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: booking_campaign.php');
    exit;
}

// 2. รับค่าจากฟอร์มหน้า booking_time.php
$slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
$campaignId = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
$bookingDate = $_POST['booking_date'] ?? date('Y-m-d');

if ($slotId <= 0 || $campaignId <= 0) {
    echo "<script>alert('กรุณาเลือกรอบเวลาให้ครบถ้วน'); window.history.back();</script>";
    exit;
}

try {
    $pdo = db();
    
    // 3. เช็คว่าเคยกดจองกิจกรรมนี้ไปแล้วหรือยัง (1 คน 1 คิวต่อ 1 กิจกรรม)
    $checkSql = "SELECT COUNT(*) FROM camp_appointments WHERE student_id = :sid AND campaign_id = :cid AND status IN ('confirmed', 'booked')";
    $stmtCheck = $pdo->prepare($checkSql);
    $stmtCheck->execute([':sid' => $studentId, ':cid' => $campaignId]);
    if ((int)$stmtCheck->fetchColumn() > 0) {
        header('Location: my_bookings.php?error=already_booked', true, 303);
        exit;
    }

    // 4. เช็คโควต้ารวมของแคมเปญ ว่าเต็มหรือยัง / หมดเขตหรือยัง
    $sqlCamp = "
        SELECT total_capacity, 
        (SELECT COUNT(*) FROM camp_appointments WHERE campaign_id = c.id AND status IN ('booked', 'confirmed')) as used
        FROM campaigns c 
        WHERE id = :cid AND status = 'active' 
          AND (available_until IS NULL OR available_until >= :booking_date)
    ";
    $stmtCamp = $pdo->prepare($sqlCamp);
    $stmtCamp->execute([':cid' => $campaignId, ':booking_date' => $bookingDate]);
    $campData = $stmtCamp->fetch(PDO::FETCH_ASSOC);

    if (!$campData || $campData['used'] >= $campData['total_capacity']) {
        echo "<script>alert('ขออภัย กิจกรรมนี้ที่นั่งเต็มหรือหมดเขตไปแล้ว กรุณาเลือกกิจกรรมอื่น'); window.location.href='booking_campaign.php';</script>";
        exit;
    }

    // 5. เช็คโควต้าของ \"รอบเวลา\" (Slot) ที่เลือก ว่าเต็มหรือยัง
    $sqlSlot = "
        SELECT max_capacity,
        (SELECT COUNT(*) FROM camp_appointments WHERE slot_id = t.id AND status IN ('booked', 'confirmed')) as slot_used
        FROM camp_time_slots t
        WHERE id = :slot_id
    ";
    $stmtSlot = $pdo->prepare($sqlSlot);
    $stmtSlot->execute([':slot_id' => $slotId]);
    $slotData = $stmtSlot->fetch(PDO::FETCH_ASSOC);

    if (!$slotData || $slotData['slot_used'] >= $slotData['max_capacity']) {
        echo "<script>alert('ขออภัย รอบเวลาที่คุณเลือกเต็มแล้ว กรุณาเลือกรอบเวลาอื่น'); window.history.back();</script>";
        exit;
    }

    // 6. บันทึกข้อมูลการจองลงฐานข้อมูล (ตารางใหม่ camp_appointments)
    $insertSql = "INSERT INTO camp_appointments (student_id, campaign_id, slot_id, status) VALUES (:sid, :cid, :slot, 'booked')";
    $stmtInsert = $pdo->prepare($insertSql);
    $stmtInsert->execute([
        ':sid' => $studentId, 
        ':cid' => $campaignId, 
        ':slot' => $slotId
    ]);

    // 7. นำไปสู่หน้าสำเร็จ
    header('Location: success.php');
    exit;

} catch (PDOException $e) {
    error_log("Booking Error: " . $e->getMessage());
    echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง'); window.history.back();</script>";
    exit;
}