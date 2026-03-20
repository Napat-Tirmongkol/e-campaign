<?php
// admin/index.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

// 1. สรุปภาพรวมแคมเปญที่กำลังเปิดอยู่ (Active)
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_campaigns,
        (SELECT COUNT(*) FROM camp_appointments WHERE status = 'booked') as pending_count,
        (SELECT COUNT(*) FROM camp_appointments WHERE status = 'confirmed') as confirmed_count
    FROM campaigns WHERE status = 'active'
");
$stats = $stmt->fetch();

// 2. ดึง 5 แคมเปญยอดฮิตที่มีคนจองเยอะสุด
$popular_stmt = $pdo->query("
    SELECT c.title, COUNT(a.id) as booking_count
    FROM campaigns c
    LEFT JOIN camp_appointments a ON c.id = a.campaign_id
    GROUP BY c.id
    ORDER BY booking_count DESC
    LIMIT 5
");
$popular_campaigns = $popular_stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900">แผงควบคุมระบบแคมเปญ (Dashboard)</h1>
    <p class="text-gray-500">ภาพรวมสถิติการลงทะเบียนเข้าร่วมกิจกรรมทั้งหมด</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-xl">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">แคมเปญที่เปิดอยู่</p>
            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_campaigns']) ?></h3>
        </div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-yellow-100 text-yellow-600 rounded-xl flex items-center justify-center text-xl">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">รออนุมัติคิว</p>
            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($stats['pending_count']) ?></h3>
        </div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-xl flex items-center justify-center text-xl">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">อนุมัติแล้วทั้งหมด</p>
            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($stats['confirmed_count']) ?></h3>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-50 bg-gray-50/50">
            <h3 class="font-bold text-gray-800">แคมเปญยอดนิยม</h3>
        </div>
        <div class="p-5">
            <div class="space-y-4">
                <?php foreach($popular_campaigns as $pc): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-medium"><?= htmlspecialchars($pc['title']) ?></span>
                    <span class="bg-gray-100 px-3 py-1 rounded-full text-xs font-bold text-gray-600"><?= number_format($pc['booking_count']) ?> จอง</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <a href="campaigns.php" class="bg-[#0052CC] text-white p-6 rounded-2xl flex flex-col justify-between hover:bg-blue-700 transition-all">
            <i class="fa-solid fa-plus-circle text-2xl mb-4"></i>
            <span class="font-bold">สร้างแคมเปญใหม่</span>
        </a>
        <a href="time_slots.php" class="bg-white border border-gray-100 p-6 rounded-2xl flex flex-col justify-between hover:bg-gray-50 transition-all shadow-sm">
            <i class="fa-solid fa-calendar-plus text-2xl text-[#0052CC] mb-4"></i>
            <span class="font-bold text-gray-800">เพิ่มรอบเวลา</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>