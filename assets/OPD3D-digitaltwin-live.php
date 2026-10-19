<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/waittime/RushHourAggregator.php';

$date = $_GET['twin_date'] ?? date('Y-m-d');
if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
    || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
    $date = date('Y-m-d');
}
$liveRows = [];
$latestHour = null;
try {
    RushHourAggregator::ensureTables($conn);
    $liveRows = RushHourAggregator::fetchTwinRows($conn, $date);
    foreach ($liveRows as $row) {
        $latestHour = $latestHour === null ? (int) $row['hour'] : max($latestHour, (int) $row['hour']);
    }
    // ถ้า rush_hour ยังว่าง ให้ fallback อ่าน opd_twin_hourly เดิม
    if (!$liveRows) {
        $stmt = $conn->prepare('SELECT hour_slot, zone_code, waiting_count, avg_wait_minutes, avg_service_minutes, served_count, capacity
            FROM opd_twin_hourly WHERE service_date = ? ORDER BY hour_slot, zone_code');
        $stmt->execute([$date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $liveRows[] = [
                'hour' => (int) $row['hour_slot'], 'zone' => $row['zone_code'],
                'waiting' => (int) $row['waiting_count'], 'avg_wait' => (float) $row['avg_wait_minutes'],
                'avg_service' => (float) $row['avg_service_minutes'],
                'served' => (int) $row['served_count'], 'capacity' => (int) $row['capacity'],
            ];
            $latestHour = $latestHour === null ? (int) $row['hour_slot'] : max($latestHour, (int) $row['hour_slot']);
        }
    }
} catch (Throwable $error) {
    error_log('OPD live twin: unable to read rush_hour/hourly data: ' . $error->getMessage());
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$source = file_get_contents(__DIR__ . '/OPD3D-digitaltwin2.html');
$injection = '<script>window.__OPD_TWIN_DATE__=' . json_encode($date, JSON_UNESCAPED_UNICODE)
    . ';window.__OPD_TWIN_LATEST_HOUR__=' . json_encode($latestHour)
    . ';window.__OPD_TWIN_ROWS__=' . json_encode($liveRows, JSON_UNESCAPED_UNICODE)
    . ';window.__OPD_TWIN_UI_VER__="staff-white-v1";</script>';
echo str_replace('</head>', $injection . '</head>', $source ?: '');
