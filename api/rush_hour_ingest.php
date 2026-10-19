<?php
/**
 * รับแถว rush_hour ที่สรุปจาก HIS ฝั่ง waittime_api แล้ว upsert ตรงเข้าตาราง rush_hour
 * ไม่ใช้ RushHourAggregator
 *
 * Header: X-Waittime-Token
 * Body JSON: {
 *   date_from, date_to,
 *   rows: [ { service_date, hour_slot, slot_label?, exam_opd_waiting, ... }, ... ]
 * }
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/load_env.php';
require_once dirname(__DIR__) . '/config/db_connect.php';

function rhi_json_exit(int $code, array $payload): void
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
    rhi_json_exit(401, ['ok' => false, 'error' => 'unauthorized']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rhi_json_exit(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    rhi_json_exit(400, ['ok' => false, 'error' => 'invalid_json']);
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
$dateFrom = $data['date_from'] ?? $today;
$dateTo = $data['date_to'] ?? $today;
$rows = $data['rows'] ?? null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    rhi_json_exit(400, ['ok' => false, 'error' => 'invalid_date_range']);
}
if ($dateFrom > $dateTo) {
    rhi_json_exit(400, ['ok' => false, 'error' => 'date_from_after_date_to']);
}
if (!is_array($rows)) {
    rhi_json_exit(400, ['ok' => false, 'error' => 'rows_required']);
}

$zoneKeys = [
    'exam_opd', 'screen_opd', 'pharmacy_opd', 'pharmacy_ncd',
    'cashier', 'screen_ncd', 'exam_ncd', 'lab',
];

$cols = ['service_date', 'hour_slot', 'slot_label'];
foreach ($zoneKeys as $zone) {
    $cols[] = "{$zone}_waiting";
    $cols[] = "{$zone}_avg_minutes";
    $cols[] = "{$zone}_service_minutes";
}
$cols[] = 'avg_total_service_minutes';
$cols[] = 'total_recipients';

// ensure table exists (schema file)
$sqlFile = dirname(__DIR__) . '/database/rush_hour.sql';
if (is_readable($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $stmtSql) {
        $lines = preg_split('/\R/', $stmtSql) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $kept[] = $line;
        }
        $stmtSql = trim(implode("\n", $kept));
        if ($stmtSql === '') {
            continue;
        }
        try {
            $conn->exec($stmtSql);
        } catch (PDOException $e) {
            // exists
        }
    }
}

$placeholders = array_map(static fn ($c) => ':' . $c, $cols);
$updates = [];
foreach ($cols as $c) {
    if (in_array($c, ['service_date', 'hour_slot'], true)) {
        continue;
    }
    $updates[] = "{$c} = VALUES({$c})";
}
$updates[] = 'updated_at = CURRENT_TIMESTAMP';

$upsertSql = 'INSERT INTO rush_hour (' . implode(', ', $cols) . ')
    VALUES (' . implode(', ', $placeholders) . ')
    ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
$stmt = $conn->prepare($upsertSql);

function rhi_float_or_null($v): ?float
{
    if ($v === null || $v === '') {
        return null;
    }
    return (float) $v;
}

function rhi_slot_label(int $hour): string
{
    return sprintf('%02d:00-%02d:59', $hour, $hour);
}

$okCount = 0;
try {
    $conn->beginTransaction();
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $serviceDate = $row['service_date'] ?? null;
        $hourSlot = isset($row['hour_slot']) ? (int) $row['hour_slot'] : -1;
        if (!is_string($serviceDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate)) {
            continue;
        }
        if ($hourSlot < 0 || $hourSlot > 23) {
            continue;
        }
        if ($serviceDate < $dateFrom || $serviceDate > $dateTo) {
            continue;
        }

        $params = [
            'service_date' => $serviceDate,
            'hour_slot' => $hourSlot,
            'slot_label' => $row['slot_label'] ?? rhi_slot_label($hourSlot),
            'avg_total_service_minutes' => rhi_float_or_null($row['avg_total_service_minutes'] ?? null),
            'total_recipients' => max(0, (int) ($row['total_recipients'] ?? 0)),
        ];
        foreach ($zoneKeys as $zone) {
            $params["{$zone}_waiting"] = max(0, (int) ($row["{$zone}_waiting"] ?? 0));
            $params["{$zone}_avg_minutes"] = rhi_float_or_null($row["{$zone}_avg_minutes"] ?? null);
            $params["{$zone}_service_minutes"] = rhi_float_or_null($row["{$zone}_service_minutes"] ?? null);
        }
        $stmt->execute($params);
        $okCount++;
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('rush_hour_ingest: ' . $e->getMessage());
    rhi_json_exit(500, ['ok' => false, 'error' => 'ingest_failed', 'message' => $e->getMessage()]);
}

rhi_json_exit(200, [
    'ok' => true,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'rows_upserted' => $okCount,
    'source' => 'his_direct',
]);
