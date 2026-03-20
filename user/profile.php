<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

session_start();

// 1. ตรวจสอบว่ามี Line ID ใน Session หรือไม่ (ถ้าไม่มีให้กลับไปหน้าแรกเพื่อ Login)
$lineUserId = $_SESSION['line_user_id'] ?? '';
if ($lineUserId === '') {
    header('Location: index.php', true, 303);
    exit;
}

// 2. ดึงข้อมูลเดิมจากฐานข้อมูล (ถ้ามี) มาแสดงในฟอร์ม
$userData = [
    'full_name' => '',
    'id_number' => '',
    'phone' => ''
];

try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT full_name, student_personnel_id, phone_number FROM med_students WHERE line_user_id = :line_id LIMIT 1");
    $stmt->execute([':line_id' => $lineUserId]);
    $user = $stmt->fetch();

    if ($user) {
        $userData['full_name'] = $user['full_name'] ?? '';
        $userData['id_number'] = $user['student_personnel_id'] ?? '';
        $userData['phone'] = $user['phone_number'] ?? '';
    }
} catch (PDOException $e) {
    // กรณี Error ให้ปล่อยผ่านไปกรอกใหม่
}

render_header('ข้อมูลส่วนตัว');
?>

<div class="p-5 flex flex-col h-full animate-in fade-in slide-in-from-right-4 duration-500">
  <form id="profileForm" class="flex-1 flex flex-col" method="post" action="save_profile.php">
    <div class="flex-1 space-y-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 font-prompt">ข้อมูลส่วนตัว</h2>
        <p class="text-sm text-gray-500 mt-1 font-prompt">กรุณากรอกข้อมูลของคุณเพื่อใช้ในการจองคิววัคซีน</p>
      </div>

      <div class="space-y-5">
        <div class="space-y-1.5">
          <label class="text-sm font-semibold text-gray-700 font-prompt" for="full_name">ชื่อ-นามสกุล</label>
          <input
            id="full_name"
            name="full_name"
            type="text"
            required
            value="<?= htmlspecialchars($userData['full_name']) ?>"
            placeholder="เช่น นายสมชาย ใจดี"
            class="w-full p-4 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] focus:border-transparent outline-none transition-all placeholder:text-gray-400 font-prompt"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-sm font-semibold text-gray-700 font-prompt" for="id_number">รหัสนักศึกษา / เลขบัตรประชาชน</label>
          <input
            id="id_number"
            name="id_number"
            type="text"
            required
            value="<?= htmlspecialchars($userData['id_number']) ?>"
            placeholder="กรอกตัวเลข 13 หลัก หรือรหัสนักศึกษา"
            class="w-full p-4 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] focus:border-transparent outline-none transition-all placeholder:text-gray-400 font-prompt"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-sm font-semibold text-gray-700 font-prompt" for="phone_number">เบอร์โทรศัพท์</label>
          <input
            id="phone_number"
            name="phone_number"
            type="tel"
            required
            value="<?= htmlspecialchars($userData['phone']) ?>"
            placeholder="08X-XXX-XXXX"
            class="w-full p-4 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] focus:border-transparent outline-none transition-all placeholder:text-gray-400 font-prompt"
          />
        </div>
      </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-gray-100 z-20 flex gap-3 shadow-[0_-4px_20px_-10px_rgba(0,0,0,0.1)]">
      <a
        href="consent.php"
        class="px-6 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors active:scale-[0.98] text-center font-prompt"
      >
        ย้อนกลับ
      </a>

      <button
        type="submit"
        class="flex-1 bg-[#0052CC] hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-all shadow-sm active:scale-[0.98] font-prompt"
      >
        บันทึกและดำเนินการต่อ
      </button>
    </div>
  </form>
</div>

<?php render_footer(); ?>