<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
session_start();

$studentId = isset($_SESSION['evax_student_id']) ? (int)$_SESSION['evax_student_id'] : 0;
if ($studentId <= 0) {
    header('Location: index.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: booking_date.php');
    exit;
}

$slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
$vaccineId = isset($_POST['vaccine_id']) ? (int)$_POST['vaccine_id'] : 0;

if ($slotId <= 0 || $vaccineId <= 0) {
    // ส่งกลับไปหน้าเดิมถ้าข้อมูลไม่ครบ
    echo "<script>alert('กรุณาเลือกรอบเวลาและประเภทวัคซีนให้ครบถ้วน'); window.history.back();</script>";
    exit;
}

try {
    $pdo = db();
    
    // 1. เช็คว่ามีคิวอยู่แล้วหรือเปล่า (กันกดย้ำๆ)
    $checkSql = "SELECT COUNT(*) FROM vac_appointments WHERE student_id = :sid AND status IN ('confirmed', 'booked')";
    $stmtCheck = $pdo->prepare($checkSql);
    $stmtCheck->execute([':sid' => $studentId]);
    if ((int)$stmtCheck->fetchColumn() > 0) {
        header('Location: my_bookings.php?error=already_booked', true, 303);
        exit;
    }

    // 2. เช็คว่า Slot เวลานี้ยังว่างอยู่ไหม
    $sqlSlot = "
        SELECT max_capacity, 
        (SELECT COUNT(*) FROM vac_appointments WHERE slot_id = t.id AND status IN ('booked', 'confirmed')) as booked
        FROM vac_time_slots t WHERE id = :slot_id
    ";
    $stmtSlot = $pdo->prepare($sqlSlot);
    $stmtSlot->execute([':slot_id' => $slotId]);
    $slotData = $stmtSlot->fetch(PDO::FETCH_ASSOC);

    if (!$slotData || $slotData['booked'] >= $slotData['max_capacity']) {
        echo "<script>alert('ขออภัย รอบเวลาที่คุณเลือกเต็มแล้ว กรุณาเลือกรอบเวลาอื่น'); window.history.back();</script>";
        exit;
    }

    $bookingDate = $_POST['booking_date'] ?? date('Y-m-d'); // รับค่าวันที่จองมาจากฟอร์ม

    // 3. เช็คว่าวัคซีนที่เลือกยังเหลือสต๊อก และยังไม่หมดเขตใช่ไหม
    $sqlVac = "
        SELECT total_stock, 
        (SELECT COUNT(*) FROM vac_appointments WHERE vaccine_id = v.id AND status IN ('booked', 'confirmed')) as used
        FROM vac_vaccines v 
        WHERE id = :vac_id AND status = 'active' 
          AND (available_until IS NULL OR available_until >= :booking_date)
    ";
    $stmtVac = $pdo->prepare($sqlVac);
    $stmtVac->execute([
        ':vac_id' => $vaccineId, 
        ':booking_date' => $bookingDate
    ]);
    $vacData = $stmtVac->fetch(PDO::FETCH_ASSOC);

    if (!$vacData || $vacData['used'] >= $vacData['total_stock']) {
        echo "<script>alert('ขออภัย วัคซีนประเภทที่คุณเลือกสต๊อกหมดหรือหมดเขตไปแล้ว กรุณาเลือกใหม่'); window.history.back();</script>";
        exit;
    }

    // 4. บันทึกข้อมูลการจองลงฐานข้อมูล
    $insertSql = "INSERT INTO vac_appointments (student_id, slot_id, vaccine_id, status) VALUES (:sid, :slot, :vac, 'booked')";
    $stmtInsert = $pdo->prepare($insertSql);
    $stmtInsert->execute([
        ':sid' => $studentId,
        ':slot' => $slotId,
        ':vac' => $vaccineId
    ]);

    // เสร็จสิ้น เด้งไปหน้าประวัติการจอง
    header('Location: my_bookings.php?success=1', true, 303);
    exit;

} catch (PDOException $e) {
    die("Error Processing Booking: " . $e->getMessage());
}