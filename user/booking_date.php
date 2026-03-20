<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

session_start();

// 1. ตรวจสอบ Login
$studentId = isset($_SESSION['evax_student_id']) ? (int)$_SESSION['evax_student_id'] : 0;
if ($studentId <= 0) {
    header('Location: index.php', true, 303);
    exit;
}

// 2. [เช็ค 1 คน 1 คิว]
try {
    $pdo = db();
    $checkSql = "SELECT COUNT(*) FROM vac_appointments WHERE student_id = :sid AND status IN ('confirmed', 'booked')";
    $stmtCheck = $pdo->prepare($checkSql);
    $stmtCheck->execute([':sid' => $studentId]);
    
    if ((int)$stmtCheck->fetchColumn() > 0) {
        header('Location: my_bookings.php?error=already_booked', true, 303);
        exit;
    }
} catch (PDOException $e) {
    error_log("Check Booking Error: " . $e->getMessage());
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$monthName = date('F', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
$startDayOfWeek = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

$selectedDate = null;
if (isset($_GET['selected_date'])) {
    $selectedDate = (int)$_GET['selected_date'];
}
if ($selectedDate !== null && ($selectedDate < 1 || $selectedDate > $daysInMonth)) {
    $selectedDate = null;
}

$dailyStats = [];
$dbError = null;

try {
    $sqlTotal = "
        SELECT DAY(ts.slot_date) AS day_num, COALESCE(SUM(ts.max_capacity), 0) AS total_capacity
        FROM vac_time_slots ts
        WHERE ts.slot_date >= :startDate AND ts.slot_date < :endDate
        GROUP BY DAY(ts.slot_date)
    ";
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-d', strtotime($startDate . ' +1 month'));

    $stmt = $pdo->prepare($sqlTotal);
    $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
    foreach ($stmt->fetchAll() as $row) {
        $day = (int)$row['day_num'];
        $dailyStats[$day] = ['total' => (int)$row['total_capacity'], 'booked' => 0, 'remaining' => (int)$row['total_capacity']];
    }

    $sqlBooked = "
        SELECT DAY(ts.slot_date) AS day_num, COUNT(*) AS booked_count
        FROM vac_appointments ap
        INNER JOIN vac_time_slots ts ON ts.id = ap.slot_id
        WHERE ts.slot_date >= :startDate AND ts.slot_date < :endDate
          AND ap.status IN ('confirmed', 'booked')
        GROUP BY DAY(ts.slot_date)
    ";
    $stmt2 = $pdo->prepare($sqlBooked);
    $stmt2->execute([':startDate' => $startDate, ':endDate' => $endDate]);
    foreach ($stmt2->fetchAll() as $row) {
        $day = (int)$row['day_num'];
        $booked = (int)$row['booked_count'];
        if (!isset($dailyStats[$day])) $dailyStats[$day] = ['total' => 0, 'booked' => 0, 'remaining' => 0];
        $dailyStats[$day]['booked'] = $booked;
        $dailyStats[$day]['remaining'] = max(0, $dailyStats[$day]['total'] - $booked);
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

function density_color(string $density): string {
    return match ($density) {
        'high' => 'bg-red-500',
        'medium' => 'bg-yellow-400',
        'low' => 'bg-green-500',
        default => 'bg-gray-200',
    };
}

function density_for_day(int $year, int $month, int $day, array $dailyStats, ?string $dbError): string {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    if ($dateStr < date('Y-m-d')) return 'disabled';
    if ($dbError !== null) return 'disabled';
    
    $total = $dailyStats[$day]['total'] ?? 0;
    $remaining = $dailyStats[$day]['remaining'] ?? 0;
    if ($total <= 0) return 'disabled';

    $ratio = $remaining / $total;
    if ($ratio > 0.5) return 'low';
    if ($ratio >= 0.1) return 'medium';
    return 'high';
}

render_header('Select Date');
?>

<div class="p-5 pb-32 flex flex-col h-full animate-in fade-in slide-in-from-right-4 duration-500">
    <div class="flex-1">
        <div class="flex items-center gap-2 mb-5">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-calendar-day text-[#0052CC]"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">Select Date</h2>
                <p class="text-xs text-gray-500">Choose your vaccination day</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
            <div class="flex items-center justify-between mb-5">
                <?php
                    $prev = strtotime(sprintf('%04d-%02d-01', $year, $month) . ' -1 month');
                    $next = strtotime(sprintf('%04d-%02d-01', $year, $month) . ' +1 month');
                ?>
                <a href="?year=<?= (int)date('Y', $prev) ?>&month=<?= (int)date('n', $prev) ?>" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500 transition-colors">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <h3 class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($monthName . ' ' . $year) ?></h3>
                <a href="?year=<?= (int)date('Y', $next) ?>&month=<?= (int)date('n', $next) ?>" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500 transition-colors">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <form method="GET" class="grid grid-cols-7 gap-y-4 gap-x-2 mb-2">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                
                <?php foreach ($weekDays as $d): ?>
                    <div class="text-center text-xs font-bold text-gray-400 uppercase tracking-wider"><?= $d ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                    <div class="h-10"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                    $density = density_for_day($year, $month, $day, $dailyStats, $dbError);
                    $isSelected = ($selectedDate === $day);
                    $isDisabled = ($density === 'disabled');

                    $numberClasses = "w-9 h-9 flex items-center justify-center rounded-full text-sm transition-all " .
                        ($isSelected ? "bg-[#0052CC] text-white font-bold shadow-md" : ($isDisabled ? "text-gray-300" : "text-gray-700 font-medium group-hover:bg-blue-50"));
                    $dotClasses = "w-1.5 h-1.5 rounded-full mt-1 absolute bottom-0 " . density_color($density) . " " . (($isSelected && !$isDisabled) ? "opacity-100" : "opacity-80") . " " . ($isDisabled ? "hidden" : "");
                ?>
                    <button type="submit" name="selected_date" value="<?= $day ?>" <?= $isDisabled ? 'disabled' : '' ?> class="relative flex flex-col items-center justify-center h-12 w-full group cursor-pointer disabled:cursor-not-allowed">
                        <div class="<?= $numberClasses ?>"><?= $day ?></div>
                        <div class="<?= $dotClasses ?>"></div>
                    </button>
                <?php endfor; ?>
            </form>
        </div>

        <div class="flex items-center justify-between text-xs font-semibold text-gray-600 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-green-500"></span> Available</div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-yellow-400"></span> Medium</div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-500"></span> Full</div>
        </div>
    </div>
</div>

<div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-gray-100 z-20 flex gap-3 shadow-[0_-4px_20px_-10px_rgba(0,0,0,0.1)]">
    <a href="profile.php" class="px-6 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors text-center">Back</a>
    <?php if ($selectedDate === null || density_for_day($year, $month, (int)$selectedDate, $dailyStats, $dbError) === 'high'): ?>
        <button type="button" disabled class="flex-1 bg-gray-300 text-gray-500 cursor-not-allowed font-bold py-4 rounded-xl text-center">Next Step</button>
    <?php else: ?>
        <a href="booking_time.php?year=<?= $year ?>&month=<?= $month ?>&day=<?= $selectedDate ?>" class="flex-1 bg-[#0052CC] hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-colors text-center shadow-sm">Next Step</a>
    <?php endif; ?>
</div>

<?php render_footer(); ?>