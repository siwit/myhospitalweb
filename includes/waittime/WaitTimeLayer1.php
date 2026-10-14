<?php
/**
 * Layer 1 (Detail / Fact)
 * ดึงข้อมูลระดับ visit (vn) จาก HIS แล้วคำนวณจุดเวลาและนาทีรายช่วง
 *
 * นิยาม service:
 * - service3  = ส่งตรวจ
 * - service4  = เริ่มซักประวัติ
 * - service11 = ซักประวัติเสร็จ
 * - service5  = แพทย์เริ่มตรวจ
 * - service12 = แพทย์ตรวจเสร็จ / สั่งยา (รวมหน้าจอ ER)
 * - service6  = บันทึกรายการยา (จัดยา)
 * - service16 = จ่ายยาให้ผู้ป่วย
 */
class WaitTimeLayer1
{
    /** รวมวันที่ + เวลา และรองรับ 24:00:00 */
    public static function dtExpr(string $dateCol, string $timeCol): string
    {
        return "
            CASE
                WHEN {$timeCol} IS NULL THEN NULL
                WHEN {$timeCol} = '24:00:00'
                    THEN STR_TO_DATE(CONCAT({$dateCol}, ' 00:00:00'), '%Y-%m-%d %H:%i:%s') + INTERVAL 1 DAY
                ELSE STR_TO_DATE(CONCAT({$dateCol}, ' ', {$timeCol}), '%Y-%m-%d %H:%i:%s')
            END
        ";
    }

    /**
     * SQL ชั้นรายละเอียด (parameter: :date_from, :date_to)
     */
    public static function detailSql(): string
    {
        $arrive = self::dtExpr('st.vstdate', 'st.service3');
        $startScreen = self::dtExpr('st.vstdate', 'st.service4');
        $endScreen = self::dtExpr('st.vstdate', 'st.service11');
        $startDoctor = self::dtExpr('st.vstdate', 'st.service5');
        $endDoctor = self::dtExpr('st.vstdate', 'st.service12');
        $arriveRx = self::dtExpr('st.vstdate', 'st.service6');
        $receiveDrug = self::dtExpr('st.vstdate', 'st.service16');

        $labOrder = self::dtExpr('lh_raw.order_date', 'lh_raw.order_time');
        $labReceive = self::dtExpr('lh_raw.receive_date', 'lh_raw.receive_time');
        $labReport = self::dtExpr('lh_raw.report_date', 'lh_raw.report_time');

        $xrayOrder = "
            COALESCE(
                xr_raw.order_datetime,
                " . self::dtExpr('xr_raw.request_date', 'xr_raw.request_time') . "
            )
        ";
        $xrayExam = self::dtExpr('xr_raw.examined_date', 'xr_raw.examined_time');
        $xrayReport = self::dtExpr('xr_raw.confirm_read_date', 'xr_raw.confirm_read_time');

        return "
SELECT
  (SELECT oc.hospitalcode FROM opdconfig oc LIMIT 1) AS hospitalcode,
  st.vn,
  st.hn,
  st.vstdate,
  st.vsttime,
  HOUR(st.vsttime) AS vst_hour,
  CASE WHEN ap.hn IS NOT NULL THEN 1 ELSE 0 END AS is_appointment_visit,
  CASE WHEN er.vn IS NOT NULL THEN 1 ELSE 0 END AS is_er_visit,

  {$arrive} AS dt_arrive_screening,
  {$startScreen} AS dt_start_screening,
  {$endScreen} AS dt_end_screening,
  {$startDoctor} AS dt_call_doctor,
  {$endDoctor} AS dt_end_doctor,
  {$arriveRx} AS dt_arrive_pharmacy,
  {$receiveDrug} AS dt_receive_drug,

  o.main_dep AS main_depcode,
  kd.department AS main_department,
  st.service3_dep AS screening_depcode,
  d3.department AS screening_department,
  st.service5_dep AS doctor_depcode,
  d5.department AS doctor_department,
  st.service6_dep AS pharmacy_depcode,
  d6.department AS pharmacy_department,

  /* รอซักประวัติ: ส่งตรวจ -> เริ่มซักประวัติ */
  TIMESTAMPDIFF(MINUTE, {$arrive}, {$startScreen}) AS wait_history_minutes,

  /* ซักประวัติ: เริ่ม -> เสร็จ */
  TIMESTAMPDIFF(MINUTE, {$startScreen}, {$endScreen}) AS history_minutes,

  /* รอแพทย์: ซักประวัติเสร็จ -> เริ่มตรวจ */
  TIMESTAMPDIFF(MINUTE, {$endScreen}, {$startDoctor}) AS wait_doctor_minutes,

  /* ตรวจแพทย์: เริ่มตรวจ -> ตรวจเสร็จ */
  TIMESTAMPDIFF(MINUTE, {$startDoctor}, {$endDoctor}) AS doctor_minutes,

  /* รอรับยา: ถึงห้องยา/จัดยา -> จ่ายยา */
  TIMESTAMPDIFF(MINUTE, {$arriveRx}, {$receiveDrug}) AS wait_drug_minutes,

  /* รวม OPD: ส่งตรวจ -> รับยา (ถ้าไม่มีใช้ถึงห้องยา) */
  CASE
    WHEN COALESCE(st.service16, st.service6) IS NULL THEN NULL
    ELSE TIMESTAMPDIFF(
      MINUTE,
      {$arrive},
      CASE
        WHEN COALESCE(st.service16, st.service6) = '24:00:00'
          THEN STR_TO_DATE(CONCAT(st.vstdate, ' 00:00:00'), '%Y-%m-%d %H:%i:%s') + INTERVAL 1 DAY
        ELSE STR_TO_DATE(CONCAT(st.vstdate, ' ', COALESCE(st.service16, st.service6)), '%Y-%m-%d %H:%i:%s')
      END
    )
  END AS total_opd_minutes,

  lh.lab_order_time,
  lh.lab_receive_time,
  lh.lab_report_time,
  lh.lab_item_count,
  TIMESTAMPDIFF(MINUTE, lh.lab_order_time, lh.lab_receive_time) AS lab_wait_receive_minutes,
  TIMESTAMPDIFF(MINUTE, lh.lab_receive_time, lh.lab_report_time) AS lab_process_minutes,
  TIMESTAMPDIFF(MINUTE, lh.lab_order_time, lh.lab_report_time) AS lab_total_minutes,

  xr.xray_order_time,
  xr.xray_exam_time,
  xr.xray_report_time,
  xr.xray_item_count,
  TIMESTAMPDIFF(MINUTE, xr.xray_order_time, xr.xray_exam_time) AS xray_wait_minutes,
  TIMESTAMPDIFF(MINUTE, xr.xray_exam_time, xr.xray_report_time) AS xray_process_minutes,
  TIMESTAMPDIFF(MINUTE, xr.xray_order_time, xr.xray_report_time) AS xray_total_minutes

FROM service_time st
LEFT JOIN ovst o ON o.vn = st.vn
LEFT JOIN kskdepartment kd ON kd.depcode = o.main_dep
LEFT JOIN kskdepartment d3 ON d3.depcode = st.service3_dep
LEFT JOIN kskdepartment d5 ON d5.depcode = st.service5_dep
LEFT JOIN kskdepartment d6 ON d6.depcode = st.service6_dep
LEFT JOIN er_regist er ON st.vn = er.vn
LEFT JOIN (
  SELECT DISTINCT hn, nextdate
  FROM oapp
  WHERE hn IS NOT NULL AND nextdate IS NOT NULL
) ap ON ap.hn = st.hn AND ap.nextdate = st.vstdate
LEFT JOIN (
  SELECT
    vn,
    MIN({$labOrder}) AS lab_order_time,
    MIN({$labReceive}) AS lab_receive_time,
    MAX({$labReport}) AS lab_report_time,
    COUNT(*) AS lab_item_count
  FROM lab_head lh_raw
  WHERE vn IS NOT NULL
  GROUP BY vn
) lh ON lh.vn = st.vn
LEFT JOIN (
  SELECT
    vn,
    MIN({$xrayOrder}) AS xray_order_time,
    MIN({$xrayExam}) AS xray_exam_time,
    MAX({$xrayReport}) AS xray_report_time,
    COUNT(*) AS xray_item_count
  FROM xray_report xr_raw
  WHERE vn IS NOT NULL
  GROUP BY vn
) xr ON xr.vn = st.vn
WHERE st.vstdate BETWEEN :date_from AND :date_to
  AND er.vn is null 
  AND st.service3 IS NOT NULL 
  AND st.service4 IS NOT NULL
  AND st.service11 IS NOT NULL
  AND st.service5 IS NOT NULL
  AND st.service4 >= st.service3
  AND st.service5 >= st.service4
";
    }

    /**
     * ดึงรายละเอียด Layer 1
     * @return array<int, array<string, mixed>>
     */
    public static function fetchDetail(PDO $his, string $dateFrom, string $dateTo): array
    {
        $stmt = $his->prepare(self::detailSql());
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        return $stmt->fetchAll();
    }
}
