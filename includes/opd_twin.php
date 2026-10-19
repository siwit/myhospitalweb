<?php

/** Read-only adapter: public page requests never seed or change hourly data. */
function opd_twin_zones(): array
{
    return [
        'insurance' => ['name' => 'ห้องประกันสุขภาพ', 'kind' => 'counter'],
        'register' => ['name' => 'ห้องลงทะเบียนส่งตรวจผู้ป่วย', 'kind' => 'counter'],
        'exam_opd' => ['name' => 'ห้องตรวจ OPD', 'kind' => 'door'],
        'lab' => ['name' => 'ห้องเจาะเลือด LAB', 'kind' => 'counter'],
        'exam_ncd' => ['name' => 'ห้องตรวจ NCD', 'kind' => 'door'],
        'screen_opd' => ['name' => 'จุดซักประวัติ OPD', 'kind' => 'counter'],
        'pharmacy_opd' => ['name' => 'ห้องจ่ายยา OPD', 'kind' => 'counter'],
        'pharmacy_ncd' => ['name' => 'ห้องจ่ายยา NCD', 'kind' => 'counter'],
        'screen_ncd' => ['name' => 'จุดซักประวัติ NCD', 'kind' => 'counter'],
        'cashier' => ['name' => 'ห้องจ่ายเงิน', 'kind' => 'counter'],
    ];
}

function opd_twin_payload(PDO $conn, string $date): array
{
    $zones = opd_twin_zones();
    $rows = [];
    $updatedAt = null;

    // แหล่งหลัก: rush_hour (wide → long)
    try {
        require_once __DIR__ . '/waittime/RushHourAggregator.php';
        RushHourAggregator::ensureTables($conn);
        $stmtRh = $conn->prepare('SELECT *, updated_at AS rh_updated_at FROM rush_hour WHERE service_date = ? ORDER BY hour_slot');
        $stmtRh->execute([$date]);
        foreach ($stmtRh->fetchAll(PDO::FETCH_ASSOC) as $wide) {
            $hour = (int) $wide['hour_slot'];
            if ($hour < 0 || $hour > 23) {
                continue;
            }
            if ($updatedAt === null || ($wide['rh_updated_at'] ?? '') > $updatedAt) {
                $updatedAt = $wide['rh_updated_at'] ?? $updatedAt;
            }
            foreach (array_keys($zones) as $code) {
                if (!array_key_exists("{$code}_waiting", $wide) && $code !== 'lab') {
                    continue;
                }
                if ($code === 'insurance' || $code === 'register') {
                    continue;
                }
                $waitingKey = "{$code}_waiting";
                $avgKey = "{$code}_avg_minutes";
                $svcKey = "{$code}_service_minutes";
                if (!array_key_exists($waitingKey, $wide)) {
                    continue;
                }
                $waiting = max(0, (int) $wide[$waitingKey]);
                $avg = isset($wide[$avgKey]) && $wide[$avgKey] !== null ? max(0, (float) $wide[$avgKey]) : 0.0;
                $svc = isset($wide[$svcKey]) && $wide[$svcKey] !== null ? max(0, (float) $wide[$svcKey]) : 0.0;
                $rows[] = [
                    'hour' => $hour,
                    'slot' => sprintf('%02d:00-%02d:59', $hour, $hour),
                    'zone' => $code,
                    'name' => $zones[$code]['name'],
                    'kind' => $zones[$code]['kind'],
                    'waiting' => $waiting,
                    'avg_wait' => $avg,
                    'avg_service' => $svc,
                    'served' => $waiting,
                    'capacity' => 4,
                ];
            }
        }
    } catch (Throwable $e) {
        // fall through to opd_twin_hourly
    }

    if (!$rows) {
        $stmt = $conn->prepare('
            SELECT hour_slot, zone_code, waiting_count, avg_wait_minutes,
                   avg_service_minutes, served_count, capacity, updated_at
            FROM opd_twin_hourly WHERE service_date = ? ORDER BY hour_slot, zone_code
        ');
        $stmt->execute([$date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = $row['zone_code'];
            $hour = (int) $row['hour_slot'];
            if (!isset($zones[$code]) || $hour < 0 || $hour > 23) {
                continue;
            }
            $rows[] = [
                'hour' => $hour,
                'slot' => sprintf('%02d:00-%02d:59', $hour, $hour),
                'zone' => $code,
                'name' => $zones[$code]['name'],
                'kind' => $zones[$code]['kind'],
                'waiting' => max(0, (int) $row['waiting_count']),
                'avg_wait' => max(0, (float) $row['avg_wait_minutes']),
                'avg_service' => max(0, (float) $row['avg_service_minutes']),
                'served' => max(0, (int) $row['served_count']),
                'capacity' => max(0, (int) $row['capacity']),
            ];
            if ($updatedAt === null || $row['updated_at'] > $updatedAt) {
                $updatedAt = $row['updated_at'];
            }
        }
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $hours = array_values(array_unique(array_column($rows, 'hour')));
    $preferred = $date === $now->format('Y-m-d') ? (int) $now->format('G') : 9;
    $defaultHour = $hours[0] ?? 9;
    foreach ($hours as $hour) {
        if ($hour <= $preferred) {
            $defaultHour = $hour;
        }
    }
    return [
        'date' => $date, 'defaultHour' => $defaultHour, 'rows' => $rows,
        'status' => $rows ? 'stored' : 'empty', 'updatedAt' => $updatedAt,
    ];
}
