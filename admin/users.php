<?php
// admin/users.php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$message = '';
$messageType = '';

// ==========================================
// ส่วนจัดการ POST Request (อัปเดตข้อมูลนักศึกษา)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $studentId = trim($_POST['student_personnel_id'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');

        if ($userId > 0 && $fullName !== '' && $studentId !== '') {
            try {
                $sql = "UPDATE med_students 
                        SET full_name = :name, student_personnel_id = :studentid, phone_number = :phone 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $fullName,
                    ':studentid' => $studentId,
                    ':phone' => $phone,
                    ':id' => $userId
                ]);
                $message = "อัปเดตข้อมูลนักศึกษาเรียบร้อยแล้ว!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
            $messageType = "error";
        }
    }
}

// ==========================================
// ดึงข้อมูลและระบบค้นหา
// ==========================================
$search = $_GET['search'] ?? '';
$users = [];
$params = [];

try {
    $sql = "SELECT * FROM med_students WHERE 1=1";
    
    if ($search !== '') {
        $sql .= " AND (full_name LIKE :search OR student_personnel_id LIKE :search OR phone_number LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    
    $sql .= " ORDER BY created_at DESC"; // เรียงจากคนที่สมัครล่าสุดขึ้นก่อน
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching users: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">จัดการรายชื่อผู้ใช้งาน</h1>
        <p class="text-sm text-gray-500 mt-1">รายชื่อนักศึกษาและบุคลากรที่เข้าสู่ระบบผ่าน LINE</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl text-sm font-semibold border <?= $messageType === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-3">
        <div class="flex-1">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาด้วย ชื่อ, รหัส หรือ เบอร์โทรศัพท์..." 
                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none transition-all font-prompt text-sm">
        </div>
        <button type="submit" class="bg-[#0052CC] hover:bg-blue-700 text-white px-6 py-2 rounded-xl font-medium transition-colors text-sm whitespace-nowrap shadow-sm flex items-center justify-center gap-2">
            <i class="fa-solid fa-magnifying-glass"></i> ค้นหา
        </button>
        <?php if ($search !== ''): ?>
            <a href="users.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-xl font-medium transition-colors text-sm whitespace-nowrap text-center">
                ล้างค่า
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                    <th class="px-6 py-4">รหัสนักศึกษา/บุคลากร</th>
                    <th class="px-6 py-4">เบอร์โทรศัพท์</th>
                    <th class="px-6 py-4">วันที่ลงทะเบียน</th>
                    <th class="px-6 py-4 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (count($users) === 0): ?>
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">ไม่พบรายชื่อผู้ใช้งาน</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): 
                        $createdDate = date('d/m/Y H:i', strtotime($u['created_at']));
                        // เข้ารหัสข้อมูลสำหรับส่งไปที่ Javascript
                        $jsName = htmlspecialchars($u['full_name'], ENT_QUOTES);
                        $jsStudentId = htmlspecialchars($u['student_personnel_id'], ENT_QUOTES);
                        $jsPhone = htmlspecialchars($u['phone_number'], ENT_QUOTES);
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-gray-500">#<?= $u['id'] ?></td>
                            <td class="px-6 py-4 font-bold text-gray-900"><?= htmlspecialchars($u['full_name'] ?: 'ยังไม่กรอกโปรไฟล์') ?></td>
                            <td class="px-6 py-4 text-[#0052CC] font-medium"><?= htmlspecialchars($u['student_personnel_id'] ?: '-') ?></td>
                            <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($u['phone_number'] ?: '-') ?></td>
                            <td class="px-6 py-4 text-xs text-gray-500"><?= $createdDate ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($u['full_name']): // ถ้ากรอกโปรไฟล์แล้วถึงจะให้แก้ได้ ?>
                                <button onclick="openEditModal(<?= $u['id'] ?>, '<?= $jsName ?>', '<?= $jsStudentId ?>', '<?= $jsPhone ?>')" 
                                        class="bg-yellow-50 hover:bg-yellow-100 text-yellow-600 px-4 py-2 rounded-lg font-semibold text-xs transition-colors flex items-center justify-center gap-2 mx-auto">
                                    <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                </button>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">รอผู้ใช้กรอกข้อมูล</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="text-xl font-bold text-gray-900">แก้ไขข้อมูลผู้ใช้งาน</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" id="edit_full_name" name="full_name" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">รหัสนักศึกษา / บุคลากร <span class="text-red-500">*</span></label>
                <input type="text" id="edit_student_id" name="student_personnel_id" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์ (ไม่บังคับ)</label>
                <input type="text" id="edit_phone" name="phone_number" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
// ฟังก์ชันโยนข้อมูลลงในช่องตอนเปิด Modal
function openEditModal(id, name, studentId, phone) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_full_name').value = name;
    document.getElementById('edit_student_id').value = studentId;
    document.getElementById('edit_phone').value = phone;
    
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>