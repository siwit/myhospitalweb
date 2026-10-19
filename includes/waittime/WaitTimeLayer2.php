<?php
/**
 * Layer 2 (Aggregate / Mart)
 * สรุป KPI จาก Layer 1 สำหรับการ์ด Dashboard
 */
require_once __DIR__ . '/WaitTimeLayer1.php';

class WaitTimeLayer2
{
    /** ช่วงชั่วโมงที่สนใจ 06:00-15:59 */
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

    /**
     * สรุป KPI ทั้งหมดจาก Layer 1
     * @return array<string, mixed>
     */
    public static function buildDashboard(PDO $his, string $dateFrom, string $dateTo): array
    {
        $layer1 = WaitTimeLayer1::detailSql();

        // ค่าเฉลี่ยหลัก + จำนวน VN
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
              SUM(CASE WHEN lab_order_time IS NOT NULL THEN 1 ELSE 0 END) AS vn_with_lab,
              SUM(CASE WHEN xray_order_time IS NOT NULL THEN 1 ELSE 0 END) AS vn_with_xray,
              SUM(CASE WHEN wait_drug_minutes IS NOT NULL THEN 1 ELSE 0 END) AS vn_with_drug,
              MAX(hospitalcode) AS hospitalcode
            FROM (
              {$layer1}
            ) fact
        ";

        $stmt = $his->prepare($sqlKpi);
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $kpi = $stmt->fetch() ?: [];

        // ช่วงชั่วโมงที่มีจำนวน VN มากที่สุด (06-15)
        $sqlBusy = "
            SELECT vst_hour, COUNT(*) AS vn_count
            FROM (
              {$layer1}
            ) fact
            WHERE vst_hour BETWEEN 6 AND 15
            GROUP BY vst_hour
            ORDER BY vn_count DESC, vst_hour ASC
            LIMIT 1
        ";
        $stmt = $his->prepare($sqlBusy);
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $busy = $stmt->fetch() ?: null;

        // ช่วงชั่วโมงที่ค่าเฉลี่ย total_opd ยาวนานที่สุด (06-15)
        $sqlLongest = "
            SELECT
              vst_hour,
              COUNT(*) AS vn_count,
              ROUND(AVG(CASE WHEN total_opd_minutes BETWEEN 0 AND 720 THEN total_opd_minutes END), 1) AS avg_minutes
            FROM (
              {$layer1}
            ) fact
            WHERE vst_hour BETWEEN 6 AND 15
            GROUP BY vst_hour
            HAVING avg_minutes IS NOT NULL
            ORDER BY avg_minutes DESC, vst_hour ASC
            LIMIT 1
        ";
        $stmt = $his->prepare($sqlLongest);
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $longest = $stmt->fetch() ?: null;

        // กระจายรายชั่วโมงสำหรับอ้างอิงบนหน้า
        $sqlHours = "
            SELECT
              vst_hour,
              COUNT(*) AS vn_count,
              ROUND(AVG(CASE WHEN total_opd_minutes BETWEEN 0 AND 720 THEN total_opd_minutes END), 1) AS avg_minutes
            FROM (
              {$layer1}
            ) fact
            WHERE vst_hour BETWEEN 6 AND 15
            GROUP BY vst_hour
            ORDER BY vst_hour
        ";
        $stmt = $his->prepare($sqlHours);
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
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

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'hospitalcode' => $kpi['hospitalcode'] ?? null,
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
        ];
    }

    private static function numOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }
}
