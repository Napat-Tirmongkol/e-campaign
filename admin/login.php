<?php
// admin/login.php
session_start();

// ถ้า Login อยู่แล้ว ให้ข้ามไปหน้า Dashboard เลย
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // TODO: เปลี่ยนเป็นการเช็คจากฐานข้อมูลได้ในอนาคต
    if ($username === 'admin' && $password === '1234') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'Administrator';
        header('Location: index.php');
        exit;
    } else {
        $error = 'ชื่อผู้ใช้ หรือ รหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - E-Vax</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Prompt', sans-serif; } </style>
</head>
<body class="bg-gray-50 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-sm border border-gray-100">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-[#0052CC]">E-Vax Admin</h1>
            <p class="text-sm text-gray-500 mt-1">ระบบจัดการหลังบ้าน</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-500 text-sm p-3 rounded-lg mb-4 text-center border border-red-100">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="username" required class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none transition-all">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052CC] outline-none transition-all">
            </div>
            <button type="submit" class="w-full bg-[#0052CC] text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>
</body>
</html>