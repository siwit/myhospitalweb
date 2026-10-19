<?php
/**
 * สรุป waittime_visit_anon → rush_hour (รายชั่วโมง)
 * และ mirror ไป opd_twin_hourly สำหรับ Digital Twin
 */
class RushHourAggregator
{
    /** @var array<string, array{deps: string[], avg_col: ?string, twin_capacity: int}> */
    public const ZONES = [
        'exam_opd' => [
            'deps' => ['003', '004', '005', '029', '042'],
            'avg_col' => 'doctor_minutes',
            'twin_capacity' => 8,
        ],
        'history_opd' => [
            'deps' => ['002', '022', '024'],
            'avg_col' => 'on_screen_minutes',
            'twin_capacity' => 6,
        ],
        'pharmacy_opd' => [
            'deps' => ['007'],
            'avg_col' => 'wait_drug_minutes',
            'twin_capacity' => 4,
        ],
        'pharmacy_ncd' => [
            'deps' => ['098'],
            'avg_col' => 'wait_drug_minutes',
            'twin_capacity' => 3,
        ],
        'cashier' => [
            'deps' => ['006'],
            'avg_col' => null,
            'twin_capacity' => 3,
        ],
        'history_ncd' => [
            'deps' => ['023'],
            'avg_col' => 'on_screen_minutes',
            'twin_capacity' => 4,
        ],
        'exam_ncd' => [
            'deps' => ['062'],
            'avg_col' => 'doctor_minutes',
            'twin_capacity' => 4,
        ],
    ];

    /** cur_dep ทั้งหมดที่นับใน total_recipients (ไม่รวม LAB พิเศษ) */
    public const TOTAL_DEP_CODES = [
        '003', '004', '005', '029', '042',
        '002', '022', '024',
        '007', '098', '006', '023', '062',
    ];

    public const HOUR_FROM = 6;
    public const HOUR_TO = 15;

    public static function ensureTables(PDO $conn): void
    {
        $files = [
            dirname(__DIR__, 2) . '/database/rush_hour.sql',
            dirname(__DIR__, 2) . '/database/opd_twin_hourly.sql',
        ];
        foreach ($files as $sqlFile) {
            if (!is_readable($sqlFile)) {
                continue;
            }
            $sql = file_get_contents($sqlFile);
            foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $stmtSql) {
                if ($stmtSql === '' || strpos($stmtSql, '--') === 0) {
                    continue;
                }
                try {
                    $conn->exec($stmtSql);
                } catch (PDOException $e) {
                    // already exists
                }
            }
        }
    }

    public static function slotLabel(int $hour): string
    {
        return sprintf('%02d:00-%02d:59', $hour, $hour);
    }

    /**
     * สรุปช่วงวันที่แล้ว upsert ลง rush_hour (+ mirror opd_twin_hourly)
     *
     * @return array{dates:int, hours:int, rows_upserted:int}
     */
    public static function rebuild(PDO $conn, string $dateFrom, string $dateTo): array
    {
        self::ensureTables($conn);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            throw new InvalidArgumentException('invalid_date_range');
        }

        $agg = self::aggregateFromAnon($conn, $dateFrom, $dateTo);
        $upserted = self::upsertRushHour($conn, $agg);
        self::mirrorToTwinHourly($conn, $agg);

        $dates = [];
        foreach (array_keys($agg) as $key) {
            $dates[substr($key, 0, 10)] = true;
        }

        return [
            'dates' => count($dates),
            'hours' => count($agg),
            'rows_upserted' => $upserted,
        ];
    }

    /**
     * @return array<string, array<string, mixed>> keyed by "Y-m-d|H"
     */
    private static function aggregateFromAnon(PDO $conn, string $dateFrom, string $dateTo): array
    {
        $depList = "'" . implode("','", self::TOTAL_DEP_CODES) . "'";

        $selectParts = [
            'vstdate AS service_date',
            'vst_hour AS hour_slot',
            'COUNT(*) AS raw_hour_visits',
        ];

        foreach (self::ZONES as $zone => $meta) {
            $inList = "'" . implode("','", $meta['deps']) . "'";
            $selectParts[] = "SUM(CASE WHEN cur_depcode IN ({$inList}) THEN 1 ELSE 0 END) AS {$zone}_waiting";
            if ($meta['avg_col'] !== null) {
                $col = $meta['avg_col'];
                $selectParts[] = "ROUND(AVG(CASE
                    WHEN cur_depcode IN ({$inList}) AND {$col} BETWEEN 0 AND 480 THEN {$col}
                END), 1) AS {$zone}_avg_minutes";
            } else {
                $selectParts[] = "NULL AS {$zone}_avg_minutes";
            }
        }

        // LAB: มี order แต่ยังไม่มี report — จัดชั่วโมงตาม HOUR(lab_order_time) แยก query แล้ว merge
        $selectParts[] = "SUM(CASE WHEN cur_depcode IN ({$depList}) THEN 1 ELSE 0 END) AS total_recipients";
        $selectParts[] = "ROUND(AVG(CASE
            WHEN cur_depcode IN ({$depList}) AND total_opd_minutes BETWEEN 0 AND 720 THEN total_opd_minutes
        END), 1) AS avg_total_service_minutes";

        $sql = '
            SELECT ' . implode(",\n              ", $selectParts) . '
            FROM waittime_visit_anon
            WHERE vstdate BETWEEN :date_from AND :date_to
              AND vst_hour BETWEEN :hour_from AND :hour_to
            GROUP BY vstdate, vst_hour
        ';

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'hour_from' => self::HOUR_FROM,
            'hour_to' => self::HOUR_TO,
        ]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = $row['service_date'];
            $hour = (int) $row['hour_slot'];
            $key = $date . '|' . $hour;
            $out[$key] = self::normalizeAggRow($date, $hour, $row);
        }

        // LAB ตามชั่วโมงของ lab_order_time
        $labSql = '
            SELECT
              DATE(lab_order_time) AS service_date,
              HOUR(lab_order_time) AS hour_slot,
              COUNT(*) AS lab_waiting,
              ROUND(AVG(CASE
                WHEN TIMESTAMPDIFF(MINUTE, lab_order_time, COALESCE(lab_report_time, NOW())) BETWEEN 0 AND 720
                THEN TIMESTAMPDIFF(MINUTE, lab_order_time, COALESCE(lab_report_time, NOW()))
              END), 1) AS lab_avg_minutes
            FROM waittime_visit_anon
            WHERE lab_order_time IS NOT NULL
              AND lab_report_time IS NULL
              AND DATE(lab_order_time) BETWEEN :date_from AND :date_to
              AND HOUR(lab_order_time) BETWEEN :hour_from AND :hour_to
            GROUP BY DATE(lab_order_time), HOUR(lab_order_time)
        ';
        $labStmt = $conn->prepare($labSql);
        $labStmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'hour_from' => self::HOUR_FROM,
            'hour_to' => self::HOUR_TO,
        ]);
        foreach ($labStmt->fetchAll(PDO::FETCH_ASSOC) as $labRow) {
            $date = $labRow['service_date'];
            $hour = (int) $labRow['hour_slot'];
            $key = $date . '|' . $hour;
            if (!isset($out[$key])) {
                $out[$key] = self::emptyAggRow($date, $hour);
            }
            $out[$key]['lab_waiting'] = (int) $labRow['lab_waiting'];
            $out[$key]['lab_avg_minutes'] = $labRow['lab_avg_minutes'] !== null
                ? (float) $labRow['lab_avg_minutes']
                : null;
        }

        // เติมชั่วโมงว่าง 6–15 สำหรับทุกวันในช่วง (ให้ twin มีการ์ดต่อเนื่อง)
        $from = new DateTimeImmutable($dateFrom);
        $to = new DateTimeImmutable($dateTo);
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            for ($h = self::HOUR_FROM; $h <= self::HOUR_TO; $h++) {
                $key = $date . '|' . $h;
                if (!isset($out[$key])) {
                    $out[$key] = self::emptyAggRow($date, $h);
                }
            }
        }

        ksort($out);
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeAggRow(string $date, int $hour, array $row): array
    {
        $base = self::emptyAggRow($date, $hour);
        foreach (self::ZONES as $zone => $_meta) {
            $base["{$zone}_waiting"] = (int) ($row["{$zone}_waiting"] ?? 0);
            $avg = $row["{$zone}_avg_minutes"] ?? null;
            $base["{$zone}_avg_minutes"] = $avg !== null && $avg !== '' ? (float) $avg : null;
        }
        $base['lab_waiting'] = (int) ($row['lab_waiting'] ?? 0);
        $labAvg = $row['lab_avg_minutes'] ?? null;
        $base['lab_avg_minutes'] = $labAvg !== null && $labAvg !== '' ? (float) $labAvg : null;
        $base['total_recipients'] = (int) ($row['total_recipients'] ?? 0);
        $tot = $row['avg_total_service_minutes'] ?? null;
        $base['avg_total_service_minutes'] = $tot !== null && $tot !== '' ? (float) $tot : null;
        return $base;
    }

    /** @return array<string, mixed> */
    private static function emptyAggRow(string $date, int $hour): array
    {
        $row = [
            'service_date' => $date,
            'hour_slot' => $hour,
            'slot_label' => self::slotLabel($hour),
            'lab_waiting' => 0,
            'lab_avg_minutes' => null,
            'avg_total_service_minutes' => null,
            'total_recipients' => 0,
        ];
        foreach (array_keys(self::ZONES) as $zone) {
            $row["{$zone}_waiting"] = 0;
            $row["{$zone}_avg_minutes"] = null;
        }
        return $row;
    }

    /**
     * @param array<string, array<string, mixed>> $agg
     */
    private static function upsertRushHour(PDO $conn, array $agg): int
    {
        $sql = '
            INSERT INTO rush_hour (
              service_date, hour_slot, slot_label,
              exam_opd_waiting, exam_opd_avg_minutes,
              history_opd_waiting, history_opd_avg_minutes,
              pharmacy_opd_waiting, pharmacy_opd_avg_minutes,
              pharmacy_ncd_waiting, pharmacy_ncd_avg_minutes,
              cashier_waiting, cashier_avg_minutes,
              history_ncd_waiting, history_ncd_avg_minutes,
              exam_ncd_waiting, exam_ncd_avg_minutes,
              lab_waiting, lab_avg_minutes,
              avg_total_service_minutes, total_recipients
            ) VALUES (
              :service_date, :hour_slot, :slot_label,
              :exam_opd_waiting, :exam_opd_avg_minutes,
              :history_opd_waiting, :history_opd_avg_minutes,
              :pharmacy_opd_waiting, :pharmacy_opd_avg_minutes,
              :pharmacy_ncd_waiting, :pharmacy_ncd_avg_minutes,
              :cashier_waiting, :cashier_avg_minutes,
              :history_ncd_waiting, :history_ncd_avg_minutes,
              :exam_ncd_waiting, :exam_ncd_avg_minutes,
              :lab_waiting, :lab_avg_minutes,
              :avg_total_service_minutes, :total_recipients
            )
            ON DUPLICATE KEY UPDATE
              slot_label = VALUES(slot_label),
              exam_opd_waiting = VALUES(exam_opd_waiting),
              exam_opd_avg_minutes = VALUES(exam_opd_avg_minutes),
              history_opd_waiting = VALUES(history_opd_waiting),
              history_opd_avg_minutes = VALUES(history_opd_avg_minutes),
              pharmacy_opd_waiting = VALUES(pharmacy_opd_waiting),
              pharmacy_opd_avg_minutes = VALUES(pharmacy_opd_avg_minutes),
              pharmacy_ncd_waiting = VALUES(pharmacy_ncd_waiting),
              pharmacy_ncd_avg_minutes = VALUES(pharmacy_ncd_avg_minutes),
              cashier_waiting = VALUES(cashier_waiting),
              cashier_avg_minutes = VALUES(cashier_avg_minutes),
              history_ncd_waiting = VALUES(history_ncd_waiting),
              history_ncd_avg_minutes = VALUES(history_ncd_avg_minutes),
              exam_ncd_waiting = VALUES(exam_ncd_waiting),
              exam_ncd_avg_minutes = VALUES(exam_ncd_avg_minutes),
              lab_waiting = VALUES(lab_waiting),
              lab_avg_minutes = VALUES(lab_avg_minutes),
              avg_total_service_minutes = VALUES(avg_total_service_minutes),
              total_recipients = VALUES(total_recipients),
              updated_at = CURRENT_TIMESTAMP
        ';
        $stmt = $conn->prepare($sql);
        $n = 0;
        foreach ($agg as $row) {
            $stmt->execute([
                'service_date' => $row['service_date'],
                'hour_slot' => $row['hour_slot'],
                'slot_label' => $row['slot_label'],
                'exam_opd_waiting' => $row['exam_opd_waiting'],
                'exam_opd_avg_minutes' => $row['exam_opd_avg_minutes'],
                'history_opd_waiting' => $row['history_opd_waiting'],
                'history_opd_avg_minutes' => $row['history_opd_avg_minutes'],
                'pharmacy_opd_waiting' => $row['pharmacy_opd_waiting'],
                'pharmacy_opd_avg_minutes' => $row['pharmacy_opd_avg_minutes'],
                'pharmacy_ncd_waiting' => $row['pharmacy_ncd_waiting'],
                'pharmacy_ncd_avg_minutes' => $row['pharmacy_ncd_avg_minutes'],
                'cashier_waiting' => $row['cashier_waiting'],
                'cashier_avg_minutes' => $row['cashier_avg_minutes'],
                'history_ncd_waiting' => $row['history_ncd_waiting'],
                'history_ncd_avg_minutes' => $row['history_ncd_avg_minutes'],
                'exam_ncd_waiting' => $row['exam_ncd_waiting'],
                'exam_ncd_avg_minutes' => $row['exam_ncd_avg_minutes'],
                'lab_waiting' => $row['lab_waiting'],
                'lab_avg_minutes' => $row['lab_avg_minutes'],
                'avg_total_service_minutes' => $row['avg_total_service_minutes'],
                'total_recipients' => $row['total_recipients'],
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * แปลง wide rush_hour → long opd_twin_hourly ให้ Digital Twin ใช้ต่อได้
     *
     * @param array<string, array<string, mixed>> $agg
     */
    private static function mirrorToTwinHourly(PDO $conn, array $agg): void
    {
        $sql = '
            INSERT INTO opd_twin_hourly (
              service_date, hour_slot, slot_label, zone_code,
              waiting_count, avg_wait_minutes, avg_service_minutes,
              served_count, capacity
            ) VALUES (
              :service_date, :hour_slot, :slot_label, :zone_code,
              :waiting_count, :avg_wait_minutes, :avg_service_minutes,
              :served_count, :capacity
            )
            ON DUPLICATE KEY UPDATE
              slot_label = VALUES(slot_label),
              waiting_count = VALUES(waiting_count),
              avg_wait_minutes = VALUES(avg_wait_minutes),
              avg_service_minutes = VALUES(avg_service_minutes),
              served_count = VALUES(served_count),
              capacity = VALUES(capacity),
              updated_at = CURRENT_TIMESTAMP
        ';
        $stmt = $conn->prepare($sql);

        $zoneKeys = array_merge(array_keys(self::ZONES), ['lab']);
        foreach ($agg as $row) {
            foreach ($zoneKeys as $zone) {
                $waiting = (int) ($row["{$zone}_waiting"] ?? 0);
                $avg = $row["{$zone}_avg_minutes"] ?? null;
                $avgVal = $avg !== null ? (float) $avg : 0.0;
                $capacity = $zone === 'lab'
                    ? 6
                    : (int) (self::ZONES[$zone]['twin_capacity'] ?? 4);
                $stmt->execute([
                    'service_date' => $row['service_date'],
                    'hour_slot' => $row['hour_slot'],
                    'slot_label' => $row['slot_label'],
                    'zone_code' => $zone,
                    'waiting_count' => $waiting,
                    'avg_wait_minutes' => $avgVal,
                    'avg_service_minutes' => $avgVal,
                    'served_count' => $waiting,
                    'capacity' => $capacity,
                ]);
            }
        }
    }

    /**
     * อ่าน rush_hour เป็นแถว long แบบ twin (hour/zone/waiting/...)
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fetchTwinRows(PDO $conn, string $date): array
    {
        self::ensureTables($conn);
        $stmt = $conn->prepare('
            SELECT *
            FROM rush_hour
            WHERE service_date = ?
            ORDER BY hour_slot
        ');
        $stmt->execute([$date]);
        $rows = [];
        $zoneKeys = array_merge(array_keys(self::ZONES), ['lab']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $wide) {
            $hour = (int) $wide['hour_slot'];
            foreach ($zoneKeys as $zone) {
                $waiting = (int) ($wide["{$zone}_waiting"] ?? 0);
                $avg = $wide["{$zone}_avg_minutes"] ?? null;
                $avgVal = $avg !== null && $avg !== '' ? (float) $avg : 0.0;
                $capacity = $zone === 'lab'
                    ? 6
                    : (int) (self::ZONES[$zone]['twin_capacity'] ?? 4);
                $rows[] = [
                    'hour' => $hour,
                    'zone' => $zone,
                    'waiting' => $waiting,
                    'avg_wait' => $avgVal,
                    'avg_service' => $avgVal,
                    'served' => $waiting,
                    'capacity' => $capacity,
                    'total_recipients' => (int) ($wide['total_recipients'] ?? 0),
                    'avg_total_service' => $wide['avg_total_service_minutes'] !== null
                        ? (float) $wide['avg_total_service_minutes']
                        : null,
                ];
            }
        }
        return $rows;
    }
}
