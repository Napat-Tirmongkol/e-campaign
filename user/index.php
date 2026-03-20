<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';
session_start();

// API สำหรับบันทึก LINE Profile และสร้าง Session เบื้องต้น
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'init_user') {
    header('Content-Type: application/json');
    $lineId = $_POST['line_id'] ?? '';
    $displayName = $_POST['display_name'] ?? '';

    if ($lineId) {
        $_SESSION['line_user_id'] = $lineId;
        try {
            $pdo = db();
            // 1. ค้นหา User (ดึง full_name มาด้วย)
            $stmt = $pdo->prepare("SELECT id, student_personnel_id, full_name FROM med_students WHERE line_user_id = :line_id LIMIT 1");
            $stmt->execute([':line_id' => $lineId]);
            $user = $stmt->fetch();

            if (!$user) {
                // กรณี User ใหม่
                $stmtInsert = $pdo->prepare("INSERT INTO med_students (line_user_id, full_name, status) VALUES (:line_id, :name, 'student')");
                $stmtInsert->execute([':line_id' => $lineId, ':name' => $displayName]);
                
                // 🌟 แก้ไข: บันทึก Session ID สำหรับผู้ใช้ใหม่
                $_SESSION['evax_student_id'] = (int)$pdo->lastInsertId();
                $_SESSION['evax_full_name'] = $displayName;

                echo json_encode(['status' => 'new', 'is_complete' => false, 'has_booking' => false]);
            } else {
                // 🌟 แก้ไข: บันทึก Session ID สำหรับคนที่เคยสมัครแล้ว
                $_SESSION['evax_student_id'] = (int)$user['id'];
                $_SESSION['evax_full_name'] = $user['full_name'];

                // 2. เช็คว่ากรอกโปรไฟล์หรือยัง
                $isComplete = !empty($user['student_personnel_id']);
                
                // 3. เช็คประวัติการจองที่ยังไม่ยกเลิก
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM vac_appointments WHERE student_id = :sid AND status IN ('confirmed', 'booked')");
                $stmtCheck->execute([':sid' => $user['id']]);
                $hasBooking = (int)$stmtCheck->fetchColumn() > 0;

                echo json_encode([
                    'status' => 'exists', 
                    'is_complete' => $isComplete,
                    'has_booking' => $hasBooking
                ]);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}
render_header('Initializing...');
?>

<div class="flex flex-col items-center justify-center min-h-screen bg-[#f4f7fa]">
    <div class="w-16 h-16 border-4 border-[#0052CC] border-t-transparent rounded-full animate-spin"></div>
    <p class="mt-4 text-gray-500 font-prompt">กำลังตรวจสอบสถานะ...</p>
</div>

<script>
async function initLiff() {
    try {
        await liff.init({ liffId: '2008476166-yRYxeEJF' }); 
        
        if (!liff.isLoggedIn()) {
            liff.login();
        } else {
            const profile = await liff.getProfile();
            
            const formData = new FormData();
            formData.append('action', 'init_user');
            formData.append('line_id', profile.userId);
            formData.append('display_name', profile.displayName);
            
            const res = await fetch('./index.php', { method: 'POST', body: formData });
            const data = await res.json();

            const urlParams = new URLSearchParams(window.location.search);
            const targetApp = urlParams.get('app'); 

            if (targetApp === 'eborrow') {
                window.location.replace('../e-borrow/home.php'); 
            } else {
                if (data.has_booking) {
                    window.location.replace('my_bookings.php');
                } else if (data.is_complete) {
                    window.location.replace('booking_date.php');
                } else {
                    window.location.replace('consent.php');
                }
            }
        }
    } catch (err) {
        console.error(err);
    }
}
    initLiff();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; render_footer(); ?>