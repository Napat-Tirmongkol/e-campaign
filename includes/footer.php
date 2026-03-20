<?php
declare(strict_types=1);

/**
 * Shared footer
 * Usage:
 * require_once __DIR__ . '/footer.php';
 * render_footer();
 */
function render_footer(): void {
  ?>
      </main>

      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const loader = document.getElementById('page-loader');
          if (!loader) return;

          // 1. ซ่อน Loader ทันทีเมื่อหน้าเว็บโหลดเสร็จสมบูรณ์
          window.addEventListener('load', () => {
            setTimeout(() => {
              loader.classList.add('opacity-0');
              setTimeout(() => {
                loader.style.display = 'none';
              }, 300); // รอให้ transition เฟดหายไป 0.3 วิ
            }, 400); // หน่วงเวลา 0.6 วินาที (แก้ตัวเลขตรงนี้ได้)
          });

          // 2. ป้องกันผู้ใช้กด Back แล้วหน้าจ้างค้าง (ดึงมาจาก Back/Forward Cache)
          window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
              loader.classList.add('opacity-0');
              loader.style.display = 'none';
            }
          });

          // 3. แสดง Loader อีกครั้ง เมื่อกำลังเปลี่ยนหน้าเว็บ (กดลิงก์ หรือ submit ฟอร์ม)
          window.addEventListener('beforeunload', () => {
            loader.style.display = 'flex';
            // RequestAnimationFrame ช่วยให้เบราว์เซอร์เรนเดอร์ UI ทันก่อนเปลี่ยนหน้า
            requestAnimationFrame(() => {
              loader.classList.remove('opacity-0');
            });
          });
        });
      </script>
      </body>
  </html>
  <?php
}