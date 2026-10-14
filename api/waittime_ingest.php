<?php
/**
 * รับสถิติ visit นิรนามจาก waittime_api (Node/PM2) แล้ว upsert ลง MySQL เว็บ
 * Header: X-Waittime-Token: <token>
 * Body JSON: { date_from, date_to, rows: [ {...} ] }
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/load_env.php';
require_once dirname(__DIR__) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/includes/waittime/WaitTimeStore.php';

function wt_json_exit(int $code, array $payload): void
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

if ($tokenExpected === '' || !hash_equals($tokenExpected, (string)$tokenGot)) {
    wt_json_exit(401, ['ok' => false, 'error' => 'unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wt_json_exit(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    wt_json_exit(400, ['ok' => false, 'error' => 'invalid_json']);
}

$dateFrom = $data['date_from'] ?? '';
$dateTo = $data['date_to'] ?? '';
$rows = $data['rows'] ?? null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    wt_json_exit(400, ['ok' => false, 'error' => 'invalid_date_range']);
}
if (!is_array($rows)) {
    wt_json_exit(400, ['ok' => false, 'error' => 'rows_required']);
}
if (count($rows) > 2000) {
    wt_json_exit(400, ['ok' => false, 'error' => 'batch_too_large']);
}

WaitTimeStore::ensureTables($conn);
WaitTimeStore::ensureColumns($conn);

$upsert = $conn->prepare("
    INSERT INTO waittime_visit_anon (
      source_hash, hospitalcode, vstdate, vsttime, vst_hour,
      is_appointment_visit, is_er_visit,
      main_depcode, main_department,
      screening_depcode, screening_department,
      doctor_depcode, doctor_department,
      pharmacy_depcode, pharmacy_department,
      wait_history_minutes, history_minutes, wait_doctor_minutes, doctor_minutes,
      wait_drug_minutes, total_opd_minutes,
      lab_item_count, lab_wait_receive_minutes, lab_process_minutes, lab_total_minutes,
      xray_item_count, xray_wait_minutes, xray_process_minutes, xray_total_minutes,
      synced_at
    ) VALUES (
      :source_hash, :hospitalcode, :vstdate, :vsttime, :vst_hour,
      :is_appointment_visit, :is_er_visit,
      :main_depcode, :main_department,
      :screening_depcode, :screening_department,
      :doctor_depcode, :doctor_department,
      :pharmacy_depcode, :pharmacy_department,
      :wait_history_minutes, :history_minutes, :wait_doctor_minutes, :doctor_minutes,
      :wait_drug_minutes, :total_opd_minutes,
      :lab_item_count, :lab_wait_receive_minutes, :lab_process_minutes, :lab_total_minutes,
      :xray_item_count, :xray_wait_minutes, :xray_process_minutes, :xray_total_minutes,
      :synced_at
    )
    ON DUPLICATE KEY UPDATE
      hospitalcode = VALUES(hospitalcode),
      vstdate = VALUES(vstdate),
      vsttime = VALUES(vsttime),
      vst_hour = VALUES(vst_hour),
      is_appointment_visit = VALUES(is_appointment_visit),
      is_er_visit = VALUES(is_er_visit),
      main_depcode = VALUES(main_depcode),
      main_department = VALUES(main_department),
      screening_depcode = VALUES(screening_depcode),
      screening_department = VALUES(screening_department),
      doctor_depcode = VALUES(doctor_depcode),
      doctor_department = VALUES(doctor_department),
      pharmacy_depcode = VALUES(pharmacy_depcode),
      pharmacy_department = VALUES(pharmacy_department),
      wait_history_minutes = VALUES(wait_history_minutes),
      history_minutes = VALUES(history_minutes),
      wait_doctor_minutes = VALUES(wait_doctor_minutes),
      doctor_minutes = VALUES(doctor_minutes),
      wait_drug_minutes = VALUES(wait_drug_minutes),
      total_opd_minutes = VALUES(total_opd_minutes),
      lab_item_count = VALUES(lab_item_count),
      lab_wait_receive_minutes = VALUES(lab_wait_receive_minutes),
      lab_process_minutes = VALUES(lab_process_minutes),
      lab_total_minutes = VALUES(lab_total_minutes),
      xray_item_count = VALUES(xray_item_count),
      xray_wait_minutes = VALUES(xray_wait_minutes),
      xray_process_minutes = VALUES(xray_process_minutes),
      xray_total_minutes = VALUES(xray_total_minutes),
      synced_at = VALUES(synced_at)
");

$log = $conn->prepare("
    INSERT INTO waittime_sync_log (date_from, date_to, row_count, status, message)
    VALUES (:date_from, :date_to, :row_count, :status, :message)
");

$intOrNull = static function ($v) {
    if ($v === null || $v === '') {
        return null;
    }
    return (int)$v;
};
$strOrNull = static function ($v) {
    if ($v === null || $v === '') {
        return null;
    }
    return (string)$v;
};

$syncedAt = date('Y-m-d H:i:s');
$okCount = 0;

try {
    $conn->beginTransaction();
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $hash = $row['source_hash'] ?? '';
        $vstdate = $row['vstdate'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/i', $hash) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vstdate)) {
            continue;
        }
        // กันข้อมูลตัวตนหลุดมา
        unset($row['hn'], $row['vn'], $row['HN'], $row['VN']);

        $upsert->execute([
            'source_hash' => strtolower($hash),
            'hospitalcode' => $strOrNull($row['hospitalcode'] ?? null),
            'vstdate' => $vstdate,
            'vsttime' => $strOrNull($row['vsttime'] ?? null),
            'vst_hour' => $intOrNull($row['vst_hour'] ?? null),
            'is_appointment_visit' => !empty($row['is_appointment_visit']) ? 1 : 0,
            'is_er_visit' => !empty($row['is_er_visit']) ? 1 : 0,
            'main_depcode' => $strOrNull($row['main_depcode'] ?? null),
            'main_department' => $strOrNull($row['main_department'] ?? null),
            'screening_depcode' => $strOrNull($row['screening_depcode'] ?? null),
            'screening_department' => $strOrNull($row['screening_department'] ?? null),
            'doctor_depcode' => $strOrNull($row['doctor_depcode'] ?? null),
            'doctor_department' => $strOrNull($row['doctor_department'] ?? null),
            'pharmacy_depcode' => $strOrNull($row['pharmacy_depcode'] ?? null),
            'pharmacy_department' => $strOrNull($row['pharmacy_department'] ?? null),
            'wait_history_minutes' => $intOrNull($row['wait_history_minutes'] ?? null),
            'history_minutes' => $intOrNull($row['history_minutes'] ?? null),
            'wait_doctor_minutes' => $intOrNull($row['wait_doctor_minutes'] ?? null),
            'doctor_minutes' => $intOrNull($row['doctor_minutes'] ?? null),
            'wait_drug_minutes' => $intOrNull($row['wait_drug_minutes'] ?? null),
            'total_opd_minutes' => $intOrNull($row['total_opd_minutes'] ?? null),
            'lab_item_count' => $intOrNull($row['lab_item_count'] ?? null),
            'lab_wait_receive_minutes' => $intOrNull($row['lab_wait_receive_minutes'] ?? null),
            'lab_process_minutes' => $intOrNull($row['lab_process_minutes'] ?? null),
            'lab_total_minutes' => $intOrNull($row['lab_total_minutes'] ?? null),
            'xray_item_count' => $intOrNull($row['xray_item_count'] ?? null),
            'xray_wait_minutes' => $intOrNull($row['xray_wait_minutes'] ?? null),
            'xray_process_minutes' => $intOrNull($row['xray_process_minutes'] ?? null),
            'xray_total_minutes' => $intOrNull($row['xray_total_minutes'] ?? null),
            'synced_at' => $syncedAt,
        ]);
        $okCount++;
    }

    $log->execute([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'row_count' => $okCount,
        'status' => 'ok',
        'message' => 'ingested',
    ]);
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    try {
        $log->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'row_count' => 0,
            'status' => 'error',
            'message' => substr($e->getMessage(), 0, 480),
        ]);
    } catch (Throwable $ignored) {
    }
    wt_json_exit(500, ['ok' => false, 'error' => 'ingest_failed', 'message' => $e->getMessage()]);
}

wt_json_exit(200, [
    'ok' => true,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'upserted' => $okCount,
    'synced_at' => $syncedAt,
]);
