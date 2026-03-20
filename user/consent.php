<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/header.php';
session_start();

// ถ้าไม่มี Line ID ใน Session ให้ดีดกลับหน้า Index
if (!isset($_SESSION['line_user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agreed'])) {
    header('Location: profile.php');
    exit;
}
render_header('Consent');
?>

<div id="consent-content" class="p-5 flex flex-col h-full animate-in fade-in slide-in-from-bottom-4 duration-500" style="display: none;">
    <div class="flex-1 pb-32">
        <div class="flex items-center gap-2 mb-4 text-[#0052CC]">
            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z"></path>
                <path d="M12 8v4"></path>
                <path d="M12 16h.01"></path>
            </svg>
            <h2 class="text-xl font-bold text-gray-800 font-prompt">Terms & Conditions</h2>
        </div>

        <div class="prose prose-sm text-gray-600 mb-6 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm max-h-[50vh] overflow-y-auto font-prompt">
            <h3 class="text-lg font-bold text-gray-900 mb-3">ข้อตกลงและเงื่อนไขการใช้บริการ</h3>
            <p class="mb-3">ยินดีต้อนรับเข้าสู่ระบบจองคิวรับวัคซีน (E-Vax) กรุณาอ่านและทำความเข้าใจเงื่อนไขด้านล่างนี้ก่อนกดยอมรับ</p>
            
            <h4 class="font-bold text-gray-800 mt-4 mb-2">1. ข้อมูลส่วนบุคคลที่เราจัดเก็บ</h4>
            <ul class="list-disc pl-5 mb-3 space-y-1 text-sm">
                <li>ชื่อ-นามสกุล</li>
                <li>รหัสนักศึกษา / รหัสประจำตัวบุคลากร</li>
                <li>หมายเลขโทรศัพท์ติดต่อ</li>
                <li>ข้อมูลบัญชี LINE (LINE User ID)</li>
            </ul>

            <h4 class="font-bold text-gray-800 mt-4 mb-2">2. วัตถุประสงค์ในการเก็บและใช้ข้อมูล</h4>
            <ul class="list-disc pl-5 mb-3 space-y-1 text-sm">
                <li>เพื่อตรวจสอบสิทธิ์และยืนยันตัวตนในการเข้ารับบริการ</li>
                <li>เพื่อบริหารจัดการคิวและวันเวลานัดหมาย</li>
                <li>เพื่อส่งข้อความแจ้งเตือนผ่านแอปพลิเคชัน LINE</li>
            </ul>

            <h4 class="font-bold text-gray-800 mt-4 mb-2">3. การรักษาความปลอดภัย (PDPA)</h4>
            <p class="text-sm">ข้อมูลของท่านจะถูกเก็บรักษาอย่างปลอดภัยตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA) จะไม่มีการนำไปเผยแพร่หรือขายต่อ</p>
        </div>

        <form id="consentForm" method="post">
            <label class="flex items-start gap-3 p-4 bg-white rounded-2xl border border-gray-100 shadow-sm cursor-pointer hover:bg-gray-50 transition-colors">
                <input type="checkbox" name="agreed" value="1" required id="agreeCheckbox" class="mt-1 w-5 h-5 rounded border-gray-300 text-[#0052CC] focus:ring-[#0052CC]" />
                <span class="text-sm text-gray-700 font-medium leading-tight font-prompt">ฉันได้อ่าน ทำความเข้าใจ และยอมรับข้อตกลงและเงื่อนไข รวมถึงนโยบายความเป็นส่วนตัว</span>
            </label>
        </form>
    </div>

    <div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-gray-100 z-20 shadow-[0_-4px_20px_-10px_rgba(0,0,0,0.1)]">
        <button type="submit" form="consentForm" id="continueBtn" disabled class="w-full bg-[#0052CC] hover:bg-blue-700 disabled:bg-gray-300 disabled:text-gray-500 text-white font-bold py-4 rounded-xl transition-all shadow-sm font-prompt active:scale-[0.98]">
            ยอมรับและดำเนินการต่อ
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // แสดงเนื้อหาหน้า Consent ทันทีเมื่อโหลดหน้าสำเร็จ
        const content = document.getElementById('consent-content');
        if (content) {
            content.style.display = 'flex';
        }

        // จัดการเปิด/ปิดปุ่มยอมรับตามสถานะ Checkbox
        const agreeCheckbox = document.getElementById('agreeCheckbox');
        const continueBtn = document.getElementById('continueBtn');
        if (agreeCheckbox && continueBtn) {
            agreeCheckbox.addEventListener('change', (e) => {
                continueBtn.disabled = !e.target.checked;
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; render_footer(); ?>