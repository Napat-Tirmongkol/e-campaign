<?php
// admin/ajax_dashboard.php
session_start();
header('Content-Type: application/json');

// เช็คสิทธิ์
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php';
$pdo = db();

// 1. สถิติ
$stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
try {
    $sqlStats = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM vac_appointments
    ";
    $row = $pdo->query($sqlStats)->fetch();
    if ($row) {
        $stats['total'] = (int)$row['total'];
        $stats['pending'] = (int)$row['pending'];
        $stats['confirmed'] = (int)$row['confirmed'];
        $stats['cancelled'] = (int)$row['cancelled'];
    }
} catch (PDOException $e) {}

// 2. ข้อมูลตารางวันนี้
$todayDate = date('Y-m-d');
$tableHtml = '';
$todayCount = 0;

try {
    $sqlToday = "
        SELECT a.status, s.full_name, s.student_personnel_id, s.phone_number, t.start_time, t.end_time
        FROM vac_appointments a
        JOIN med_students s ON a.student_id = s.id
        JOIN vac_time_slots t ON a.slot_id = t.id
        WHERE t.slot_date = :today AND a.status IN ('booked', 'confirmed')
        ORDER BY t.start_time ASC
    ";
    $stmtToday = $pdo->prepare($sqlToday);
    $stmtToday->execute([':today' => $todayDate]);
    $todaysBookings = $stmtToday->fetchAll();
    $todayCount = count($todaysBookings);

    if ($todayCount === 0) {
        $tableHtml = '<tr><td colspan="4" class="px-6 py-12 text-center"><p class="text-gray-500 font-medium">ไม่มีคิวนัดหมายสำหรับวันนี้</p></td></tr>';
    } else {
        foreach ($todaysBookings as $tb) {
            $timeLabel = substr($tb['start_time'], 0, 5) . ' - ' . substr($tb['end_time'], 0, 5);
            $statusHtml = $tb['status'] === 'confirmed' 
                ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">อนุมัติแล้ว</span>' 
                : '<span class="px-3 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-700">รออนุมัติ</span>';
            
            $tableHtml .= "
                <tr class='hover:bg-blue-50/30 transition-colors'>
                    <td class='px-6 py-4 font-bold text-[#0052CC]'>{$timeLabel}</td>
                    <td class='px-6 py-4'>
                        <div class='font-bold text-gray-900'>" . htmlspecialchars($tb['full_name']) . "</div>
                        <div class='text-xs text-gray-500'>รหัส: " . htmlspecialchars($tb['student_personnel_id']) . "</div>
                    </td>
                    <td class='px-6 py-4 text-gray-600'>" . htmlspecialchars($tb['phone_number']) . "</td>
                    <td class='px-6 py-4 text-center'>{$statusHtml}</td>
                </tr>
            ";
        }
    }
} catch (PDOException $e) {}

// ส่งข้อมูลกลับไปให้ Javascript
echo json_encode([
    'stats' => $stats,
    'todayCount' => $todayCount,
    'tableHtml' => $tableHtml
]);