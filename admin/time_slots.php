<?php
// admin/time_slots.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$message = '';
$messageType = '';

// ดึงรายชื่อแคมเปญที่เปิดใช้งานอยู่ เพื่อให้เลือกใน Dropdown
$activeCampaigns = $pdo->query("SELECT id, title FROM campaigns WHERE status = 'active' ORDER BY title ASC")->fetchAll();

// ==========================================
// ส่วนจัดการ AJAX / POST (เพิ่ม/ลบ รอบเวลา)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_slot') {
        $campaign_id = (int)$_POST['campaign_id'];
        $date = $_POST['slot_date'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $max = (int)$_POST['max_capacity'];

        if ($campaign_id > 0 && $date && $start && $end && $max >= 0) {
            $stmt = $pdo->prepare("INSERT INTO camp_time_slots (campaign_id, slot_date, start_time, end_time, max_capacity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$campaign_id, $date, $start, $end, $max]);
            echo json_encode(['status' => 'success']);
            exit;
        }
    }

    if ($action === 'delete_slot') {
        $id = (int)$_POST['slot_id'];
        // เช็คว่ามีคนจองรอบนี้หรือยัง
        $check = $pdo->prepare("SELECT COUNT(*) FROM camp_appointments WHERE slot_id = ? AND status != 'cancelled'");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบได้ เนื่องจากมีคนจองรอบนี้แล้ว']);
        } else {
            $pdo->prepare("DELETE FROM camp_time_slots WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success']);
        }
        exit;
    }
}

// ==========================================
// ส่วนดึงข้อมูลเพื่อแสดงผล (Calendar Data)
// ==========================================
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// ดึงรอบเวลาทั้งหมดของเดือนนี้
$stmt = $pdo->prepare("
    SELECT ts.*, c.title as campaign_title 
    FROM camp_time_slots ts 
    JOIN campaigns c ON ts.campaign_id = c.id 
    WHERE MONTH(ts.slot_date) = ? AND YEAR(ts.slot_date) = ?
    ORDER BY ts.slot_date, ts.start_time
");
$stmt->execute([$month, $year]);
$slots = $stmt->fetchAll();

// จัดกลุ่มข้อมูลตามวันที่เพื่อให้แสดงในปฏิทินง่ายขึ้น
$calendarData = [];
foreach ($slots as $s) {
    $calendarData[$s['slot_date']][] = $s;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">จัดการรอบเวลาแคมเปญ</h1>
        <p class="text-sm text-gray-500">กำหนดช่วงเวลาและจำนวนที่รับได้ในแต่ละแคมเปญ</p>
    </div>
    <div class="flex gap-2">
        <select onchange="location.href='?month='+this.value.split('-')[1]+'&year='+this.value.split('-')[0]" class="px-4 py-2 border rounded-xl bg-white font-prompt text-sm outline-none">
            <?php 
            for($i = -3; $i <= 6; $i++) {
                $d = date('Y-m', strtotime("$i months"));
                $selected = ($d == "$year-".str_pad($month, 2, '0', STR_PAD_LEFT)) ? 'selected' : '';
                echo "<option value='$d' $selected>".date('M Y', strtotime("$i months"))."</option>";
            }
            ?>
        </select>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-xl overflow-hidden">
        <?php
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDay = date('N', strtotime("$year-$month-01"));
        $weekdays = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];

        foreach ($weekdays as $day) echo "<div class='bg-gray-50 p-3 text-center text-xs font-bold text-gray-500 uppercase'>$day</div>";
        
        for ($i = 1; $i < $firstDay; $i++) echo "<div class='bg-gray-50 h-32'></div>";

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $isToday = $currentDate == date('Y-m-d');
            ?>
            <div class="bg-white h-40 p-2 border-t hover:bg-gray-50 transition-colors group relative">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-bold <?= $isToday ? 'bg-[#0052CC] text-white w-7 h-7 flex items-center justify-center rounded-full' : 'text-gray-700' ?>"><?= $day ?></span>
                    <button onclick="openAddSlotModal('<?= $currentDate ?>')" class="opacity-0 group-hover:opacity-100 text-[#0052CC] hover:bg-blue-50 w-7 h-7 rounded-lg transition-all">
                        <i class="fa-solid fa-plus-circle"></i>
                    </button>
                </div>
                <div class="space-y-1 overflow-y-auto max-h-[100px] scrollbar-hide">
                    <?php if (isset($calendarData[$currentDate])): ?>
                        <?php foreach ($calendarData[$currentDate] as $s): ?>
                            <div class="text-[10px] p-1.5 bg-blue-50 border border-blue-100 rounded text-blue-700 leading-tight relative group/slot">
                                <b><?= substr($s['start_time'], 0, 5) ?></b> (<?= $s['max_capacity'] ?> ที่)
                                <div class="truncate font-semibold text-[9px]"><?= htmlspecialchars($s['campaign_title']) ?></div>
                                <button onclick="deleteSlot(<?= $s['id'] ?>)" class="absolute top-1 right-1 hidden group-hover/slot:block text-red-400 hover:text-red-600">
                                    <i class="fa-solid fa-times-circle"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<div id="slotModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">เพิ่มรอบเวลาแคมเปญ</h3>
            <button onclick="document.getElementById('slotModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="slotForm" class="p-5 space-y-4">
            <input type="hidden" name="action" value="add_slot">
            <input type="hidden" name="slot_date" id="modal_date">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">เลือกแคมเปญ <span class="text-red-500">*</span></label>
                <select name="campaign_id" required class="w-full px-4 py-2 border rounded-xl font-prompt text-sm outline-none bg-white">
                    <?php foreach ($activeCampaigns as $ac): ?>
                        <option value="<?= $ac['id'] ?>"><?= htmlspecialchars($ac['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เวลาเริ่ม</label>
                    <input type="time" name="start_time" required class="w-full px-4 py-2 border rounded-xl font-prompt text-sm outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เวลาสิ้นสุด</label>
                    <input type="time" name="end_time" required class="w-full px-4 py-2 border rounded-xl font-prompt text-sm outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">จำนวนที่รับได้ (ที่นั่ง)</label>
                <input type="number" name="max_capacity" value="50" min="1" required class="w-full px-4 py-2 border rounded-xl font-prompt text-sm outline-none">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('slotModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddSlotModal(date) {
    document.getElementById('modal_date').value = date;
    document.getElementById('slotModal').classList.remove('hidden');
}

// ใช้ Fetch API บันทึกข้อมูลแบบไม่รีเฟรชหน้า
document.getElementById('slotForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('time_slots.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') location.reload();
        else alert(data.message);
    });
});

function deleteSlot(id) {
    if (!confirm('ยืนยันการลบรอบเวลานี้?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_slot');
    formData.append('slot_id', id);
    fetch('time_slots.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') location.reload();
        else alert(data.message);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>