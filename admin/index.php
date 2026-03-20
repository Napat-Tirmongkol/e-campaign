<?php
// admin/index.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
// ไม่ต้อง Query PHP ตรงนี้แล้ว เพราะเราจะให้ Javascript เป็นตัวดึงตั้งแต่เริ่มโหลดหน้า
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">ภาพรวมระบบ (Dashboard)</h1>
        <p class="text-sm text-gray-500 mt-1">ข้อมูลอัปเดตอัตโนมัติ (Real-time)</p>
    </div>
    <a href="bookings.php" class="hidden md:flex bg-gray-100 hover:bg-gray-200 text-[#0052CC] px-4 py-2 rounded-xl font-medium transition-colors text-sm shadow-sm items-center gap-2 font-prompt">
        ดูคิวทั้งหมด ➔
    </a>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-8">
    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center transition-all duration-300" id="card-total">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center text-lg"><i class="fa-solid fa-chart-line"></i></div>
            <p class="text-sm text-gray-500 font-medium">การจองทั้งหมด</p>
        </div>
        <h3 class="text-3xl font-bold text-gray-900 ml-1"><span id="stat-total">...</span> <span class="text-xs font-normal text-gray-400">รายการ</span></h3>
    </div>
    
    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center transition-all duration-300" id="card-pending">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-yellow-50 text-yellow-500 rounded-lg flex items-center justify-center text-lg"><i class="fa-solid fa-hourglass-half"></i></div>
            <p class="text-sm text-gray-500 font-medium">รออนุมัติ</p>
        </div>
        <h3 class="text-3xl font-bold text-yellow-600 ml-1"><span id="stat-pending">...</span> <span class="text-xs font-normal text-gray-400">รายการ</span></h3>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center transition-all duration-300" id="card-confirmed">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-green-50 text-green-500 rounded-lg flex items-center justify-center text-lg"><i class="fa-solid fa-circle-check"></i></div>
            <p class="text-sm text-gray-500 font-medium">อนุมัติแล้ว</p>
        </div>
        <h3 class="text-3xl font-bold text-green-600 ml-1"><span id="stat-confirmed">...</span> <span class="text-xs font-normal text-gray-400">รายการ</span></h3>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center transition-all duration-300" id="card-cancelled">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-red-50 text-red-500 rounded-lg flex items-center justify-center text-lg"><i class="fa-solid fa-circle-xmark"></i></div>
            <p class="text-sm text-gray-500 font-medium">ยกเลิก/สละสิทธิ์</p>
        </div>
        <h3 class="text-3xl font-bold text-red-500 ml-1"><span id="stat-cancelled">...</span> <span class="text-xs font-normal text-gray-400">รายการ</span></h3>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
    <div onclick="toggleTodayTable()" class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors select-none">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-bold text-[#0052CC] flex items-center gap-2">
                <i class="fa-solid fa-calendar-day"></i> ตารางนัดหมายประจำวันนี้
            </h2>
            <span class="text-sm font-medium text-gray-500 bg-white px-3 py-1 rounded-full border border-gray-200">
                <?= date('d M Y') ?>
            </span>
            <span class="text-xs font-bold text-[#0052CC] bg-blue-100 px-2 py-1 rounded-md">
                <span id="badge-today-count">0</span> คิว
            </span>
        </div>
        <svg id="arrow-icon" class="w-5 h-5 text-gray-500 transform transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </div>
    
    <div id="table-container" class="hidden animate-in fade-in slide-in-from-top-2 duration-300">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-white text-gray-400 font-semibold border-b border-gray-100 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">เวลา</th>
                        <th class="px-6 py-4">ชื่อผู้เข้ารับวัคซีน</th>
                        <th class="px-6 py-4">เบอร์ติดต่อ</th>
                        <th class="px-6 py-4 text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="tbody-today">
                    <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleTodayTable() {
    const container = document.getElementById('table-container');
    const arrow = document.getElementById('arrow-icon');
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        arrow.classList.add('rotate-180');
    } else {
        container.classList.add('hidden');
        arrow.classList.remove('rotate-180');
    }
}

// ==========================================
// ระบบ Real-time (AJAX Polling)
// ==========================================
function fetchDashboardData() {
    fetch('ajax_dashboard.php')
        .then(response => response.json())
        .then(data => {
            if(data.error) return;

            // 1. อัปเดตสถิติ พร้อม Effect กระพริบเบาๆ ถ้าเลขเปลี่ยน
            updateStat('stat-total', data.stats.total, 'card-total');
            updateStat('stat-pending', data.stats.pending, 'card-pending');
            updateStat('stat-confirmed', data.stats.confirmed, 'card-confirmed');
            updateStat('stat-cancelled', data.stats.cancelled, 'card-cancelled');

            // 2. อัปเดตตารางและ Badge
            document.getElementById('badge-today-count').innerText = data.todayCount;
            document.getElementById('tbody-today').innerHTML = data.tableHtml;
        })
        .catch(error => console.error('Error fetching data:', error));
}

function updateStat(elementId, newValue, cardId) {
    const el = document.getElementById(elementId);
    if (el.innerText !== String(newValue) && el.innerText !== '...') {
        // ทำ Effect ให้กล่องกระพริบถ้ามีข้อมูลใหม่เข้ามา
        const card = document.getElementById(cardId);
        card.classList.add('scale-[1.02]', 'shadow-md', 'ring-2', 'ring-blue-200');
        setTimeout(() => {
            card.classList.remove('scale-[1.02]', 'shadow-md', 'ring-2', 'ring-blue-200');
        }, 500);
    }
    el.innerText = newValue;
}

// โหลดครั้งแรกทันทีที่เปิดหน้า
fetchDashboardData();

// ตั้งเวลาให้ดึงข้อมูลใหม่ทุกๆ 5 วินาที
setInterval(fetchDashboardData, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>