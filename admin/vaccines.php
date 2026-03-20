<?php
// admin/vaccines.php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['vaccine_name'] ?? '');
    $stock = (int)($_POST['total_stock'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    // รับค่าวันที่หมดเขต ถ้าปล่อยว่างให้เป็น null
    $availableUntil = !empty($_POST['available_until']) ? $_POST['available_until'] : null; 

    if ($action === 'add' && $name && $stock >= 0) {
        try {
            $sql = "INSERT INTO vac_vaccines (vaccine_name, total_stock, available_until, status) VALUES (:name, :stock, :until, :status)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $name, ':stock' => $stock, ':until' => $availableUntil, ':status' => $status]);
            $message = "เพิ่มวัคซีนเรียบร้อยแล้ว!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = "error";
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['vaccine_id'] ?? 0);
        if ($id > 0 && $name && $stock >= 0) {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM vac_appointments WHERE vaccine_id = :id AND status IN ('booked', 'confirmed')");
                $check->execute([':id' => $id]);
                $used = (int)$check->fetchColumn();

                if ($stock < $used) {
                    $message = "จำนวนสต๊อกรวม ต้องไม่น้อยกว่าจำนวนที่ถูกจองไปแล้ว ({$used} โดส)";
                    $messageType = "error";
                } else {
                    $sql = "UPDATE vac_vaccines SET vaccine_name = :name, total_stock = :stock, available_until = :until, status = :status WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':name' => $name, ':stock' => $stock, ':until' => $availableUntil, ':status' => $status, ':id' => $id]);
                    $message = "อัปเดตข้อมูลวัคซีนสำเร็จ!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['vaccine_id'] ?? 0);
        if ($id > 0) {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM vac_appointments WHERE vaccine_id = :id");
                $check->execute([':id' => $id]);
                if ((int)$check->fetchColumn() > 0) {
                    $message = "ไม่สามารถลบได้ เนื่องจากมีประวัติการจองวัคซีนชนิดนี้แล้ว";
                    $messageType = "error";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM vac_vaccines WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $message = "ลบวัคซีนสำเร็จ!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

$vaccines = [];
try {
    $sql = "
        SELECT 
            v.*,
            (SELECT COUNT(*) FROM vac_appointments a WHERE a.vaccine_id = v.id AND a.status IN ('booked', 'confirmed')) AS used_stock
        FROM vac_vaccines v
        ORDER BY v.status ASC, v.created_at DESC
    ";
    $stmt = $pdo->query($sql);
    $vaccines = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "ไม่พบตารางข้อมูล กรุณารันคำสั่ง SQL ก่อนใช้งานหน้านี้";
    $messageType = "error";
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">จัดการแคมเปญฉีดวัคซีน (Campaigns)</h1>
        <p class="text-sm text-gray-500 mt-1">สร้างแคมเปญ, กำหนดโควต้า และระยะเวลาเปิดรับลงทะเบียน</p>
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
                    <th class="px-6 py-4">ชื่อวัคซีน</th>
                    <th class="px-6 py-4 text-center">เปิดจองถึงวันที่</th>
                    <th class="px-6 py-4 text-center">คงเหลือ (โดส)</th>
                    <th class="px-6 py-4 text-center">สถานะ</th>
                    <th class="px-6 py-4 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (count($vaccines) === 0): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">ยังไม่มีข้อมูลวัคซีนในระบบ</td></tr>
                <?php else: ?>
                    <?php foreach ($vaccines as $v): 
                        $remaining = $v['total_stock'] - $v['used_stock'];
                        $isLow = ($remaining <= 10 && $v['total_stock'] > 0);
                        $jsName = htmlspecialchars($v['vaccine_name'], ENT_QUOTES);
                        $dateLabel = $v['available_until'] ? date('d/m/Y', strtotime($v['available_until'])) : '<span class="text-gray-400">ไม่มีกำหนด</span>';
                        
                        // เช็คว่าหมดอายุหรือยัง (ถ้ามีวันที่กำหนดและเลยวันปัจจุบันไปแล้ว)
                        $isExpired = $v['available_until'] && (strtotime($v['available_until']) < strtotime(date('Y-m-d')));
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors <?= ($v['status'] === 'inactive' || $isExpired) ? 'opacity-60 bg-gray-50' : '' ?>">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900 text-base flex items-center gap-2">
                                    <i class="fa-solid fa-syringe text-[#0052CC]"></i> <?= htmlspecialchars($v['vaccine_name']) ?>
                                </div>
                                <div class="text-[11px] text-gray-500 mt-1">สต๊อกรวม: <?= number_format($v['total_stock']) ?> | จองแล้ว: <?= number_format($v['used_stock']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-center font-medium <?= $isExpired ? 'text-red-500' : 'text-gray-700' ?>">
                                <?= $dateLabel ?>
                                <?php if ($isExpired): ?><div class="text-[10px] text-red-500 mt-0.5">(หมดเขตแล้ว)</div><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-bold text-lg <?= $isLow ? 'text-red-500' : 'text-green-600' ?>">
                                    <?= number_format($remaining) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($v['status'] === 'active' && !$isExpired): ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">เปิดใช้งาน</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-600">หมดเขต</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-200 text-gray-600">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center flex items-center justify-center gap-2">
                                <button onclick="openEditModal(<?= $v['id'] ?>, '<?= $jsName ?>', <?= $v['total_stock'] ?>, '<?= $v['status'] ?>', '<?= $v['available_until'] ?>')" 
                                        class="w-8 h-8 bg-yellow-50 text-yellow-600 rounded-lg flex items-center justify-center hover:bg-yellow-100 transition-colors" title="แก้ไข">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if ($v['used_stock'] == 0): ?>
                                    <form method="POST" class="m-0" onsubmit="return confirm('ยืนยันการลบวัคซีน <?= $jsName ?>?');">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="vaccine_id" value="<?= $v['id'] ?>">
                                        <button type="submit" class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors" title="ลบ"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                <?php else: ?>
                                    <button class="w-8 h-8 bg-gray-100 text-gray-300 rounded-lg flex items-center justify-center cursor-not-allowed" title="ลบไม่ได้"><i class="fa-solid fa-trash-can"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="vaccineModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="text-xl font-bold text-[#0052CC]" id="modal_title"><i class="fa-solid fa-syringe mr-2"></i> เพิ่มวัคซีน</h3>
            <button onclick="document.getElementById('vaccineModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" id="modal_action" value="add">
            <input type="hidden" name="vaccine_id" id="modal_vaccine_id">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อวัคซีน <span class="text-red-500">*</span></label>
                <input type="text" id="modal_vaccine_name" name="vaccine_name" required class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">จำนวนสต๊อกรวม <span class="text-red-500">*</span></label>
                    <input type="number" id="modal_total_stock" name="total_stock" required min="0" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">สถานะ</label>
                    <select id="modal_status" name="status" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700 appearance-none bg-white">
                        <option value="active">เปิดให้จอง</option>
                        <option value="inactive">ปิดชั่วคราว</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">เปิดให้จองถึงวันที่ (ไม่บังคับ)</label>
                <input type="date" id="modal_available_until" name="available_until" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none font-prompt text-gray-700 text-sm">
                <p class="text-xs text-gray-500 mt-1">* หากไม่ระบุ จะเปิดให้จองได้จนกว่าสต๊อกจะหมด</p>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('vaccineModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" id="modal_submit_btn" class="flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modal_title').innerHTML = '<i class="fa-solid fa-syringe mr-2"></i> เพิ่มวัคซีนใหม่';
    document.getElementById('modal_action').value = 'add';
    document.getElementById('modal_vaccine_id').value = '';
    document.getElementById('modal_vaccine_name').value = '';
    document.getElementById('modal_total_stock').value = '0';
    document.getElementById('modal_status').value = 'active';
    document.getElementById('modal_available_until').value = '';
    document.getElementById('modal_submit_btn').innerHTML = 'เพิ่มวัคซีน';
    document.getElementById('modal_submit_btn').className = 'flex-1 bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm';
    document.getElementById('vaccineModal').classList.remove('hidden');
}

function openEditModal(id, name, stock, status, availableUntil) {
    document.getElementById('modal_title').innerHTML = '<i class="fa-solid fa-pen-to-square mr-2 text-yellow-600"></i> <span class="text-yellow-700">แก้ไขวัคซีน</span>';
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('modal_vaccine_id').value = id;
    document.getElementById('modal_vaccine_name').value = name;
    document.getElementById('modal_total_stock').value = stock;
    document.getElementById('modal_status').value = status;
    document.getElementById('modal_available_until').value = availableUntil || '';
    document.getElementById('modal_submit_btn').innerHTML = 'บันทึกการแก้ไข';
    document.getElementById('modal_submit_btn').className = 'flex-1 bg-yellow-500 text-white font-bold py-3 rounded-xl hover:bg-yellow-600 transition-colors shadow-sm';
    document.getElementById('vaccineModal').classList.remove('hidden');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>