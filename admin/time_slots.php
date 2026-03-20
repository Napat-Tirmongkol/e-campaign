<?php
// admin/time_slots.php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$message = '';
$messageType = '';

// ==========================================
// ส่วนจัดการ POST Request (เพิ่ม / ลบ / แก้ไข สล็อต)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. เพิ่มสล็อตเวลาใหม่
    if ($action === 'add') {
        $slotDate = $_POST['slot_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $capacity = (int)($_POST['max_capacity'] ?? 0);

        if ($slotDate && $startTime && $endTime && $capacity > 0) {
            try {
                $sql = "INSERT INTO vac_time_slots (slot_date, start_time, end_time, max_capacity) 
                        VALUES (:date, :start, :end, :cap)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':date' => $slotDate, ':start' => $startTime, ':end' => $endTime, ':cap' => $capacity]);
                $message = "เพิ่มรอบเวลาสำเร็จ!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }

    // 2. แก้ไขสล็อตเวลา
    if ($action === 'edit') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $capacity = (int)($_POST['max_capacity'] ?? 0);

        if ($slotId > 0 && $startTime && $endTime && $capacity > 0) {
            try {
                // เช็คจำนวนคนจองปัจจุบันว่าเกิน capacity ใหม่ที่ตั้งหรือไม่
                $check = $pdo->prepare("SELECT COUNT(*) FROM vac_appointments WHERE slot_id = :id AND status IN ('booked', 'confirmed')");
                $check->execute([':id' => $slotId]);
                $bookedCount = (int)$check->fetchColumn();

                if ($capacity < $bookedCount) {
                    $message = "ไม่สามารถลดจำนวนคนรับได้ต่ำกว่าผู้จองปัจจุบัน ($bookedCount คน)";
                    $messageType = "error";
                } else {
                    $sql = "UPDATE vac_time_slots SET start_time = :start, end_time = :end, max_capacity = :cap WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':start' => $startTime, ':end' => $endTime, ':cap' => $capacity, ':id' => $slotId]);
                    $message = "อัปเดตข้อมูลรอบเวลาสำเร็จ!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }

    // 3. ลบสล็อตเวลา
    if ($action === 'delete') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        if ($slotId > 0) {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM vac_appointments WHERE slot_id = :id AND status IN ('booked', 'confirmed')");
                $check->execute([':id' => $slotId]);
                if ((int)$check->fetchColumn() > 0) {
                    $message = "ไม่สามารถลบได้ เนื่องจากมีผู้จองคิวในรอบเวลานี้แล้ว";
                    $messageType = "error";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM vac_time_slots WHERE id = :id");
                    $stmt->execute([':id' => $slotId]);
                    $message = "ลบรอบเวลาสำเร็จ!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

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
$endDate = date('Y-m-t', strtotime($startDate));

$daysInMonth = (int)date('t', strtotime($startDate));
$startDayOfWeek = (int)date('w', strtotime($startDate));

// ==========================================
// ดึงข้อมูลสล็อตเวลา
// ==========================================
$slotsByDay = [];
try {
    $sql = "
        SELECT 
            t.*,
            (SELECT COUNT(*) FROM vac_appointments a WHERE a.slot_id = t.id AND a.status IN ('booked', 'confirmed')) AS booked_count
        FROM vac_time_slots t
        WHERE t.slot_date >= :start AND t.slot_date <= :end
        ORDER BY t.start_time ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    
    foreach ($stmt->fetchAll() as $row) {
        $day = (int)date('d', strtotime($row['slot_date']));
        $slotsByDay[$day][] = $row;
    }
} catch (PDOException $e) {
    die("Error fetching slots: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">จัดการสล็อตเวลา (Time Slots)</h1>
        <p class="text-sm text-gray-500 mt-1">กำหนดวันและเวลาเพื่อเปิดรับคิวในรูปแบบปฏิทิน</p>
    </div>
    <button onclick="openAddModal('')" class="bg-[#0052CC] hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-medium transition-colors text-sm shadow-sm flex items-center gap-2">
        <i class="fa-solid fa-plus-circle text-lg"></i> เพิ่มรอบเวลา
    </button>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl text-sm font-semibold border <?= $messageType === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

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
                $isPast = strtotime($dateStr) < strtotime(date('Y-m-d'));
                
                echo "<div class='bg-white min-h-[120px] p-2 hover:bg-blue-50/30 transition-colors relative group'>";
                
                echo "<div class='flex justify-between items-start mb-2'>";
                echo "<span class='inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-bold " . ($isToday ? "bg-[#0052CC] text-white" : "text-gray-700") . "'>{$currentDay}</span>";
                if (!$isPast) {
                    echo "<button onclick=\"openAddModal('{$dateStr}')\" title='เพิ่มรอบเวลา' class='hidden group-hover:flex w-6 h-6 items-center justify-center bg-blue-100 text-[#0052CC] rounded-md hover:bg-[#0052CC] hover:text-white transition-colors text-sm font-bold leading-none'><i class=\"fa-solid fa-plus\"></i></button>";
                }
                echo "</div>";

                if (isset($slotsByDay[$currentDay])) {
                    echo "<div class='space-y-2'>";
                    foreach ($slotsByDay[$currentDay] as $s) {
                        $time = substr($s['start_time'], 0, 5);
                        $cap = $s['max_capacity'];
                        $booked = $s['booked_count'];
                        $isFull = $booked >= $cap;
                        
                        $bgClass = $isPast ? 'bg-gray-100 text-gray-500 border-gray-200' : ($isFull ? 'bg-red-50 text-red-700 border-red-200' : 'bg-blue-50 text-[#0052CC] border-blue-200');
                        
                        echo "<div class='relative group/slot border rounded-lg p-1.5 text-[11px] {$bgClass} flex flex-col gap-0.5 transition-all hover:shadow-md'>";
                        echo "<div class='flex justify-between font-bold'><span>{$time}</span><span>{$booked}/{$cap}</span></div>";
                        if ($isFull && !$isPast) echo "<div class='text-[9px] text-red-500 font-medium text-right'>เต็มแล้ว</div>";
                        
                        // เมนูแก้ไข/ลบ (โชว์เมื่อเอาเมาส์ชี้)
                        if (!$isPast) {
                            $jsStart = substr($s['start_time'], 0, 5);
                            $jsEnd = substr($s['end_time'], 0, 5);
                            echo "<div class='absolute -top-2 -right-2 hidden group-hover/slot:flex gap-1 z-10'>";
                            // ปุ่มแก้ไข
                            echo "<button type='button' onclick=\"openEditModal({$s['id']}, '{$jsStart}', '{$jsEnd}', {$cap})\" class='w-5 h-5 bg-yellow-500 text-white rounded-full flex items-center justify-center hover:bg-yellow-600 shadow-sm text-[10px]'><i class='fa-solid fa-pen'></i></button>";
                            // ปุ่มลบ (ถ้าไม่มีคนจอง)
                            if ($booked == 0) {
                                echo "<form method='POST' class='m-0' onsubmit=\"return confirm('ยืนยันการลบรอบเวลา {$time} น. ใช่หรือไม่?');\">";
                                echo "<input type='hidden' name='action' value='delete'><input type='hidden' name='slot_id' value='{$s['id']}'>";
                                echo "<button type='submit' class='w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-700 shadow-sm text-[10px]'><i class='fa-solid fa-times'></i></button>";
                                echo "</form>";
                            }
                            echo "</div>";
                        }
                        echo "</div>";
                    }
                    echo "</div>";
                }
                
                echo "</div>";
                $currentDay++;
            }
            if ($currentDay > $daysInMonth && ($i + 1) % 7 == 0) break;
        }
        ?>
    </div>
</div>

<div id="addModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="text-xl font-bold text-gray-900"><i class="fa-solid fa-calendar-plus text-[#0052CC] mr-2"></i> เพิ่มรอบเวลาใหม่</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="add">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่รับวัคซีน <span class="text-red-500">*</span></label>
                <input type="date" id="input_slot_date" name="slot_date" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เวลาเริ่มต้น <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เวลาสิ้นสุด <span class="text-red-500">*</span></label>
                    <input type="time" name="end_time" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">จำนวนที่รับได้สูงสุด (คน) <span class="text-red-500">*</span></label>
                <input type="number" name="max_capacity" required min="1" value="50" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">บันทึกรอบเวลา</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-yellow-50">
            <h3 class="text-xl font-bold text-yellow-700"><i class="fa-solid fa-pen-to-square mr-2"></i> แก้ไขรอบเวลา</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="slot_id" id="edit_slot_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เวลาเริ่มต้น <span class="text-red-500">*</span></label>
                    <input type="time" id="edit_start_time" name="start_time" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-yellow-500 outline-none font-prompt text-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เวลาสิ้นสุด <span class="text-red-500">*</span></label>
                    <input type="time" id="edit_end_time" name="end_time" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-yellow-500 outline-none font-prompt text-gray-700">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">จำนวนที่รับได้สูงสุด (คน) <span class="text-red-500">*</span></label>
                <input type="number" id="edit_max_capacity" name="max_capacity" required min="1" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-yellow-500 outline-none font-prompt text-gray-700">
                <p class="text-xs text-gray-500 mt-2">* ไม่สามารถตั้งค่าน้อยกว่าจำนวนคนที่จองคิวเข้ามาแล้วได้</p>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-yellow-500 text-white font-bold py-3 rounded-xl hover:bg-yellow-600 transition-colors shadow-sm">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal(dateString) {
    const modal = document.getElementById('addModal');
    const dateInput = document.getElementById('input_slot_date');
    if (dateString) dateInput.value = dateString;
    else dateInput.value = ''; 
    modal.classList.remove('hidden');
}

function openEditModal(slotId, startTime, endTime, maxCapacity) {
    document.getElementById('edit_slot_id').value = slotId;
    document.getElementById('edit_start_time').value = startTime;
    document.getElementById('edit_end_time').value = endTime;
    document.getElementById('edit_max_capacity').value = maxCapacity;
    
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>