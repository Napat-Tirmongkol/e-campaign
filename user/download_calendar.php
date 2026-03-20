<?php
declare(strict_types=1);

/**
 * ดาวน์โหลดไฟล์ .ics (iCalendar) เพื่อ Save ลง Calendar
 * ใช้ข้อมูลการจองล่าสุดจาก session (evax_last_booking)
 */

session_start();

$booking = $_SESSION['evax_last_booking'] ?? null;
if (!is_array($booking)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'No booking data found.';
  exit;
}

$fullName = (string)($_SESSION['evax_full_name'] ?? 'E-Vax Patient');
$appointmentId = isset($booking['appointment_id']) ? (int)$booking['appointment_id'] : 0;
$slotDate = (string)($booking['slot_date'] ?? '');
$startTime = (string)($booking['start_time'] ?? '');
$endTime = (string)($booking['end_time'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Invalid slot date.';
  exit;
}

// ถ้าเวลาเป็น HH:MM:SS ให้ใช้ได้เลย
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
  // fallback: สร้างเป็น all-day event ถ้าเวลาไม่ถูกต้อง
  $startTime = '09:00:00';
  $endTime = '10:00:00';
}

// ตั้ง timezone เป็น Asia/Bangkok (แนะนำให้ตรงกับระบบไทย)
$tz = 'Asia/Bangkok';

$dtStart = new DateTime($slotDate . ' ' . $startTime, new DateTimeZone($tz));
$dtEnd = new DateTime($slotDate . ' ' . $endTime, new DateTimeZone($tz));

// สร้าง UID
$uid = ($appointmentId > 0 ? "evax-{$appointmentId}" : ('evax-' . bin2hex(random_bytes(6)))) . '@e-vax.local';

// ฟังก์ชัน escape สำหรับ iCalendar
$esc = static function (string $s): string {
  $s = str_replace("\\", "\\\\", $s);
  $s = str_replace("\r\n", "\n", $s);
  $s = str_replace("\n", "\\n", $s);
  $s = str_replace(",", "\\,", $s);
  $s = str_replace(";", "\\;", $s);
  return $s;
};

$summary = $esc('E-Vax Vaccination Appointment');
$description = $esc("Patient: {$fullName}\nBooking ID: " . ($appointmentId > 0 ? $appointmentId : '—'));
$location = $esc('Vax Center, Hospital 1 (Building B, Floor 2)');

// Format ตาม RFC 5545
$ics = implode("\r\n", [
  'BEGIN:VCALENDAR',
  'VERSION:2.0',
  'PRODID:-//E-Vax//Booking//EN',
  'CALSCALE:GREGORIAN',
  'METHOD:PUBLISH',
  'BEGIN:VEVENT',
  'UID:' . $uid,
  'DTSTAMP:' . gmdate('Ymd\THis\Z'),
  'DTSTART;TZID=' . $tz . ':' . $dtStart->format('Ymd\THis'),
  'DTEND;TZID=' . $tz . ':' . $dtEnd->format('Ymd\THis'),
  'SUMMARY:' . $summary,
  'DESCRIPTION:' . $description,
  'LOCATION:' . $location,
  'END:VEVENT',
  'END:VCALENDAR',
  '',
]);

$filename = 'E-Vax-' . ($appointmentId > 0 ? $appointmentId : date('YmdHis')) . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo $ics;
exit;

