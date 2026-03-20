<?php
// admin/ajax_approve_booking.php
session_start();
header('Content-Type: application/json');

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = $_POST['appointment_id'] ?? 0;

    if ($appointmentId > 0) {
        try {
            $pdo = db();
            $sql = "UPDATE vac_appointments SET status = 'confirmed' WHERE id = :id AND status = 'booked'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $appointmentId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Booking approved.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No rows updated. It may have been already updated or cancelled.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}