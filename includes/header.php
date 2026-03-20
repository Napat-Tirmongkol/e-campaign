<?php
declare(strict_types=1);

/**
 * Shared header (Tailwind CDN + Prompt)
 * Usage:
 * require_once __DIR__ . '/header.php';
 * render_header('Page Title');
 */
function render_header(string $title = 'E-Vax'): void {
  ?>
  <!doctype html>
  <html lang="th">
    <head>
      <meta charset="utf-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
      <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

      <link rel="icon" href="data:,">

      <script src="https://cdn.tailwindcss.com"></script>
	  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <script>
        // ปิด Warning ของ Tailwind
        if (window.console && window.console.log) {
          const originalLog = console.log;
          console.log = function() {
            if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('cdn.tailwindcss.com')) return;
            originalLog.apply(console, arguments);
          };
        }
      </script>
      <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
      
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
      
      <style>
        .custom-scrollbar::-webkit-scrollbar { display: none; }
        .custom-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
      </style>
    </head>
    
    <body class="bg-[#f4f7fa] h-[100dvh] w-full overflow-hidden flex justify-center text-gray-900 font-prompt">
      
      <div id="page-loader" class="fixed inset-0 z-[9999] bg-[#f4f7fa] flex flex-col items-center justify-center transition-opacity duration-300">
        <div class="relative w-16 h-16">
          <div class="absolute inset-0 rounded-full border-4 border-blue-100"></div>
          <div class="absolute inset-0 rounded-full border-4 border-[#0052CC] border-t-transparent animate-spin"></div>
        </div>
        <p class="mt-4 text-[#0052CC] font-semibold text-sm animate-pulse font-prompt">กำลังโหลดข้อมูล...</p>
      </div>
      
      <main class="w-full max-w-md h-full bg-white shadow-xl relative overflow-y-auto overflow-x-hidden custom-scrollbar">
  <?php
}