<?php
/**
 * อ่านสถิติระยะเวลารอคอยจากตารางท้องถิ่น (visit นิรนาม)
 * แล้วสรุปเป็น Dashboard ตามช่วงวันที่ / แผนกหลัก
 */
class WaitTimeStore
{
    public const HOUR_SLOTS = [
        6 => '06:00-06:59 น.',
        7 => '07:00-07:59 น.',
        8 => '08:00-08:59 น.',
        9 => '09:00-09:59 น.',
        10 => '10:00-10:59 น.',
        11 => '11:00-11:59 น.',
        12 => '12:00-12:59 น.',
        13 => '13:00-13:59 น.',
        14 => '14:00-14:59 น.',
        15 => '15:00-15:59 น.',
    ];

    public static function ensureTables(PDO $conn): void
    {
        $sqlFile = dirname(__DIR__, 2) . '/database/waittime_stats.sql';
        if (!is_readable($sqlFile)) {
            return;
        }
        $sql = file_get_contents($sqlFile);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmtSql) {
            if ($stmtSql === '' || strpos($stmtSql, '--') === 0) {
                continue;
            }
            try {
                $conn->exec($stmtSql);
            } catch (PDOException $e) {
                // ignore if already exists / partial
            }
        }
        self::ensureColumns($conn);
    }

    /** เพิ่มคอลัมน์ใหม่ให้ตารางเดิม (production ที่สร้างไว้แล้ว) */
    public static function ensureColumns(PDO $conn): void
    {
        $alters = [
            "ALTER TABLE waittime_visit_anon ADD COLUMN main_depcode VARCHAR(10) DEFAULT NULL AFTER is_er_visit",
            "ALTER TABLE waittime_visit_anon ADD COLUMN main_department VARCHAR(150) DEFAULT NULL AFTER main_depcode",
            "ALTER TABLE waittime_visit_anon ADD KEY idx_waittime_main_dep (main_depcode)",
            "ALTER TABLE waittime_visit_anon ADD COLUMN cur_depcode VARCHAR(10) DEFAULT NULL AFTER pharmacy_department",
            "ALTER TABLE waittime_visit_anon ADD COLUMN current_department VARCHAR(150) DEFAULT NULL AFTER cur_depcode",
            "ALTER TABLE waittime_visit_anon ADD COLUMN wait_screen_minutes INT DEFAULT NULL AFTER current_department",
            "ALTER TABLE waittime_visit_anon ADD COLUMN on_screen_minutes INT DEFAULT NULL AFTER wait_screen_minutes",
            "ALTER TABLE waittime_visit_anon ADD KEY idx_waittime_cur_dep (cur_depcode)",
            // ย้ายค่าจากชื่อเดิม (ถ้ามี) ไปชื่อใหม่
            "UPDATE waittime_visit_anon SET wait_screen_minutes = wait_history_minutes WHERE wait_screen_minutes IS NULL AND wait_history_minutes IS NOT NULL",
            "UPDATE waittime_visit_anon SET on_screen_minutes = history_minutes WHERE on_screen_minutes IS NULL AND history_minutes IS NOT NULL",
        ];
        foreach ($alters as $sql) {
            try {
                $conn->exec($sql);
            } catch (PDOException $e) {
                // duplicate column/key หรือยังไม่มีคอลัมน์เก่า
            }
        }
    }

    /**
     * รายการแผนกหลักในช่วงวันที่ (สำหรับ dropdown)
     * @return array<int, array{main_depcode:string, main_department:?string, visit_count:int}>
     */
    public static function listDepartments(PDO $conn, string $dateFrom, string $dateTo): array
    {
        self::ensureTables($conn);
        $stmt = $conn->prepare("
            SELECT
              COALESCE(main_depcode, '') AS main_depcode,
              MAX(main_department) AS main_department,
              COUNT(*) AS visit_count
            FROM waittime_visit_anon
            WHERE vstdate BETWEEN :date_from AND :date_to
            GROUP BY COALESCE(main_depcode, '')
            ORDER BY visit_count DESC, main_department ASC
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDashboard(PDO $conn, string $dateFrom, string $dateTo, ?string $mainDepcode = null): array
    {
        self::ensureTables($conn);

        [$whereSql, $params] = self::buildWhere($dateFrom, $dateTo, $mainDepcode);

        $sqlKpi = "
            SELECT
              COUNT(*) AS total_vn,
              ROUND(AVG(CASE WHEN total_opd_minutes BETWEEN 0 AND 720 THEN total_opd_minutes END), 1) AS avg_total_opd_minutes,
              ROUND(AVG(CASE WHEN wait_screen_minutes BETWEEN 0 AND 480 THEN wait_screen_minutes END), 1) AS avg_wait_screen_minutes,
              ROUND(AVG(CASE WHEN on_screen_minutes BETWEEN 0 AND 240 THEN on_screen_minutes END), 1) AS avg_on_screen_minutes,
              ROUND(AVG(CASE WHEN wait_doctor_minutes BETWEEN 0 AND 480 THEN wait_doctor_minutes END), 1) AS avg_wait_doctor_minutes,
              ROUND(AVG(CASE WHEN doctor_minutes BETWEEN 0 AND 240 THEN doctor_minutes END), 1) AS avg_doctor_minutes,
              ROUND(AVG(CASE WHEN lab_total_minutes BETWEEN 0 AND 1440 THEN lab_total_minutes END), 1) AS avg_lab_wait_minutes,
              ROUND(AVG(CASE
                WHEN COALESCE(xray_total_minutes, xray_wait_minutes) BETWEEN 0 AND 1440
                  THEN COALESCE(xray_total_minutes, xray_wait_minutes)
              END), 1) AS avg_xray_wait_minutes,
              ROUND(AVG(CASE WHEN wait_drug_minutes BETWEEN 0 AND 240 THEN wait_drug_minutes END), 1) AS avg_wait_drug_minutes,
              SUM(CASE WHEN lab_item_count IS NOT NULL AND lab_item_count > 0 THEN 1 ELSE 0 END) AS vn_with_lab,
              SUM(CASE WHEN xray_item_count IS NOT NULL AND xray_item_count > 0 THEN 1 ELSE 0 END) AS vn_with_xray,
              SUM(CASE WHEN wait_drug_minutes IS NOT NULL THEN 1 ELSE 0 END) AS vn_with_drug,
              MAX(hospitalcode) AS hospitalcode,
              MAX(synced_at) AS synced_at
            FROM waittime_visit_anon
            WHERE {$whereSql}
        ";
        $stmt = $conn->prepare($sqlKpi);
        $stmt->execute($params);
        $kpi = $stmt->fetch() ?: [];

        $sqlBusy = "
            SELECT vst_hour, COUNT(*) AS vn_count
            FROM waittime_visit_anon
            WHERE {$whereSql}
              AND vst_hour BETWEEN 6 AND 15
            GROUP BY vst_hour
            ORDER BY vn_count DESC, vst_hour ASC
            LIMIT 1
        ";
        $stmt = $conn->prepare($sqlBusy);
        $stmt->execute($params);
        $busy = $stmt->fetch() ?: null;

        $sqlLongest = "
            SELECT
              vst_hour,
              COUNT(*) AS vn_count,
              ROUND(AVG(CASE WHEN total_opd_minutes BETWEEN 0 AND 720 THEN total_opd_minutes END), 1) AS avg_minutes
            FROM waittime_visit_anon
            WHERE {$whereSql}
              AND vst_hour BETWEEN 6 AND 15
            GROUP BY vst_hour
            HAVING avg_minutes IS NOT NULL
            ORDER BY avg_minutes DESC, vst_hour ASC
            LIMIT 1
        ";
        $stmt = $conn->prepare($sqlLongest);
        $stmt->execute($params);
        $longest = $stmt->fetch() ?: null;

        $sqlHours = "
            SELECT
              vst_hour,
              COUNT(*) AS vn_count,
              ROUND(AVG(CASE WHEN total_opd_minutes BETWEEN 0 AND 720 THEN total_opd_minutes END), 1) AS avg_minutes
            FROM waittime_visit_anon
            WHERE {$whereSql}
              AND vst_hour BETWEEN 6 AND 15
            GROUP BY vst_hour
            ORDER BY vst_hour
        ";
        $stmt = $conn->prepare($sqlHours);
        $stmt->execute($params);
        $hourRows = $stmt->fetchAll();

        $hourStats = [];
        foreach (self::HOUR_SLOTS as $hour => $label) {
            $hourStats[$hour] = [
                'hour' => $hour,
                'label' => $label,
                'vn_count' => 0,
                'avg_minutes' => null,
            ];
        }
        foreach ($hourRows as $row) {
            $h = (int)$row['vst_hour'];
            if (!isset($hourStats[$h])) {
                continue;
            }
            $hourStats[$h]['vn_count'] = (int)$row['vn_count'];
            $hourStats[$h]['avg_minutes'] = $row['avg_minutes'] !== null ? (float)$row['avg_minutes'] : null;
        }

        $busyHour = $busy ? (int)$busy['vst_hour'] : null;
        $longestHour = $longest ? (int)$longest['vst_hour'] : null;

        $selectedDeptName = null;
        if ($mainDepcode !== null && $mainDepcode !== '') {
            $stmt = $conn->prepare("
                SELECT MAX(main_department) AS main_department
                FROM waittime_visit_anon
                WHERE vstdate BETWEEN :date_from AND :date_to
                  AND main_depcode = :main_depcode
            ");
            $stmt->execute([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'main_depcode' => $mainDepcode,
            ]);
            $selectedDeptName = $stmt->fetchColumn() ?: null;
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'main_depcode' => $mainDepcode,
            'main_department' => $selectedDeptName,
            'hospitalcode' => $kpi['hospitalcode'] ?? null,
            'synced_at' => $kpi['synced_at'] ?? null,
            'total_vn' => (int)($kpi['total_vn'] ?? 0),
            'avg_total_opd_minutes' => self::numOrNull($kpi['avg_total_opd_minutes'] ?? null),
            'avg_wait_screen_minutes' => self::numOrNull($kpi['avg_wait_screen_minutes'] ?? null),
            'avg_on_screen_minutes' => self::numOrNull($kpi['avg_on_screen_minutes'] ?? null),
            'avg_wait_doctor_minutes' => self::numOrNull($kpi['avg_wait_doctor_minutes'] ?? null),
            'avg_doctor_minutes' => self::numOrNull($kpi['avg_doctor_minutes'] ?? null),
            'avg_lab_wait_minutes' => self::numOrNull($kpi['avg_lab_wait_minutes'] ?? null),
            'avg_xray_wait_minutes' => self::numOrNull($kpi['avg_xray_wait_minutes'] ?? null),
            'avg_wait_drug_minutes' => self::numOrNull($kpi['avg_wait_drug_minutes'] ?? null),
            'vn_with_lab' => (int)($kpi['vn_with_lab'] ?? 0),
            'vn_with_xray' => (int)($kpi['vn_with_xray'] ?? 0),
            'vn_with_drug' => (int)($kpi['vn_with_drug'] ?? 0),
            'longest_avg_slot' => $longestHour !== null ? [
                'hour' => $longestHour,
                'label' => self::HOUR_SLOTS[$longestHour] ?? sprintf('%02d:00-%02d:59 น.', $longestHour, $longestHour),
                'avg_minutes' => self::numOrNull($longest['avg_minutes'] ?? null),
                'vn_count' => (int)($longest['vn_count'] ?? 0),
            ] : null,
            'busiest_slot' => $busyHour !== null ? [
                'hour' => $busyHour,
                'label' => self::HOUR_SLOTS[$busyHour] ?? sprintf('%02d:00-%02d:59 น.', $busyHour, $busyHour),
                'vn_count' => (int)($busy['vn_count'] ?? 0),
            ] : null,
            'hour_stats' => array_values($hourStats),
            'departments' => self::listDepartments($conn, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildWhere(string $dateFrom, string $dateTo, ?string $mainDepcode): array
    {
        $where = ['vstdate BETWEEN :date_from AND :date_to'];
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        if ($mainDepcode !== null && $mainDepcode !== '') {
            if ($mainDepcode === '__none__') {
                $where[] = '(main_depcode IS NULL OR main_depcode = \'\')';
            } else {
                $where[] = 'main_depcode = :main_depcode';
                $params['main_depcode'] = $mainDepcode;
            }
        }

        return [implode(' AND ', $where), $params];
    }

    private static function numOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }
}
