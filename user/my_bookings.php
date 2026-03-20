<?php
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

// ดึงข้อมูลการจอง
$bookings = [];
try {
  $pdo = db();
  $sql = "
    SELECT a.id AS appointment_id, a.status, t.slot_date, t.start_time, t.end_time
    FROM vac_appointments a
    JOIN vac_time_slots t ON a.slot_id = t.id
    WHERE a.student_id = :student_id
    AND a.status IN ('confirmed', 'booked')
    ORDER BY t.slot_date DESC, t.start_time DESC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':student_id' => $studentId]);
  $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
  die("Error fetching bookings: " . $e->getMessage());
}

// เช็คว่ามีคิวที่ยืนยันแล้วหรือไม่ (เพื่อใช้ล็อกปุ่มจอง)
$hasActiveBooking = false;
foreach ($bookings as $b) {
    if ($b['status'] === 'confirmed' || $b['status'] === 'booked') {
        $hasActiveBooking = true;
        break;
    }
}

render_header('My Bookings');
?>

<div class="p-5 pb-32 flex flex-col h-full bg-[#f4f7fa] animate-in fade-in duration-500">
  <div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">ประวัติการจอง</h2>
    <p class="text-sm text-gray-500 mt-1">รายการนัดหมายฉีดวัคซีนของคุณ</p>
  </div>

  <div class="space-y-4">
    <?php if (count($bookings) === 0): ?>
      <div class="bg-white rounded-2xl p-8 text-center border border-gray-100 shadow-sm">
        <div class="text-gray-400 mb-2">📅</div>
        <p class="text-gray-500 font-medium">ยังไม่มีประวัติการจอง</p>
      </div>
    <?php else: ?>
      <?php foreach ($bookings as $b): 
        $dateLabel = date('j F Y', strtotime($b['slot_date']));
        $timeLabel = substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5);
        $isConfirmed = ($b['status'] === 'confirmed' || $b['status'] === 'booked');
        $patientName = htmlspecialchars($_SESSION['evax_full_name'] ?? 'ไม่ระบุชื่อ', ENT_QUOTES);
        
        // เตรียมข้อมูลเป็น JSON String เพื่อส่งให้ Javascript
        $safeDate = htmlspecialchars($dateLabel, ENT_QUOTES);
        $safeTime = htmlspecialchars($timeLabel, ENT_QUOTES);
      ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm relative overflow-hidden mb-4 cursor-pointer active:scale-[0.98] transition-all" 
           onclick="openModal('<?= $patientName ?>', '<?= $safeDate ?>', '<?= $safeTime ?>', '<?= $b['appointment_id'] ?>', '<?= $b['status'] ?>')">
          
          <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $isConfirmed ? 'bg-green-500' : 'bg-red-400' ?>"></div>
          
          <div class="p-5 flex justify-between items-start pl-2">
              <div>
                  <p class="text-sm text-gray-500 mb-1">วันที่นัดหมาย</p>
                  <p class="font-bold text-gray-900 text-lg font-prompt"><?= htmlspecialchars($dateLabel) ?></p>
                  <p class="text-[#0052CC] font-semibold mt-0.5">เวลา: <?= htmlspecialchars($timeLabel) ?></p>
              </div>
              <div>
                  <?php if ($isConfirmed): ?>
                      <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">ยืนยันแล้ว</span>
                  <?php else: ?>
                      <span class="px-3 py-1 bg-red-50 text-red-500 text-xs font-bold rounded-full">ยกเลิกแล้ว</span>
                  <?php endif; ?>
              </div>
          </div>

          <?php if ($isConfirmed): ?>
              <div class="px-5 pb-5 border-t border-gray-50 pt-4" onclick="event.stopPropagation()"> 
                  <form action="cancel_booking.php" method="POST" class="cancel-form">
                      <input type="hidden" name="appointment_id" value="<?= $b['appointment_id'] ?>">
                      <button type="submit" class="w-full py-2.5 text-sm font-bold text-red-500 bg-red-50 hover:bg-red-100 rounded-xl transition-colors active:scale-[0.98]">
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
  <?php if ($hasActiveBooking): ?>
    <button onclick="showAlreadyBookedAlert()" class="flex w-full items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-4 rounded-xl transition-all font-prompt">
      จองวัคซีน
    </button>
  <?php else: ?>
    <a href="booking_date.php" class="flex w-full items-center justify-center bg-[#0052CC] hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-all font-prompt">
      จองวัคซีน
    </a>
  <?php endif; ?>
</div>

<div id="details-modal" class="fixed inset-0 z-[100] bg-black/50 backdrop-blur-sm flex items-end justify-center opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeModal()">
    <div class="bg-white w-full max-w-md rounded-t-3xl p-6 pb-12 shadow-2xl transform translate-y-full transition-transform duration-300 relative" onclick="event.stopPropagation()">
        
        <div class="absolute left-1/2 -top-3.5 -translate-x-1/2 w-12 h-1.5 bg-gray-300 rounded-full cursor-pointer" onclick="closeModal()"></div>
        
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-900 font-prompt">รายละเอียดการจอง</h2>
            <button class="w-9 h-9 flex items-center justify-center bg-gray-100 text-gray-500 rounded-full hover:bg-gray-200 transition-colors" onclick="closeModal()">✕</button>
        </div>

        <div class="bg-white text-center">
            
            <div id="modal-status-container" class="mb-8 flex flex-col items-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl text-green-500">✓</span>
                </div>
                <p id="modal-status-text" class="text-gray-500 font-medium">ยืนยันการจองเรียบร้อย</p>
            </div>

            <div class="space-y-4 text-left bg-gray-50 p-6 rounded-2xl mb-8 border border-gray-100 shadow-inner">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-bold">Patient Name</p>
                    <p id="modal-patient-name" class="text-lg font-bold text-gray-900 font-prompt"></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-bold">Date</p>
                    <p id="modal-date" class="text-lg font-bold text-gray-900 font-prompt"></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-bold">Time</p>
                    <p id="modal-time" class="text-lg font-bold text-[#0052CC] font-prompt"></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-bold">Location</p>
                    <p class="text-lg font-bold text-gray-900 font-prompt">คลินิกเวชกรรม มหาวิทยาลัยรังสิต Building 4/2, Floor 2</p>
                </div>
            </div>

            <div class="mt-10 border-t-2 border-dashed border-gray-200 pt-10">
                <p class="text-sm text-gray-400 mb-6">แสดง QR Code นี้แก่เจ้าหน้าที่หน้างาน</p>
                
                <div class="bg-white p-3 inline-block rounded-2xl border-4 border-white shadow-[0_0_20px_rgba(0,0,0,0.08)] mb-6">
                    <img id="modal-qrcode" src="" alt="Booking QR Code" class="w-48 h-48 mx-auto" />
                </div>
                
                <p class="text-xs text-gray-400">ID: <span id="modal-id"></span></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // ============================================
  // เพิ่มสคริปต์แจ้งเตือนจองได้แค่คิวเดียว
  // ============================================
  function showAlreadyBookedAlert() {
      Swal.fire({
          title: 'ไม่สามารถจองเพิ่มได้',
          text: 'คุณมีคิวที่กำลังดำเนินการอยู่แล้ว (จำกัด 1 คิวต่อท่าน) หากต้องการเปลี่ยนวัน กรุณายกเลิกคิวเดิมก่อน',
          icon: 'warning',
          confirmButtonColor: '#0052CC',
          confirmButtonText: 'ตกลง',
          customClass: {
              title: 'font-prompt', 
              popup: 'font-prompt rounded-2xl',
              confirmButton: 'font-prompt rounded-xl px-5 py-2.5'
          }
      });
  }

  // ถ้าถูกเด้งกลับมาจากหน้าอื่น (เช่น booking_date.php) จะมี ?error=already_booked
  <?php if (isset($_GET['error']) && $_GET['error'] === 'already_booked'): ?>
      showAlreadyBookedAlert();
      // ลบพารามิเตอร์ทิ้ง เพื่อที่เวลากด Refresh หน้าเว็บจะได้ไม่โชว์ซ้ำ
      window.history.replaceState(null, null, window.location.pathname);
  <?php endif; ?>

  // ============================================

  // สคริปต์จัดการฟอร์มยกเลิกคิว (ยืนยันก่อนลบ)
  document.querySelectorAll('.cancel-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault(); 
      Swal.fire({
        title: 'ต้องการยกเลิกคิว?',
        text: 'หากยกเลิกแล้ว คุณจะต้องทำการจองคิวใหม่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444', 
        cancelButtonColor: '#6B7280',  
        confirmButtonText: 'ใช่, ยกเลิกคิว',
        cancelButtonText: 'ปิด',
        reverseButtons: true,
        customClass: {
          title: 'font-prompt', popup: 'font-prompt rounded-2xl',
          confirmButton: 'font-prompt rounded-xl px-5 py-2.5',
          cancelButton: 'font-prompt rounded-xl px-5 py-2.5'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit(); 
        }
      });
    });
  });

  const modal = document.getElementById('details-modal');
  const modalContent = modal.querySelector('div');

  // ฟังก์ชันเปิด Popup และโหลดข้อมูล
  function openModal(patientName, dateLabel, timeLabel, appId, status) {
      document.getElementById('modal-patient-name').innerText = patientName;
      document.getElementById('modal-date').innerText = dateLabel;
      document.getElementById('modal-time').innerText = timeLabel;
      document.getElementById('modal-id').innerText = appId;

      const qrCodeImg = document.getElementById('modal-qrcode');
      qrCodeImg.src = `api_qrcode.php?id=${appId}`;

      const statusContainer = document.getElementById('modal-status-container');
      const statusText = document.getElementById('modal-status-text');
      if (status === 'confirmed' || status === 'booked') {
          statusContainer.querySelector('div').className = "w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4";
          statusContainer.querySelector('span').innerText = "✓";
          statusContainer.querySelector('span').className = "text-3xl text-green-500";
          statusText.innerText = "ยืนยันการจองเรียบร้อย";
          statusText.className = "text-gray-500 font-medium";
      } else {
          statusContainer.querySelector('div').className = "w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4";
          statusContainer.querySelector('span').innerText = "✕";
          statusContainer.querySelector('span').className = "text-3xl text-red-500";
          statusText.innerText = "ยกเลิกการจองแล้ว";
          statusText.className = "text-red-500 font-medium";
      }

      modal.classList.remove('opacity-0', 'pointer-events-none');
      modalContent.classList.remove('translate-y-full');
  }

  // ฟังก์ชันปิด Popup
  function closeModal() {
      modal.classList.add('opacity-0', 'pointer-events-none');
      modalContent.classList.add('translate-y-full');

      setTimeout(() => {
          document.getElementById('modal-qrcode').src = "";
      }, 300);
  }
</script>
<?php render_footer(); ?>