<?php
// admin/campaigns.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$message = '';
$messageType = '';

// ==========================================
// ส่วนจัดการ POST Request (เพิ่ม / แก้ไข / ลบ แคมเปญ)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    $capacity = (int)($_POST['total_capacity'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $availableUntil = !empty($_POST['available_until']) ? $_POST['available_until'] : null;

    // 1. สร้างแคมเปญใหม่
    if ($action === 'add' && $title && $capacity >= 0) {
        try {
            $sql = "INSERT INTO campaigns (title, type, description, total_capacity, available_until, status) 
                    VALUES (:title, :type, :description, :capacity, :until, :status)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title, ':type' => $type, ':description' => $description, 
                ':capacity' => $capacity, ':until' => $availableUntil, ':status' => $status
            ]);
            $message = "สร้างแคมเปญเรียบร้อยแล้ว!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = "error";
        }
    }

    // 2. แก้ไขแคมเปญ
    if ($action === 'edit') {
        $id = (int)($_POST['campaign_id'] ?? 0);
        if ($id > 0 && $title && $capacity >= 0) {
            try {
                // เช็คว่าลดโควต้าน้อยกว่าคนที่จองไปแล้วหรือเปล่า
                $check = $pdo->prepare("SELECT COUNT(*) FROM camp_appointments WHERE campaign_id = :id AND status IN ('booked', 'confirmed')");
                $check->execute([':id' => $id]);
                $used = (int)$check->fetchColumn();

                if ($capacity < $used) {
                    $message = "จำนวนโควต้ารวม ต้องไม่น้อยกว่าจำนวนผู้ที่ลงทะเบียนไปแล้ว ({$used} คน)";
                    $messageType = "error";
                } else {
                    $sql = "UPDATE campaigns SET title = :title, type = :type, description = :description, 
                            total_capacity = :capacity, available_until = :until, status = :status WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':title' => $title, ':type' => $type, ':description' => $description, 
                        ':capacity' => $capacity, ':until' => $availableUntil, ':status' => $status, ':id' => $id
                    ]);
                    $message = "อัปเดตข้อมูลแคมเปญสำเร็จ!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }

    // 3. ลบแคมเปญ
    if ($action === 'delete') {
        $id = (int)($_POST['campaign_id'] ?? 0);
        if ($id > 0) {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM camp_appointments WHERE campaign_id = :id");
                $check->execute([':id' => $id]);
                if ((int)$check->fetchColumn() > 0) {
                    $message = "ไม่สามารถลบได้ เนื่องจากมีประวัติการลงทะเบียนในแคมเปญนี้แล้ว (แนะนำให้ปิดสถานะแทน)";
                    $messageType = "error";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $message = "ลบแคมเปญสำเร็จ!";
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
// ดึงข้อมูลแคมเปญทั้งหมด
// ==========================================
$campaigns = [];
try {
    $sql = "
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM camp_appointments a WHERE a.campaign_id = c.id AND a.status IN ('booked', 'confirmed')) AS used_capacity
        FROM campaigns c
        ORDER BY c.status ASC, c.created_at DESC
    ";
    $stmt = $pdo->query($sql);
    $campaigns = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "ไม่พบตารางข้อมูล กรุณาตรวจสอบ Database";
    $messageType = "error";
}

// ฟังก์ชันแปลงประเภทเป็นภาษาไทยและไอคอน
function getCampaignTypeDetails($type) {
    return match($type) {
        'vaccine' => ['label' => 'ฉีดวัคซีน', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100', 'icon' => 'fa-syringe'],
        'training' => ['label' => 'อบรม/สัมมนา', 'color' => 'text-purple-600', 'bg' => 'bg-purple-100', 'icon' => 'fa-chalkboard-user'],
        'health_check' => ['label' => 'ตรวจสุขภาพ', 'color' => 'text-green-600', 'bg' => 'bg-green-100', 'icon' => 'fa-stethoscope'],
        default => ['label' => 'กิจกรรมอื่นๆ', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100', 'icon' => 'fa-star'],
    };
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">จัดการแคมเปญ (Campaigns)</h1>
        <p class="text-sm text-gray-500 mt-1">สร้างแคมเปญ, กำหนดโควต้า, และระยะเวลาเปิดรับลงทะเบียน</p>
    </div>
    <button onclick="openAddModal()" class="bg-[#0052CC] hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-medium transition-colors text-sm shadow-sm flex items-center gap-2">
        <i class="fa-solid fa-plus-circle text-lg"></i> สร้างแคมเปญใหม่
    </button>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl text-sm font-semibold border <?= $messageType === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4">ชื่อแคมเปญ / ประเภท</th>
                    <th class="px-6 py-4 text-center">เปิดรับถึงวันที่</th>
                    <th class="px-6 py-4 text-center">ที่นั่งคงเหลือ</th>
                    <th class="px-6 py-4 text-center">สถานะ</th>
                    <th class="px-6 py-4 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (count($campaigns) === 0): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">ยังไม่มีแคมเปญในระบบ</td></tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $c): 
                        $remaining = $c['total_capacity'] - $c['used_capacity'];
                        $isLow = ($remaining <= 10 && $c['total_capacity'] > 0);
                        $typeDetails = getCampaignTypeDetails($c['type']);
                        $isExpired = $c['available_until'] && (strtotime($c['available_until']) < strtotime(date('Y-m-d')));
                        
                        $jsTitle = htmlspecialchars($c['title'], ENT_QUOTES);
                        $jsDesc = htmlspecialchars($c['description'] ?? '', ENT_QUOTES);
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors <?= ($c['status'] === 'inactive' || $isExpired) ? 'opacity-60 bg-gray-50' : '' ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 <?= $typeDetails['bg'] ?> <?= $typeDetails['color'] ?> rounded-xl flex items-center justify-center text-lg shrink-0">
                                        <i class="fa-solid <?= $typeDetails['icon'] ?>"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900 text-base"><?= htmlspecialchars($c['title']) ?></div>
                                        <div class="text-[11px] font-semibold <?= $typeDetails['color'] ?> uppercase tracking-wider mt-0.5"><?= $typeDetails['label'] ?></div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            โควต้ารวม: <?= number_format($c['total_capacity']) ?> | ลงทะเบียนแล้ว: <?= number_format($c['used_capacity']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-medium <?= $isExpired ? 'text-red-500' : 'text-gray-700' ?>">
                                <?= $c['available_until'] ? date('d/m/Y', strtotime($c['available_until'])) : '<span class="text-gray-400">ไม่มีกำหนด</span>' ?>
                                <?php if ($isExpired): ?><div class="text-[10px] text-red-500 mt-0.5">(หมดเขตแล้ว)</div><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-bold text-lg <?= $isLow ? 'text-red-500' : 'text-green-600' ?>">
                                    <?= number_format($remaining) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($c['status'] === 'active' && !$isExpired): ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">เปิดรับสมัคร</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-600">หมดเขต</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-200 text-gray-600">ปิดชั่วคราว</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="openEditModal(<?= $c['id'] ?>, '<?= $jsTitle ?>', '<?= $c['type'] ?>', <?= $c['total_capacity'] ?>, '<?= $c['available_until'] ?>', '<?= $c['status'] ?>', `<?= $jsDesc ?>`)" 
                                            class="w-8 h-8 bg-yellow-50 text-yellow-600 rounded-lg flex items-center justify-center hover:bg-yellow-100 transition-colors" title="แก้ไข">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <?php if ($c['used_capacity'] == 0): ?>
                                        <form method="POST" class="m-0" onsubmit="return confirm('ยืนยันการลบแคมเปญ <?= $jsTitle ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="w-8 h-8 bg-gray-100 text-gray-300 rounded-lg flex items-center justify-center cursor-not-allowed" title="มีการลงทะเบียนแล้ว ลบไม่ได้">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="campaignModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200 my-8">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="text-xl font-bold text-[#0052CC]" id="modal_title"><i class="fa-solid fa-bullhorn mr-2"></i> สร้างแคมเปญใหม่</h3>
            <button onclick="document.getElementById('campaignModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" id="modal_action" value="add">
            <input type="hidden" name="campaign_id" id="modal_campaign_id">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อแคมเปญ/กิจกรรม <span class="text-red-500">*</span></label>
                <input type="text" id="modal_title_input" name="title" required placeholder="เช่น อบรม CPR รุ่น 1" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ประเภทแคมเปญ <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select id="modal_type" name="type" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700 appearance-none bg-white">
                            <option value="vaccine">💉 ฉีดวัคซีน</option>
                            <option value="training">👨‍🏫 อบรม/สัมมนา</option>
                            <option value="health_check">🩺 ตรวจสุขภาพ</option>
                            <option value="other">⭐ กิจกรรมอื่นๆ</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500"><i class="fa-solid fa-chevron-down text-xs"></i></div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">โควต้าผู้เข้าร่วมทั้งหมด <span class="text-red-500">*</span></label>
                    <input type="number" id="modal_total_capacity" name="total_capacity" required min="0" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เปิดรับถึงวันที่ (ไม่บังคับ)</label>
                    <input type="date" id="modal_available_until" name="available_until" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">สถานะ</label>
                    <div class="relative">
                        <select id="modal_status" name="status" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700 appearance-none bg-white">
                            <option value="active">🟢 เปิดรับสมัคร</option>
                            <option value="inactive">⚪ ปิดชั่วคราว</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500"><i class="fa-solid fa-chevron-down text-xs"></i></div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">รายละเอียด / สถานที่จัดงาน (ไม่บังคับ)</label>
                <textarea id="modal_description" name="description" rows="3" placeholder="ระบุเงื่อนไขการเข้าร่วม หรือสถานที่จัดกิจกรรม..." class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700 resize-none"></textarea>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('campaignModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" id="modal_submit_btn" class="flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modal_title').innerHTML = '<i class="fa-solid fa-bullhorn mr-2"></i> สร้างแคมเปญใหม่';
    document.getElementById('modal_action').value = 'add';
    document.getElementById('modal_campaign_id').value = '';
    document.getElementById('modal_title_input').value = '';
    document.getElementById('modal_type').value = 'vaccine';
    document.getElementById('modal_total_capacity').value = '0';
    document.getElementById('modal_available_until').value = '';
    document.getElementById('modal_status').value = 'active';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_submit_btn').innerHTML = 'สร้างแคมเปญ';
    document.getElementById('modal_submit_btn').className = 'flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm';
    document.getElementById('campaignModal').classList.remove('hidden');
}

function openEditModal(id, title, type, capacity, until, status, desc) {
    document.getElementById('modal_title').innerHTML = '<i class="fa-solid fa-pen-to-square mr-2 text-yellow-600"></i> <span class="text-yellow-700">แก้ไขแคมเปญ</span>';
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('modal_campaign_id').value = id;
    document.getElementById('modal_title_input').value = title;
    document.getElementById('modal_type').value = type;
    document.getElementById('modal_total_capacity').value = capacity;
    document.getElementById('modal_available_until').value = until || '';
    document.getElementById('modal_status').value = status;
    document.getElementById('modal_description').value = desc;
    document.getElementById('modal_submit_btn').innerHTML = 'บันทึกการแก้ไข';
    document.getElementById('modal_submit_btn').className = 'flex-1 bg-yellow-500 text-white font-bold py-3 rounded-xl hover:bg-yellow-600 transition-colors shadow-sm';
    document.getElementById('campaignModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>