<?php
// user/my_bookings.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

session_start();

// ตรวจสอบว่าล็อกอินหรือยัง
$studentId = isset($_SESSION['evax_student_id']) ? (int)$_SESSION['evax_student_id'] : 0;
if ($studentId <= 0) {
  header('Location: profile.php', true, 303);
  exit;
}

// ดึงข้อมูลการจอง (อัปเดตเป็นระบบแคมเปญ)
$bookings = [];
try {
  $pdo = db();
  $sql = "
    SELECT 
        a.id AS appointment_id, 
        a.status, 
        t.slot_date, 
        t.start_time, 
        t.end_time,
        c.title AS campaign_title
    FROM camp_appointments a
    JOIN camp_time_slots t ON a.slot_id = t.id
    JOIN campaigns c ON a.campaign_id = c.id
    WHERE a.student_id = :student_id
    ORDER BY t.slot_date DESC, t.start_time DESC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':student_id' => $studentId]);
  $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
  die("Error fetching bookings: " . $e->getMessage());
}

render_header('ประวัติการจอง - E-Campaign');
?>

<div class="p-5 pb-32 flex flex-col h-full bg-[#f4f7fa] animate-in fade-in duration-500">
  <div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">ประวัติการลงทะเบียน</h2>
    <p class="text-sm text-gray-500 mt-1">รายการกิจกรรมที่คุณได้ทำการจองไว้</p>
  </div>

  <div class="space-y-4">
    <?php if (count($bookings) === 0): ?>
      <div class="bg-white rounded-2xl p-8 text-center border border-gray-100 shadow-sm">
        <div class="text-gray-400 mb-2 text-4xl"><i class="fa-regular fa-calendar-xmark"></i></div>
        <p class="text-gray-500 font-medium">คุณยังไม่มีประวัติการจองกิจกรรม</p>
      </div>
    <?php else: ?>
      <?php foreach ($bookings as $b): 
        $dateLabel = date('j M Y', strtotime($b['slot_date']));
        $timeLabel = substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5);
        $isConfirmed = ($b['status'] === 'confirmed' || $b['status'] === 'booked');
        $patientName = htmlspecialchars($_SESSION['evax_full_name'] ?? 'ไม่ระบุชื่อ', ENT_QUOTES);
        $campaignTitle = htmlspecialchars($b['campaign_title'], ENT_QUOTES);
        
        $safeDate = htmlspecialchars($dateLabel, ENT_QUOTES);
        $safeTime = htmlspecialchars($timeLabel, ENT_QUOTES);
      ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm relative overflow-hidden mb-4 cursor-pointer active:scale-[0.98] transition-all" 
           onclick="openModal('<?= $patientName ?>', '<?= $safeDate ?>', '<?= $safeTime ?>', '<?= $b['appointment_id'] ?>', '<?= $b['status'] ?>', '<?= $campaignTitle ?>')">
          
          <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $isConfirmed ? 'bg-green-500' : 'bg-red-400' ?>"></div>
          
          <div class="p-5 flex justify-between items-start pl-4">
              <div class="pr-2">
                  <p class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1"><i class="fa-solid fa-layer-group"></i> <?= $campaignTitle ?></p>
                  <p class="font-bold text-gray-900 text-lg font-prompt"><?= htmlspecialchars($dateLabel) ?></p>
                  <p class="text-[#0052CC] font-semibold text-sm mt-0.5"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($timeLabel) ?></p>
              </div>
              <div class="shrink-0">
                  <?php if ($isConfirmed): ?>
                      <span class="px-3 py-1.5 bg-green-50 text-green-600 text-[10px] font-bold uppercase tracking-widest rounded-full border border-green-100">ยืนยันแล้ว</span>
                  <?php else: ?>
                      <span class="px-3 py-1.5 bg-red-50 text-red-500 text-[10px] font-bold uppercase tracking-widest rounded-full border border-red-100">ยกเลิกแล้ว</span>
                  <?php endif; ?>
              </div>
          </div>

          <?php if ($isConfirmed): ?>
              <div class="px-5 pb-4 border-t border-gray-50 pt-3" onclick="event.stopPropagation()"> 
                  <form action="cancel_booking.php" method="POST" class="cancel-form m-0">
                      <input type="hidden" name="appointment_id" value="<?= $b['appointment_id'] ?>">
                      <button type="submit" class="w-full py-2 text-sm font-bold text-red-500 bg-red-50 hover:bg-red-100 rounded-xl transition-colors active:scale-[0.98]">
                          ยกเลิกคิวนี้
                      </button>
                  </form>
              </div>
          <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-gray-100 z-20">
    <a href="booking_campaign.php" class="flex w-full items-center justify-center bg-[#0052CC] hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-all font-prompt shadow-lg shadow-blue-100">
      <i class="fa-solid fa-plus mr-2"></i> จองกิจกรรมเพิ่ม
    </a>
</div>

<div id="details-modal" class="fixed inset-0 z-[100] bg-black/50 backdrop-blur-sm flex items-end justify-center opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeModal()">
    <div class="bg-white w-full max-w-md rounded-t-3xl p-6 pb-12 shadow-2xl transform translate-y-full transition-transform duration-300 relative" onclick="event.stopPropagation()">
        
        <div class="absolute left-1/2 -top-3.5 -translate-x-1/2 w-12 h-1.5 bg-gray-300 rounded-full cursor-pointer" onclick="closeModal()"></div>
        
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900 font-prompt">รายละเอียดการจอง</h2>
            <button class="w-8 h-8 flex items-center justify-center bg-gray-100 text-gray-500 rounded-full hover:bg-gray-200 transition-colors" onclick="closeModal()"><i class="fa-solid fa-times"></i></button>
        </div>

        <div class="bg-white text-center">
            <div id="modal-status-container" class="mb-6 flex flex-col items-center">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-check text-2xl text-green-500"></i>
                </div>
                <p id="modal-status-text" class="text-gray-500 font-medium text-sm">ยืนยันการจองเรียบร้อย</p>
            </div>

            <div class="space-y-4 text-left bg-gray-50 p-5 rounded-2xl mb-6 border border-gray-100 shadow-inner">
                <div>
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">กิจกรรม (Campaign)</p>
                    <p id="modal-campaign" class="text-base font-bold text-[#0052CC] font-prompt"></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">ชื่อ-นามสกุล (Name)</p>
                    <p id="modal-patient-name" class="text-base font-bold text-gray-900 font-prompt"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">วันที่ (Date)</p>
                        <p id="modal-date" class="text-base font-bold text-gray-900 font-prompt"></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">เวลา (Time)</p>
                        <p id="modal-time" class="text-base font-bold text-gray-900 font-prompt"></p>
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t-2 border-dashed border-gray-200 pt-6">
                <p class="text-xs text-gray-500 mb-4 font-medium">แสดง QR Code นี้แก่เจ้าหน้าที่หน้างาน</p>
                <div class="bg-white p-3 inline-block rounded-2xl border-4 border-white shadow-[0_0_20px_rgba(0,0,0,0.08)] mb-4">
                    <img id="modal-qrcode" src="" alt="Booking QR Code" class="w-40 h-40 mx-auto" />
                </div>
                <p class="text-[10px] text-gray-400 font-mono">REF ID: <span id="modal-id"></span></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // สคริปต์แจ้งเตือนกรณีจองแคมเปญเดิมซ้ำ
  function showAlreadyBookedAlert() {
      Swal.fire({
          title: 'ไม่สามารถจองซ้ำได้',
          text: 'คุณมีคิวของกิจกรรมนี้อยู่แล้ว หากต้องการเปลี่ยนวัน กรุณายกเลิกคิวเดิมก่อน',
          icon: 'warning',
          confirmButtonColor: '#0052CC',
          confirmButtonText: 'ตกลง',
          customClass: {
              title: 'font-prompt', popup: 'font-prompt rounded-2xl',
              confirmButton: 'font-prompt rounded-xl px-5 py-2.5'
          }
      });
  }

  <?php if (isset($_GET['error']) && $_GET['error'] === 'already_booked'): ?>
      showAlreadyBookedAlert();
      window.history.replaceState(null, null, window.location.pathname);
  <?php endif; ?>

  // สคริปต์ยืนยันก่อนยกเลิกคิว
  document.querySelectorAll('.cancel-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault(); 
      Swal.fire({
        title: 'ต้องการยกเลิกคิว?',
        text: 'หากยกเลิกแล้ว คุณจะต้องทำการกดจองรอบเวลาใหม่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444', 
        cancelButtonColor: '#6B7280',  
        confirmButtonText: 'ใช่, ยกเลิกคิว',
        cancelButtonText: 'ปิด',
        reverseButtons: true,
        customClass: {
          title: 'font-prompt text-lg', popup: 'font-prompt rounded-3xl',
          confirmButton: 'font-prompt rounded-xl px-5 py-2.5',
          cancelButton: 'font-prompt rounded-xl px-5 py-2.5'
        }
      }).then((result) => {
        if (result.isConfirmed) form.submit(); 
      });
    });
  });

  const modal = document.getElementById('details-modal');
  const modalContent = modal.querySelector('div');

  // ฟังก์ชันเปิด Popup QR Code
  function openModal(patientName, dateLabel, timeLabel, appId, status, campaignTitle) {
      document.getElementById('modal-patient-name').innerText = patientName;
      document.getElementById('modal-date').innerText = dateLabel;
      document.getElementById('modal-time').innerText = timeLabel;
      document.getElementById('modal-campaign').innerText = campaignTitle;
      document.getElementById('modal-id').innerText = appId;

      const qrCodeImg = document.getElementById('modal-qrcode');
      qrCodeImg.src = `api_qrcode.php?id=${appId}`;

      const statusContainer = document.getElementById('modal-status-container');
      const statusText = document.getElementById('modal-status-text');
      if (status === 'confirmed' || status === 'booked') {
          statusContainer.querySelector('div').className = "w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3";
          statusContainer.querySelector('i').className = "fa-solid fa-check text-2xl text-green-500";
          statusText.innerText = "ยืนยันการจองเรียบร้อย";
          statusText.className = "text-green-600 font-bold text-sm";
      } else {
          statusContainer.querySelector('div').className = "w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3";
          statusContainer.querySelector('i').className = "fa-solid fa-times text-2xl text-red-500";
          statusText.innerText = "ยกเลิกการจองแล้ว";
          statusText.className = "text-red-500 font-bold text-sm";
      }

      modal.classList.remove('opacity-0', 'pointer-events-none');
      modalContent.classList.remove('translate-y-full');
  }

  function closeModal() {
      modal.classList.add('opacity-0', 'pointer-events-none');
      modalContent.classList.add('translate-y-full');
      setTimeout(() => { document.getElementById('modal-qrcode').src = ""; }, 300);
  }
</script>

<?php render_footer(); ?>