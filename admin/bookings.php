<?php
// admin/bookings.php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

// ==========================================
// การคำนวณเดือนและปีสำหรับปฏิทิน
// ==========================================
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$thaiMonths = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
$monthName = $thaiMonths[$month];
$buddhistYear = $year + 543;

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate)); // วันสุดท้ายของเดือน

$daysInMonth = (int)date('t', strtotime($startDate));
$startDayOfWeek = (int)date('w', strtotime($startDate)); // 0 = อา, 6 = ส

// ==========================================
// ดึงข้อมูลการจองเฉพาะเดือนที่เลือก
// ==========================================
$allBookings = [];
$bookingsByDay = [];

try {
    $sql = "
        SELECT 
            a.id AS appointment_id, 
            a.status, 
            a.created_at,
            s.full_name, 
            s.student_personnel_id, 
            s.phone_number,
            t.slot_date, 
            t.start_time, 
            t.end_time
        FROM vac_appointments a
        JOIN med_students s ON a.student_id = s.id
        JOIN vac_time_slots t ON a.slot_id = t.id
        WHERE t.slot_date >= :start 
          AND t.slot_date <= :end
          AND a.status IN ('booked', 'confirmed') -- 🌟 เพิ่มบรรทัดนี้เพื่อซ่อนคิวที่ยกเลิก 🌟
        ORDER BY t.start_time ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดกลุ่มข้อมูลตามวันที่
    foreach ($allBookings as $b) {
        $day = (int)date('d', strtotime($b['slot_date']));
        $bookingsByDay[$day][] = $b;
    }
} catch (PDOException $e) {
    die("Error fetching bookings: " . $e->getMessage());
}

// ==========================================
// ส่วนจัดการ Export Excel ประจำเดือน
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "evax_bookings_{$year}_{$month}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // เติม BOM ให้รองรับภาษาไทย
    
    fputcsv($output, ['รหัสนักศึกษา/บุคลากร', 'ชื่อ-นามสกุล', 'เบอร์โทรศัพท์', 'วันที่จอง', 'เวลา', 'สถานะ']);
    
    foreach ($allBookings as $b) {
        $dateLabel = date('d/m/Y', strtotime($b['slot_date']));
        $timeLabel = substr($b['start_time'], 0, 5) . '-' . substr($b['end_time'], 0, 5);
        $statusText = '';
        switch ($b['status']) {
            case 'booked': $statusText = 'รออนุมัติ'; break;
            case 'confirmed': $statusText = 'อนุมัติแล้ว'; break;
            case 'cancelled': $statusText = 'ยกเลิกแล้ว'; break;
            default: $statusText = $b['status'];
        }
        fputcsv($output, [
            $b['student_personnel_id'], 
            $b['full_name'], 
            $b['phone_number'], 
            $dateLabel, 
            $timeLabel, 
            $statusText
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">จัดการคิวจอง (Calendar View)</h1>
        <p class="text-sm text-gray-500 mt-1">คลิกที่วันที่ในปฏิทินเพื่อดูรายชื่อและอนุมัติคิวจอง</p>
    </div>
    
    <a href="?month=<?= $month ?>&year=<?= $year ?>&export=excel" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2.5 rounded-xl font-medium transition-colors text-sm shadow-sm flex items-center justify-center gap-2 w-full md:w-auto">
        <i class="fa-solid fa-file-excel text-lg"></i>
        Export คิวเดือนนี้
    </a>
</div>

<div class="bg-white p-4 rounded-t-2xl border border-gray-100 border-b-0 flex justify-between items-center shadow-sm">
    <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-colors flex items-center gap-2">
        <i class="fa-solid fa-chevron-left text-xs"></i> เดือนก่อนหน้า
    </a>
    <h2 class="text-xl font-bold text-[#0052CC]"><?= $monthName ?> <?= $buddhistYear ?></h2>
    <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-colors flex items-center gap-2">
        เดือนถัดไป <i class="fa-solid fa-chevron-right text-xs"></i>
    </a>
</div>

<div class="bg-gray-200 border border-gray-200 rounded-b-2xl overflow-hidden shadow-sm mb-8">
    <div class="grid grid-cols-7 gap-px text-center bg-gray-100">
        <div class="py-3 text-sm font-bold text-red-500">อา.</div>
        <div class="py-3 text-sm font-bold text-gray-600">จ.</div>
        <div class="py-3 text-sm font-bold text-gray-600">อ.</div>
        <div class="py-3 text-sm font-bold text-gray-600">พ.</div>
        <div class="py-3 text-sm font-bold text-gray-600">พฤ.</div>
        <div class="py-3 text-sm font-bold text-gray-600">ศ.</div>
        <div class="py-3 text-sm font-bold text-purple-600">ส.</div>
    </div>
    
    <div class="grid grid-cols-7 gap-px bg-gray-200">
        <?php 
        $currentDay = 1;
        for ($i = 0; $i < 42; $i++) {
            if ($i < $startDayOfWeek || $currentDay > $daysInMonth) {
                echo '<div class="bg-gray-50/60 min-h-[120px] p-2"></div>';
            } else {
                $isToday = ($currentDay == (int)date('d') && $month == (int)date('m') && $year == (int)date('Y'));
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                $hasBookings = isset($bookingsByDay[$currentDay]) && count($bookingsByDay[$currentDay]) > 0;
                
                // เริ่มกล่องวันที่ (คลิกได้ถ้ามีคิวจอง)
                $cursorClass = $hasBookings ? 'cursor-pointer hover:bg-blue-50/50' : 'opacity-80';
                $onClick = $hasBookings ? "onclick=\"openDayBookings({$currentDay}, '{$currentDay} {$monthName} {$buddhistYear}')\"" : '';
                
                echo "<div class='bg-white min-h-[130px] p-2 transition-colors relative flex flex-col {$cursorClass}' {$onClick}>";
                
                echo "<div class='flex justify-between items-start mb-2'>";
                echo "<span class='inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-bold " . ($isToday ? "bg-[#0052CC] text-white" : "text-gray-700") . "'>{$currentDay}</span>";
                echo "</div>";

                // แสดงสรุปข้อมูลการจอง
                if ($hasBookings) {
                    $pending = 0; $confirmed = 0; $cancelled = 0;
                    foreach ($bookingsByDay[$currentDay] as $b) {
                        if ($b['status'] == 'booked') $pending++;
                        elseif ($b['status'] == 'confirmed') $confirmed++;
                        elseif ($b['status'] == 'cancelled') $cancelled++;
                    }
                    
                    echo "<div class='mt-auto space-y-1'>";
                    // ป้ายจำนวนทั้งหมด
                    echo "<div class='text-[11px] bg-blue-100 text-[#0052CC] px-2 py-1 rounded-md font-semibold text-center shadow-sm'>คิวทั้งหมด: " . count($bookingsByDay[$currentDay]) . "</div>";
                    // ป้ายรออนุมัติ (สีเหลือง)
                    if ($pending > 0) echo "<div class='text-[11px] bg-yellow-100 text-yellow-700 px-2 py-1 rounded-md font-semibold text-center flex justify-between'><span>รออนุมัติ</span><span>{$pending}</span></div>";
                    // ป้ายอนุมัติแล้ว (สีเขียว)
                    if ($confirmed > 0) echo "<div class='text-[11px] bg-green-100 text-green-700 px-2 py-1 rounded-md font-semibold text-center flex justify-between'><span>อนุมัติแล้ว</span><span>{$confirmed}</span></div>";
                    echo "</div>";
                } else {
                    echo "<div class='mt-auto text-center text-xs text-gray-300 font-medium pb-2'>ไม่มีคิว</div>";
                }
                
                echo "</div>"; // ปิดกล่องวันที่
                $currentDay++;
            }
            if ($currentDay > $daysInMonth && ($i + 1) % 7 == 0) break;
        }
        ?>
    </div>
</div>

<div id="dayBookingsModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50 flex-shrink-0">
            <div>
                <h3 class="text-xl font-bold text-[#0052CC] flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check"></i> คิวจองประจำวันที่ <span id="modal-date-title" class="text-gray-900 ml-1"></span>
                </h3>
            </div>
            <button onclick="document.getElementById('dayBookingsModal').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center bg-gray-200 text-gray-600 rounded-full hover:bg-gray-300 transition-colors">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-4 bg-white">
            <div class="overflow-x-auto rounded-xl border border-gray-100">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3">เวลา</th>
                            <th class="px-4 py-3">ชื่อผู้จอง (รหัส)</th>
                            <th class="px-4 py-3">เบอร์โทรศัพท์</th>
                            <th class="px-4 py-3">สถานะ</th>
                            <th class="px-4 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="dayBookingsTbody" class="divide-y divide-gray-100">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// รับ Data Array จาก PHP
const monthBookings = <?= json_encode($bookingsByDay, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function openDayBookings(day, dateTitle) {
    document.getElementById('modal-date-title').innerText = dateTitle;
    const tbody = document.getElementById('dayBookingsTbody');
    tbody.innerHTML = '';
    
    const dayData = monthBookings[day];
    
    if (!dayData || dayData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">ไม่มีข้อมูลการจองคิวในวันนี้</td></tr>';
    } else {
        dayData.forEach(b => {
            const timeStr = b.start_time.substring(0,5) + ' - ' + b.end_time.substring(0,5);
            let statusBadge = '';
            let actionBtn = '-';
            
            if (b.status === 'booked') {
                statusBadge = '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded-md text-xs font-bold shadow-sm">รออนุมัติ</span>';
                actionBtn = `<button onclick="approveBooking(${b.appointment_id})" class="bg-[#0052CC] hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition-colors shadow-sm">อนุมัติคิว</button>`;
            } else if (b.status === 'confirmed') {
                statusBadge = '<span class="bg-green-100 text-green-700 px-2 py-1 rounded-md text-xs font-bold shadow-sm">อนุมัติแล้ว</span>';
            } else if (b.status === 'cancelled') {
                statusBadge = '<span class="bg-red-100 text-red-600 px-2 py-1 rounded-md text-xs font-bold shadow-sm">ยกเลิก</span>';
            }
            
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-blue-50/30 transition-colors';
            tr.innerHTML = `
                <td class="px-4 py-3 font-bold text-[#0052CC]">${timeStr}</td>
                <td class="px-4 py-3">
                    <div class="font-bold text-gray-900">${b.full_name}</div>
                    <div class="text-xs text-gray-500">${b.student_personnel_id}</div>
                </td>
                <td class="px-4 py-3 text-gray-600">${b.phone_number}</td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3 text-center">${actionBtn}</td>
            `;
            tbody.appendChild(tr);
        });
    }
    
    document.getElementById('dayBookingsModal').classList.remove('hidden');
}

function approveBooking(appointmentId) {
    Swal.fire({
        title: 'ยืนยันการอนุมัติ?',
        text: "คุณต้องการอนุมัติคิวนี้ใช่หรือไม่?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0052CC',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ใช่อนุมัติ',
        cancelButtonText: 'ยกเลิก',
        customClass: { title: 'font-prompt', popup: 'font-prompt rounded-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax_approve_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'appointment_id=' + appointmentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'อนุมัติคิวเรียบร้อยแล้ว',
                        icon: 'success',
                        confirmButtonColor: '#0052CC',
                        customClass: { title: 'font-prompt', popup: 'font-prompt rounded-2xl' }
                    }).then(() => {
                        // โหลดหน้าเว็บใหม่เพื่ออัปเดตปฏิทินให้เป็นข้อมูลล่าสุด
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'ไม่สามารถติดต่อ Server ได้', 'error');
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>