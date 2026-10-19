<?php
/**
 * สรุป waittime_visit_anon → rush_hour รายชั่วโมง
 * Header: X-Waittime-Token (เดียวกับ ingest)
 * Body JSON (optional): { date_from, date_to } — ค่าว่าง = วันนี้
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/load_env.php';
require_once dirname(__DIR__) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/includes/waittime/RushHourAggregator.php';

function rh_json_exit(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenExpected = getenv('WAITTIME_INGEST_TOKEN') ?: '';
$tokenGot = $_SERVER['HTTP_X_WAITTIME_TOKEN'] ?? '';
if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $tokenGot = trim($m[1]);
}

if ($tokenExpected === '' || !hash_equals($tokenExpected, (string) $tokenGot)) {
    rh_json_exit(401, ['ok' => false, 'error' => 'unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rh_json_exit(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    rh_json_exit(400, ['ok' => false, 'error' => 'invalid_json']);
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
$dateFrom = $data['date_from'] ?? $today;
$dateTo = $data['date_to'] ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    rh_json_exit(400, ['ok' => false, 'error' => 'invalid_date_range']);
}
if ($dateFrom > $dateTo) {
    rh_json_exit(400, ['ok' => false, 'error' => 'date_from_after_date_to']);
}

try {
    $result = RushHourAggregator::rebuild($conn, $dateFrom, $dateTo);
    rh_json_exit(200, [
        'ok' => true,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'dates' => $result['dates'],
        'hours' => $result['hours'],
        'rows_upserted' => $result['rows_upserted'],
    ]);
} catch (Throwable $e) {
    error_log('rush_hour_rebuild: ' . $e->getMessage());
    rh_json_exit(500, ['ok' => false, 'error' => 'rebuild_failed', 'message' => $e->getMessage()]);
}
