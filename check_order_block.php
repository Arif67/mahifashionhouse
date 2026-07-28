<?php
require_once 'Config.php';
header('Content-Type: application/json; charset=utf-8');

// ২৪ ঘণ্টার মধ্যে একই আইপি বা ডিভাইস থেকে অর্ডার আছে কিনা চেক
$deviceToken = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['device_token'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$blocked = false;

try {
    $stmt = getDB()->prepare(
        "SELECT id FROM orders
         WHERE created_at >= (NOW() - INTERVAL 24 HOUR)
           AND status != 'cancelled'
           AND (
                ip_address = ?
                OR (device_token IS NOT NULL AND device_token != '' AND device_token = ?)
           )
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$ip, $deviceToken]);
    $blocked = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blocked = false;
}

echo json_encode(['blocked' => $blocked]);
exit;