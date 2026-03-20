<?php
// user/booking_time.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

session_start();

$studentId = isset($_SESSION['evax_student_id']) ? (int)$_SESSION['evax_student_id'] : 0;
if ($studentId <= 0) {
    header('Location: index.php', true, 303);
    exit;
}

$year = (int)($_GET['year'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$day = (int)($_GET['day'] ?? 0);
$campaignId = (int)($_GET['campaign_id'] ?? 0);

if ($year == 0 || $month == 0 || $day == 0 || $campaignId == 0) {
    header('Location: booking_campaign.php');
    exit;
}

$selectedDateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
$displayDate = date('j F Y', strtotime($selectedDateStr));

$pdo = db();

// 1. ดึงข้อมูลแคมเปญที่เลือกมาแสดง (ไม่ต้องมี Dropdown แล้ว)
$campaign = null;
try {
    $stmtCamp = $pdo->prepare("SELECT id, title FROM campaigns WHERE id = :id");
    $stmtCamp->execute([':id' => $campaignId]);
    $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        header('Location: booking_campaign.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error fetching campaign");
}

// 2. ดึงสล็อตเวลาของวันที่เลือก (เฉพาะของแคมเปญนี้)
$timeSlots = [];
try {
    $sqlSlots = "
        SELECT 
            t.id, t.start_time, t.end_time, t.max_capacity,
            (SELECT COUNT(*) FROM camp_appointments a WHERE a.slot_id = t.id AND a.status IN ('booked', 'confirmed')) as booked_count
        FROM camp_time_slots t
        WHERE t.slot_date = :date AND t.campaign_id = :cid
        ORDER BY t.start_time ASC
    ";
    $stmt = $pdo->prepare($sqlSlots);
    $stmt->execute([':date' => $selectedDateStr, ':cid' => $campaignId]);
    $timeSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching time slots: " . $e->getMessage());
}

render_header('เลือกรอบเวลา');
?>

<div class="p-5 pb-32 flex flex-col h-full animate-in fade-in slide-in-from-right-4 duration-500">
    <div class="flex-1">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-clock text-[#0052CC]"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 leading-tight">เลือกรอบเวลา</h2>
                <p class="text-sm text-[#0052CC] font-semibold mt-0.5"><?= $displayDate ?></p>
            </div>
        </div>

        <form action="submit_booking.php" method="POST" id="bookingForm">
            <input type="hidden" name="booking_date" value="<?= $selectedDateStr ?>">
            <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
            
            <div class="mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">
                    <i class="fa-solid fa-thumbtack mr-1"></i> กิจกรรมที่เลือก
                </label>
                <div class="font-bold text-gray-900 text-lg">
                    <?= htmlspecialchars($campaign['title']) ?>
                </div>
            </div>

            <label class="block text-sm font-semibold text-gray-700 mb-3 ml-1">เลือกรอบเวลา (ที่ว่างอยู่)</label>
            <div class="space-y-3">
                <?php if (count($timeSlots) === 0): ?>
                    <div class="bg-gray-50 p-8 rounded-3xl text-center border-2 border-dashed border-gray-200">
                        <div class="text-3xl text-gray-300 mb-2"><i class="fa-regular fa-clock"></i></div>
                        <p class="text-gray-500 font-medium">ไม่พบรอบเวลาที่เปิดรับในวันนี้</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($timeSlots as $slot): 
                        $timeStr = substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5);
                        $remaining = $slot['max_capacity'] - $slot['booked_count'];
                        $isFull = $remaining <= 0;
                    ?>
                        <label class="relative block bg-white border <?= $isFull ? 'border-red-200 opacity-60' : 'border-gray-200 cursor-pointer hover:border-[#0052CC] hover:bg-blue-50/50 hover:shadow-sm' ?> rounded-2xl p-4 transition-all">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="slot_id" value="<?= $slot['id'] ?>" <?= $isFull ? 'disabled' : 'required' ?> class="w-5 h-5 text-[#0052CC] focus:ring-[#0052CC] border-gray-300 cursor-pointer disabled:cursor-not-allowed">
                                    <span class="font-bold text-gray-900 text-lg font-prompt"><?= $timeStr ?></span>
                                </div>
                                <div>
                                    <?php if ($isFull): ?>
                                        <span class="text-xs font-bold text-red-500 bg-red-50 px-3 py-1.5 rounded-lg border border-red-100">เต็มแล้ว</span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-lg border border-green-100">ว่าง <?= $remaining ?> ที่</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-gray-100 z-20 flex gap-3 shadow-[0_-4px_20px_-10px_rgba(0,0,0,0.1)]">
                <a href="booking_date.php?year=<?= $year ?>&month=<?= $month ?>&campaign_id=<?= $campaignId ?>" class="px-6 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors text-center shadow-sm active:scale-95">ย้อนกลับ</a>
                <button type="submit" class="flex-1 bg-[#0052CC] hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-colors text-center shadow-sm active:scale-95">
                    ยืนยันรอบเวลา
                </button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>