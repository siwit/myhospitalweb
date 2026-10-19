<?php
/**
 * สรุป waittime_visit_anon → rush_hour (รายชั่วโมง)
 * และ mirror ไป opd_twin_hourly สำหรับ Digital Twin
 *
 * *_avg_minutes     = ระยะเวลารอคอยเฉลี่ย (นาที/ราย)
 * *_service_minutes = เวลารับบริการเฉลี่ย (นาที/ราย)
 */
class RushHourAggregator
{
    /** @var array<string, array{deps: string[], wait_col: ?string, service_col: ?string, twin_capacity: int}> */
    public const ZONES = [
        'exam_opd' => [
            'deps' => ['003', '004', '005', '029', '042'],
            'wait_col' => 'wait_doctor_minutes',
            'service_col' => 'doctor_minutes',
            'twin_capacity' => 8,
        ],
        'screen_opd' => [
            'deps' => ['002', '022', '024'],
            'wait_col' => 'wait_screen_minutes',
            'service_col' => 'on_screen_minutes',
            'twin_capacity' => 6,
        ],
        'pharmacy_opd' => [
            'deps' => ['007'],
            'wait_col' => 'wait_drug_minutes',
            'service_col' => null,
            'twin_capacity' => 4,
        ],
        'pharmacy_ncd' => [
            'deps' => ['098'],
            'wait_col' => 'wait_drug_minutes',
            'service_col' => null,
            'twin_capacity' => 3,
        ],
        'cashier' => [
            'deps' => ['006'],
            'wait_col' => null,
            'service_col' => null,
            'twin_capacity' => 3,
        ],
        'screen_ncd' => [
            'deps' => ['023'],
            'wait_col' => 'wait_screen_minutes',
            'service_col' => 'on_screen_minutes',
            'twin_capacity' => 4,
        ],
        'exam_ncd' => [
            'deps' => ['062'],
            'wait_col' => 'wait_doctor_minutes',
            'service_col' => 'doctor_minutes',
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

    /** โซนที่มีคอลัมน์ waiting/avg/service ใน rush_hour */
    public const ZONE_KEYS = [
        'exam_opd', 'screen_opd', 'pharmacy_opd', 'pharmacy_ncd',
        'cashier', 'screen_ncd', 'exam_ncd', 'lab',
    ];

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
        self::ensureColumns($conn);
    }

    /** rename history_* → screen_* และเพิ่ม *_service_minutes บนตารางเดิม */
    public static function ensureColumns(PDO $conn): void
    {
        $alters = [
            "ALTER TABLE rush_hour CHANGE COLUMN history_opd_waiting screen_opd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ซักประวัติ/screen OPD cur_dep 002,022,024'",
            "ALTER TABLE rush_hour CHANGE COLUMN history_opd_avg_minutes screen_opd_avg_minutes DECIMAL(6,1) DEFAULT NULL",
            "ALTER TABLE rush_hour CHANGE COLUMN history_ncd_waiting screen_ncd_waiting INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ซักประวัติ/screen NCD cur_dep 023'",
            "ALTER TABLE rush_hour CHANGE COLUMN history_ncd_avg_minutes screen_ncd_avg_minutes DECIMAL(6,1) DEFAULT NULL",
            "UPDATE opd_twin_hourly SET zone_code = 'screen_opd' WHERE zone_code = 'history_opd'",
            "UPDATE opd_twin_hourly SET zone_code = 'screen_ncd' WHERE zone_code = 'history_ncd'",
            "ALTER TABLE rush_hour ADD COLUMN exam_opd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER exam_opd_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN screen_opd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER screen_opd_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN pharmacy_opd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER pharmacy_opd_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN pharmacy_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER pharmacy_ncd_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN cashier_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER cashier_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN screen_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER screen_ncd_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN exam_ncd_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER exam_ncd_avg_minutes",
            "ALTER TABLE rush_hour ADD COLUMN lab_service_minutes DECIMAL(6,1) DEFAULT NULL COMMENT 'เวลารับบริการเฉลี่ย นาที/ราย' AFTER lab_avg_minutes",
        ];
        foreach ($alters as $sql) {
            try {
                $conn->exec($sql);
            } catch (PDOException $e) {
                // column already renamed / already exists / table missing
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
            $selectParts[] = self::avgCaseSql($inList, $meta['wait_col'], "{$zone}_avg_minutes");
            $selectParts[] = self::avgCaseSql($inList, $meta['service_col'], "{$zone}_service_minutes");
        }

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

        // LAB ตามชั่วโมงของ lab_order_time (รอ = ยังไม่มี report)
        $labSql = '
            SELECT
              DATE(lab_order_time) AS service_date,
              HOUR(lab_order_time) AS hour_slot,
              COUNT(*) AS lab_waiting,
              ROUND(AVG(CASE
                WHEN TIMESTAMPDIFF(MINUTE, lab_order_time, COALESCE(lab_report_time, NOW())) BETWEEN 0 AND 720
                THEN TIMESTAMPDIFF(MINUTE, lab_order_time, COALESCE(lab_report_time, NOW()))
              END), 1) AS lab_avg_minutes,
              ROUND(AVG(CASE
                WHEN lab_process_minutes BETWEEN 0 AND 480 THEN lab_process_minutes
              END), 1) AS lab_service_minutes
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
            $out[$key]['lab_avg_minutes'] = self::floatOrNull($labRow['lab_avg_minutes'] ?? null);
            $out[$key]['lab_service_minutes'] = self::floatOrNull($labRow['lab_service_minutes'] ?? null);
        }

        // เติมชั่วโมงว่าง 6–15 สำหรับทุกวันในช่วง
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

    private static function avgCaseSql(string $inList, ?string $col, string $alias): string
    {
        if ($col === null) {
            return "NULL AS {$alias}";
        }
        return "ROUND(AVG(CASE
                    WHEN cur_depcode IN ({$inList}) AND {$col} BETWEEN 0 AND 480 THEN {$col}
                END), 1) AS {$alias}";
    }

    /** @param mixed $v */
    private static function floatOrNull($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (float) $v;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeAggRow(string $date, int $hour, array $row): array
    {
        $base = self::emptyAggRow($date, $hour);
        foreach (self::ZONE_KEYS as $zone) {
            if ($zone === 'lab') {
                continue;
            }
            $base["{$zone}_waiting"] = (int) ($row["{$zone}_waiting"] ?? 0);
            $base["{$zone}_avg_minutes"] = self::floatOrNull($row["{$zone}_avg_minutes"] ?? null);
            $base["{$zone}_service_minutes"] = self::floatOrNull($row["{$zone}_service_minutes"] ?? null);
        }
        $base['lab_waiting'] = (int) ($row['lab_waiting'] ?? 0);
        $base['lab_avg_minutes'] = self::floatOrNull($row['lab_avg_minutes'] ?? null);
        $base['lab_service_minutes'] = self::floatOrNull($row['lab_service_minutes'] ?? null);
        $base['total_recipients'] = (int) ($row['total_recipients'] ?? 0);
        $base['avg_total_service_minutes'] = self::floatOrNull($row['avg_total_service_minutes'] ?? null);
        return $base;
    }

    /** @return array<string, mixed> */
    private static function emptyAggRow(string $date, int $hour): array
    {
        $row = [
            'service_date' => $date,
            'hour_slot' => $hour,
            'slot_label' => self::slotLabel($hour),
            'avg_total_service_minutes' => null,
            'total_recipients' => 0,
        ];
        foreach (self::ZONE_KEYS as $zone) {
            $row["{$zone}_waiting"] = 0;
            $row["{$zone}_avg_minutes"] = null;
            $row["{$zone}_service_minutes"] = null;
        }
        return $row;
    }

    /**
     * @param array<string, array<string, mixed>> $agg
     */
    private static function upsertRushHour(PDO $conn, array $agg): int
    {
        $cols = ['service_date', 'hour_slot', 'slot_label'];
        foreach (self::ZONE_KEYS as $zone) {
            $cols[] = "{$zone}_waiting";
            $cols[] = "{$zone}_avg_minutes";
            $cols[] = "{$zone}_service_minutes";
        }
        $cols[] = 'avg_total_service_minutes';
        $cols[] = 'total_recipients';

        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $updates = [];
        foreach ($cols as $c) {
            if (in_array($c, ['service_date', 'hour_slot'], true)) {
                continue;
            }
            $updates[] = "{$c} = VALUES({$c})";
        }
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';

        $sql = 'INSERT INTO rush_hour (' . implode(', ', $cols) . ')
            VALUES (' . implode(', ', $placeholders) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        $stmt = $conn->prepare($sql);
        $n = 0;
        foreach ($agg as $row) {
            $params = [];
            foreach ($cols as $c) {
                $params[$c] = $row[$c] ?? null;
            }
            $stmt->execute($params);
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

        foreach ($agg as $row) {
            foreach (self::ZONE_KEYS as $zone) {
                $waiting = (int) ($row["{$zone}_waiting"] ?? 0);
                $waitAvg = self::floatOrNull($row["{$zone}_avg_minutes"] ?? null) ?? 0.0;
                $svcAvg = self::floatOrNull($row["{$zone}_service_minutes"] ?? null) ?? 0.0;
                $capacity = $zone === 'lab'
                    ? 6
                    : (int) (self::ZONES[$zone]['twin_capacity'] ?? 4);
                $stmt->execute([
                    'service_date' => $row['service_date'],
                    'hour_slot' => $row['hour_slot'],
                    'slot_label' => $row['slot_label'],
                    'zone_code' => $zone,
                    'waiting_count' => $waiting,
                    'avg_wait_minutes' => $waitAvg,
                    'avg_service_minutes' => $svcAvg,
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
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $wide) {
            $hour = (int) $wide['hour_slot'];
            foreach (self::ZONE_KEYS as $zone) {
                $waiting = (int) ($wide["{$zone}_waiting"] ?? 0);
                $waitAvg = self::floatOrNull($wide["{$zone}_avg_minutes"] ?? null) ?? 0.0;
                $svcAvg = self::floatOrNull($wide["{$zone}_service_minutes"] ?? null) ?? 0.0;
                $capacity = $zone === 'lab'
                    ? 6
                    : (int) (self::ZONES[$zone]['twin_capacity'] ?? 4);
                $rows[] = [
                    'hour' => $hour,
                    'zone' => $zone,
                    'waiting' => $waiting,
                    'avg_wait' => $waitAvg,
                    'avg_service' => $svcAvg,
                    'served' => $waiting,
                    'capacity' => $capacity,
                    'total_recipients' => (int) ($wide['total_recipients'] ?? 0),
                    'avg_total_service' => self::floatOrNull($wide['avg_total_service_minutes'] ?? null),
                ];
            }
        }
        return $rows;
    }
}
