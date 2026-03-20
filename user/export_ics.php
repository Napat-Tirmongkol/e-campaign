<?php
declare(strict_types=1);

session_start();

$booking = $_SESSION['evax_last_booking'] ?? null;

if (!is_array($booking)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'No booking data found.';
  exit;
}

$slotDate = (string)($booking['slot_date'] ?? '');
$startTime = (string)($booking['start_time'] ?? '');
$endTime = (string)($booking['end_time'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Invalid booking date.';
  exit;
}

// ถ้าเวลาไม่ตรงรูปแบบ ให้ fallback เป็นช่วง 09:00-10:00
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) {
  $startTime = '09:00:00';
}
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
  $endTime = '10:00:00';
}

// แปลงเป็น DateTime แล้วออกมาเป็นรูปแบบ Ymd\THis
$start = new DateTime($slotDate . ' ' . $startTime);
$end = new DateTime($slotDate . ' ' . $endTime);

$dtStart = $start->format('Ymd\THis');
$dtEnd = $end->format('Ymd\THis');

$uid = 'evax-' . ($booking['appointment_id'] ?? uniqid()) . '@e-vax.local';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="vaccine_appointment.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//E-Vax//Vaccine Appointment//TH\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "BEGIN:VEVENT\r\n";
echo "UID:{$uid}\r\n";
echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
echo "DTSTART:{$dtStart}\r\n";
echo "DTEND:{$dtEnd}\r\n";
echo "SUMMARY:" . "นัดหมายฉีดวัคซีน E-Vax" . "\r\n";
echo "END:VEVENT\r\n";
echo "END:VCALENDAR\r\n";

exit;

