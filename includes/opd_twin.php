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
        'history_opd' => ['name' => 'จุดซักประวัติ OPD', 'kind' => 'counter'],
        'pharmacy_opd' => ['name' => 'ห้องจ่ายยา OPD', 'kind' => 'counter'],
        'pharmacy_ncd' => ['name' => 'ห้องจ่ายยา NCD', 'kind' => 'counter'],
        'history_ncd' => ['name' => 'จุดซักประวัติ NCD', 'kind' => 'counter'],
        'cashier' => ['name' => 'ห้องจ่ายเงิน', 'kind' => 'counter'],
    ];
}

function opd_twin_payload(PDO $conn, string $date): array
{
    $stmt = $conn->prepare('
        SELECT hour_slot, zone_code, waiting_count, avg_wait_minutes,
               avg_service_minutes, served_count, capacity, updated_at
        FROM opd_twin_hourly WHERE service_date = ? ORDER BY hour_slot, zone_code
    ');
    $stmt->execute([$date]);
    $zones = opd_twin_zones();
    $rows = [];
    $updatedAt = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = $row['zone_code'];
        $hour = (int) $row['hour_slot'];
        if (!isset($zones[$code]) || $hour < 0 || $hour > 23) continue;
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
        if ($updatedAt === null || $row['updated_at'] > $updatedAt) $updatedAt = $row['updated_at'];
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $hours = array_values(array_unique(array_column($rows, 'hour')));
    $preferred = $date === $now->format('Y-m-d') ? (int) $now->format('G') : 9;
    $defaultHour = $hours[0] ?? 9;
    foreach ($hours as $hour) {
        if ($hour <= $preferred) $defaultHour = $hour;
    }
    return [
        'date' => $date, 'defaultHour' => $defaultHour, 'rows' => $rows,
        'status' => $rows ? 'stored' : 'empty', 'updatedAt' => $updatedAt,
    ];
}
