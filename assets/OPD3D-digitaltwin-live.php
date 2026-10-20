<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/waittime/RushHourAggregator.php';

$tz = new DateTimeZone('Asia/Bangkok');
$todayBkk = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$explicitDate = isset($_GET['twin_date']);
$date = $_GET['twin_date'] ?? $todayBkk;
if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
    || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
    $date = $todayBkk;
    $explicitDate = false;
}
$liveRows = [];
$latestHour = null;

/** รวม waiting ทั้งวันจากแถว twin long */
$twinDayTotal = static function (array $rows): int {
    $sum = 0;
    foreach ($rows as $row) {
        $sum += (int) ($row['waiting'] ?? 0);
    }
    return $sum;
};

try {
    RushHourAggregator::ensureTables($conn);
    $liveRows = RushHourAggregator::fetchTwinRows($conn, $date);

    // ไม่ได้เลือกวันเอง และวันนี้ว่าง → ใช้วันล่าสุดที่มีข้อมูลจริง
    if (!$explicitDate && $twinDayTotal($liveRows) === 0) {
        $fallbackDate = $conn->query("
            SELECT service_date
            FROM rush_hour
            WHERE total_recipients > 0
            ORDER BY service_date DESC
            LIMIT 1
        ")->fetchColumn();
        if (is_string($fallbackDate) && $fallbackDate !== '' && $fallbackDate !== $date) {
            $date = $fallbackDate;
            $liveRows = RushHourAggregator::fetchTwinRows($conn, $date);
        }
    }

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

/** สรุปการ์ด KPI ทั้งวัน: ยอดไม่ซ้ำ / รอเฉลี่ย / ความแออัดจาก capacity รวม */
$dayStats = [
    'recipients' => 0,
    'wait' => 0.0,
    'density' => 0.0,
    'capacity' => 0,
];
try {
    RushHourAggregator::ensureTables($conn);
    $capacities = RushHourAggregator::loadCapacities($conn);
    $capacityTotal = 0;
    foreach (RushHourAggregator::ZONE_KEYS as $zone) {
        $capacityTotal += RushHourAggregator::capacityFor($capacities, $zone);
    }
    $dayStats['capacity'] = $capacityTotal;

    // ยอดผู้ป่วยไม่ซ้ำทั้งวัน จาก waittime_visit_anon
    try {
        $uniqStmt = $conn->prepare('SELECT COUNT(*) FROM waittime_visit_anon WHERE vstdate = ?');
        $uniqStmt->execute([$date]);
        $dayStats['recipients'] = (int) $uniqStmt->fetchColumn();
    } catch (Throwable $uniqError) {
        error_log('OPD live twin: unique recipients query failed: ' . $uniqError->getMessage());
    }

    $waitWeight = 0.0;
    $waitSum = 0.0;
    $hourWaiting = [];
    $hourRecipients = [];
    foreach ($liveRows as $row) {
        $hour = (int) ($row['hour'] ?? -1);
        $waiting = (int) ($row['waiting'] ?? 0);
        $avgWait = (float) ($row['avg_wait'] ?? 0);
        if ($waiting > 0 && $avgWait >= 0) {
            $waitSum += $avgWait * $waiting;
            $waitWeight += $waiting;
        }
        if ($hour >= 0) {
            $hourWaiting[$hour] = ($hourWaiting[$hour] ?? 0) + $waiting;
            $tr = $row['total_recipients'] ?? null;
            if ($tr !== null && is_numeric($tr)) {
                $hourRecipients[$hour] = max($hourRecipients[$hour] ?? 0, (int) $tr);
            }
        }
    }

    // สูตร C: ใช้ avg_journey_minutes ณ สิ้นชั่วโมงอ้างอิง (คาบที่ยิงแล้ว / วันย้อนหลัง = ชั่วโมงล่าสุด)
    $refHour = null;
    $nowBkk = new DateTimeImmutable('now', $tz);
    $isToday = ($date === $todayBkk);
    if ($isToday) {
        $wallHour = (int) $nowBkk->format('G');
        if ($wallHour >= 7 && $wallHour <= 16) {
            $refHour = max(6, min(15, $wallHour - 1));
        } elseif ($wallHour > 16) {
            $refHour = 15;
        }
    } else {
        $refHour = $latestHour;
    }
    $journeyWait = null;
    if ($refHour !== null) {
        try {
            $jStmt = $conn->prepare('
                SELECT avg_journey_minutes
                FROM rush_hour
                WHERE service_date = ? AND hour_slot = ?
                LIMIT 1
            ');
            $jStmt->execute([$date, $refHour]);
            $jVal = $jStmt->fetchColumn();
            if ($jVal !== false && $jVal !== null && $jVal !== '') {
                $journeyWait = (float) $jVal;
            }
        } catch (Throwable $journeyError) {
            error_log('OPD live twin: journey wait query failed: ' . $journeyError->getMessage());
        }
    }
    if ($journeyWait === null && $refHour !== null && $latestHour !== null && $refHour !== $latestHour) {
        // ยังไม่มียิงคาบอ้างอิง → ใช้ชั่วโมงล่าสุดที่มีใน DB
        try {
            $jStmt = $conn->prepare('
                SELECT avg_journey_minutes
                FROM rush_hour
                WHERE service_date = ? AND hour_slot = ?
                LIMIT 1
            ');
            $jStmt->execute([$date, $latestHour]);
            $jVal = $jStmt->fetchColumn();
            if ($jVal !== false && $jVal !== null && $jVal !== '') {
                $journeyWait = (float) $jVal;
            }
        } catch (Throwable $journeyError) {
            // ignore
        }
    }
    $dayStats['wait'] = $journeyWait !== null
        ? round($journeyWait, 1)
        : ($waitWeight > 0 ? round($waitSum / $waitWeight, 1) : 0.0);
    $dayStats['wait_mode'] = $journeyWait !== null ? 'journey_c' : 'zone_fallback';
    $dayStats['wait_ref_hour'] = $refHour;

    $activeHourWaitings = array_values(array_filter($hourWaiting, static fn(int $n): bool => $n > 0));
    $avgHourWaiting = $activeHourWaitings
        ? array_sum($activeHourWaitings) / count($activeHourWaitings)
        : 0.0;
    $dayStats['density'] = $capacityTotal > 0
        ? round($avgHourWaiting / $capacityTotal * 100, 1)
        : 0.0;

    // ถ้ายังไม่มี anon ของวันนั้น แต่มี rush_hour → ใช้ยอดสูงสุดรายชั่วโมงเป็นค่าสำรอง
    if ($dayStats['recipients'] === 0 && $hourRecipients) {
        $dayStats['recipients'] = max($hourRecipients);
    }
} catch (Throwable $dayError) {
    error_log('OPD live twin: day stats failed: ' . $dayError->getMessage());
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$source = file_get_contents(__DIR__ . '/OPD3D-digitaltwin2.html');
$injection = '<script>window.__OPD_TWIN_DATE__=' . json_encode($date, JSON_UNESCAPED_UNICODE)
    . ';window.__OPD_TWIN_LATEST_HOUR__=' . json_encode($latestHour)
    . ';window.__OPD_TWIN_ROWS__=' . json_encode($liveRows, JSON_UNESCAPED_UNICODE)
    . ';window.__OPD_TWIN_DAY_STATS__=' . json_encode($dayStats, JSON_UNESCAPED_UNICODE)
    . ';window.__OPD_TWIN_UI_VER__="wait-journey-c-v1";</script>';
echo str_replace('</head>', $injection . '</head>', $source ?: '');
