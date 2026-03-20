<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

session_start();

$booking = $_SESSION['evax_last_booking'] ?? null;
$fullName = (string)($_SESSION['evax_full_name'] ?? '—');

$appointmentId = null;
if (isset($_GET['id'])) {
  $appointmentId = (int)$_GET['id'];
}

$slotDate = $booking['slot_date'] ?? null;
$startTime = $booking['start_time'] ?? null;
$endTime = $booking['end_time'] ?? null;

$dateLabel = $slotDate ? date('j F Y', strtotime((string)$slotDate)) : '—';
$timeLabel = ($startTime && $endTime)
  ? (substr((string)$startTime, 0, 5) . ' - ' . substr((string)$endTime, 0, 5))
  : '—';

// QR code จำลอง (ไม่ใช่ QR จริง) — เหมือนหน้า SummaryPage.tsx ที่เป็น mock
$displayCode = $appointmentId ? ('EVAX-' . str_pad((string)$appointmentId, 5, '0', STR_PAD_LEFT)) : 'EVAX-00000';

render_header('Booking Confirmed');
?>

<div class="p-5 flex flex-col h-full bg-[#f4f7fa] animate-in fade-in slide-in-from-bottom-8 duration-700">
  <div class="flex-1 flex flex-col items-center pb-24">
    <div class="mt-6 mb-8 flex flex-col items-center text-center">
      <div class="relative mb-4">
        <div class="absolute inset-0 bg-green-200 rounded-full animate-ping opacity-20"></div>
        <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center shadow-inner relative z-10">
          <!-- CheckCircle icon -->
          <svg viewBox="0 0 24 24" class="w-14 h-14 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <path d="M22 4 12 14.01l-3-3"></path>
          </svg>
        </div>
      </div>
      <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Booking Confirmed!</h2>
      <p class="text-sm font-medium text-gray-500 mt-2">Please present your QR code on arrival</p>
    </div>

    <div class="w-full bg-white rounded-[24px] shadow-xl border border-gray-100 overflow-hidden relative">
      <div class="absolute left-0 top-[55%] -mt-4 -ml-4 w-8 h-8 bg-[#f4f7fa] rounded-full border-r border-gray-100 shadow-inner"></div>
      <div class="absolute right-0 top-[55%] -mt-4 -mr-4 w-8 h-8 bg-[#f4f7fa] rounded-full border-l border-gray-100 shadow-inner"></div>
      <div class="absolute left-6 right-6 top-[55%] border-t-2 border-dashed border-gray-200"></div>

      <div class="p-7 pb-8">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-6 text-center">Booking Details</h3>

        <div class="space-y-5">
          <div class="flex gap-4 items-start">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
              <!-- User icon -->
              <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#0052CC]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </div>
            <div>
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Patient Name</p>
              <p class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>

          <div class="flex gap-4 items-start">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
              <!-- Calendar icon -->
              <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#0052CC]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                <path d="M16 2v4"></path>
                <path d="M8 2v4"></path>
                <path d="M3 10h18"></path>
              </svg>
            </div>
            <div>
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Date</p>
              <p class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>

          <div class="flex gap-4 items-start">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
              <!-- Clock icon -->
              <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#0052CC]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
              </svg>
            </div>
            <div>
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Time</p>
              <p class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>

          <div class="flex gap-4 items-start">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
              <!-- MapPin icon -->
              <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#0052CC]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
            </div>
            <div>
              <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Location</p>
              <p class="font-bold text-gray-900 text-base leading-tight mt-1">
                คลินิกเวชกรรม มหาวิทยาลัยรังสิต <br/>
                <span class="text-gray-500 font-medium text-sm">Building 4/2, Floor 2</span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="pt-8 pb-7 px-7 flex flex-col items-center justify-center bg-gray-50">
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 mb-3 relative">
          <!-- QR code mock -->
          <div class="w-[140px] h-[140px] grid grid-cols-7 gap-1">
            <?php for ($i = 0; $i < 49; $i++): ?>
              <?php $on = (($i * 37 + 13) % 9) < 4; ?>
              <div class="<?= $on ? 'bg-gray-900' : 'bg-gray-200' ?> rounded-[2px]"></div>
            <?php endfor; ?>
          </div>
        </div>
        <p class="text-sm font-bold font-mono tracking-widest text-gray-600 bg-gray-200 px-4 py-1.5 rounded-full">
          ID: <?= htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
    </div>
  </div>

<div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-gray-100 z-20 flex flex-col gap-3 shadow-[0_-10px_30px_-15px_rgba(0,0,0,0.1)]">
    <a
      href="my_bookings.php"
      class="w-full flex items-center justify-center bg-[#0052CC] hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-all shadow-sm active:scale-[0.98]"
    >
      ดูประวัติการจอง (My Bookings)
    </a>
  </div>
</div>

<?php render_footer(); ?>

