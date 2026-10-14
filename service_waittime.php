<?php
require_once __DIR__ . '/config/load_env.php';
require_once __DIR__ . '/includes/waittime/WaitTimeStore.php';

$dateFrom = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])
    ? $_GET['date_from']
    : '2026-10-01';
$dateTo = isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])
    ? $_GET['date_to']
    : '2026-10-26';
$mainDepcode = isset($_GET['main_dep']) ? trim((string)$_GET['main_dep']) : '';
if ($mainDepcode !== '' && $mainDepcode !== '__none__' && !preg_match('/^[A-Za-z0-9_-]{1,10}$/', $mainDepcode)) {
    $mainDepcode = '';
}

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$error = '';
$dash = null;
$departments = [];
$dataSource = strtolower((string)(getenv('WAITTIME_SOURCE') ?: 'local'));

try {
    if ($dataSource === 'his') {
        require_once __DIR__ . '/includes/waittime/WaitTimeLayer2.php';
        require __DIR__ . '/config/his_db.php';
        $dash = WaitTimeLayer2::buildDashboard($his, $dateFrom, $dateTo);
        $dash['data_source'] = 'his';
        $departments = [];
    } else {
        require __DIR__ . '/config/db_connect.php';
        $departments = WaitTimeStore::listDepartments($conn, $dateFrom, $dateTo);
        $dash = WaitTimeStore::buildDashboard($conn, $dateFrom, $dateTo, $mainDepcode !== '' ? $mainDepcode : null);
        $dash['data_source'] = 'local';
        $departments = $dash['departments'] ?? $departments;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function wt_fmt(?float $n): string
{
    if ($n === null) {
        return '-';
    }
    return number_format($n, 1);
}

/** แสดงวันเวลาเป็น วัน เดือน ปี พ.ศ. เวลา เช่น 14 ตุลาคม 2569 16:30 น. */
function wt_fmt_datetime_be(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts)] ?? date('m', $ts);
    $yBe = (int)date('Y', $ts) + 543;
    $hm = date('H:i', $ts);
    return sprintf('%d %s %d %s น.', $d, $m, $yBe, $hm);
}

include __DIR__ . '/includes/header.php';
?>

<style>
.wt-page-title {
    color: var(--primary-color);
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 10px;
    margin-bottom: 1rem;
}
.wt-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: end;
    background: #f8fafc;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 1.5rem;
}
.wt-filter label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-weight: 600;
    font-size: 0.9rem;
}
.wt-filter .wt-date-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-weight: 600;
    font-size: 0.9rem;
}
.wt-filter .flatpickr-input,
.wt-filter input.wt-date-th,
.wt-filter select.wt-dept-select {
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    min-width: 200px;
    background: #fff;
    font-family: inherit;
    font-size: 0.95rem;
}
.wt-filter select.wt-dept-select {
    min-width: 240px;
    max-width: 320px;
}
.wt-filter .flatpickr-input[readonly] {
    cursor: pointer;
    background: #fff;
}
.wt-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.wt-kpi {
    background: var(--white);
    border-radius: 12px;
    padding: 1.1rem 1.2rem;
    box-shadow: var(--shadow);
    border-top: 4px solid var(--primary-color);
    min-height: 120px;
}
.wt-kpi.accent-orange { border-top-color: #f59e0b; }
.wt-kpi.accent-blue { border-top-color: #0ea5e9; }
.wt-kpi.accent-purple { border-top-color: #8b5cf6; }
.wt-kpi.accent-pink { border-top-color: #ec4899; }
.wt-kpi.accent-slate { border-top-color: #64748b; }
.wt-kpi.accent-red { border-top-color: #ef4444; }
.wt-kpi-label {
    color: var(--light-text);
    font-size: 0.92rem;
    margin-bottom: 0.55rem;
    line-height: 1.35;
}
.wt-kpi-value {
    font-size: 1.85rem;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1.2;
}
.wt-kpi-unit {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--light-text);
    margin-left: 4px;
}
.wt-kpi-sub {
    margin-top: 0.45rem;
    font-size: 0.85rem;
    color: #64748b;
}
.wt-section-title {
    margin: 0.5rem 0 1rem;
    color: var(--primary-color);
    border-left: 5px solid var(--accent-color);
    padding-left: 10px;
}
.wt-chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 0.5rem;
}
@media (max-width: 900px) {
    .wt-chart-grid { grid-template-columns: 1fr; }
}
.wt-chart-card {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1rem 1.1rem 0.75rem;
    box-shadow: var(--shadow);
}
.wt-chart-card h4 {
    margin: 0 0 0.75rem;
    color: var(--primary-color);
    font-size: 1.05rem;
}
.wt-chart-wrap {
    position: relative;
    height: 320px;
}
.wt-hour-table {
    width: 100%;
    border-collapse: collapse;
}
.wt-hour-table th, .wt-hour-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}
.wt-hour-table th {
    background: #ecfdf5;
    color: #065f46;
}
.wt-hour-table tr.is-busy td { background: #fff7ed; font-weight: 600; }
.wt-hour-table tr.is-longest td { background: #eff6ff; }
.wt-note {
    margin-top: 1rem;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.6;
}
.wt-alert {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 1rem;
}
.wt-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    background: #ecfdf5;
    color: #047857;
    margin-left: 6px;
}
</style>

<div class="card">
    <h2 class="wt-page-title">
        <i class="fas fa-hourglass-half"></i> ข้อมูลระยะเวลาการให้บริการ
        <!-- ?php if ($dash && !empty($dash['hospitalcode'])): ?>
            <span class="wt-badge">รหัส รพ ?php echo htmlspecialchars((string)$dash['hospitalcode']); ?></span>
        ?php endif; ? -->
        <!-- ?php if ($dash && !empty($dash['data_source'])): ?>
            <span class="wt-badge"><php echo $dash['data_source'] === 'his' ? 'แหล่ง: HIS ตรง' : 'แหล่ง: ฐานเว็บ'; ?></span>
        ?php endif; ?> -->
    </h2>
    <?php if ($dash && !empty($dash['synced_at'])): ?>
        <p style="margin:-0.4rem 0 1rem; color:#64748b; font-size:0.9rem;">
            ข้อมูล sync ล่าสุดในช่วงนี้: <?php echo htmlspecialchars(wt_fmt_datetime_be((string)$dash['synced_at'])); ?>
        </p>
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <form class="wt-filter" method="get" action="service_waittime.php" id="wtFilterForm">
        <div class="wt-date-wrap">
            <span>วันที่เริ่ม</span>
            <input type="text" id="date_from_th" class="wt-date-th" placeholder="วัน เดือน ปี พ.ศ." readonly required>
            <input type="hidden" name="date_from" id="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div class="wt-date-wrap">
            <span>ถึงวันที่</span>
            <input type="text" id="date_to_th" class="wt-date-th" placeholder="วัน เดือน ปี พ.ศ." readonly required>
            <input type="hidden" name="date_to" id="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div class="wt-date-wrap">
            <span>แผนกหลัก</span>
            <select name="main_dep" class="wt-dept-select">
                <option value="">ทุกแผนก</option>
                <?php foreach ($departments as $dep): ?>
                    <?php
                        $code = (string)($dep['main_depcode'] ?? '');
                        $name = trim((string)($dep['main_department'] ?? ''));
                        $optionValue = ($code === '') ? '__none__' : $code;
                        if ($code === '' && $name === '') {
                            $label = '(ไม่ระบุแผนก) (' . number_format((int)($dep['visit_count'] ?? 0)) . ')';
                        } else {
                            $label = ($name !== '' ? $name : $code) . ' (' . number_format((int)($dep['visit_count'] ?? 0)) . ')';
                        }
                    ?>
                    <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo ($mainDepcode !== '' && $mainDepcode === $optionValue) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> แสดงผล</button>
    </form>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <script>
    (function () {
        const thaiMonths = (flatpickr.l10ns.th && flatpickr.l10ns.th.months)
            ? flatpickr.l10ns.th.months.longhand
            : ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

        function pad(n) {
            return String(n).padStart(2, '0');
        }

        function toIso(date) {
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        function toThaiBe(date) {
            return date.getDate() + ' ' + thaiMonths[date.getMonth()] + ' ' + (date.getFullYear() + 543);
        }

        function parseIso(str) {
            const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str || '');
            if (!m) return null;
            return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        }

        function parseThaiBe(str) {
            const parts = String(str || '').trim().split(/\s+/);
            if (parts.length < 3) return null;
            const day = parseInt(parts[0], 10);
            const month = thaiMonths.indexOf(parts[1]);
            const be = parseInt(parts[2], 10);
            if (!day || month < 0 || !be) return null;
            return new Date(be - 543, month, day);
        }

        function bindThaiDate(displayId, hiddenId, defaultIso) {
            const hidden = document.getElementById(hiddenId);
            const initial = parseIso(defaultIso);
            const fp = flatpickr('#' + displayId, {
                locale: 'th',
                allowInput: false,
                disableMobile: true,
                defaultDate: initial || null,
                dateFormat: 'j F Y',
                formatDate: function (date) {
                    return toThaiBe(date);
                },
                parseDate: function (dateStr) {
                    return parseThaiBe(dateStr) || parseIso(dateStr);
                },
                onReady: function (selectedDates, dateStr, instance) {
                    if (selectedDates[0]) {
                        instance.input.value = toThaiBe(selectedDates[0]);
                        if (hidden) {
                            hidden.value = toIso(selectedDates[0]);
                        }
                    }
                },
                onChange: function (selectedDates) {
                    if (selectedDates[0]) {
                        if (hidden) {
                            hidden.value = toIso(selectedDates[0]);
                        }
                    }
                }
            });
            return fp;
        }

        bindThaiDate('date_from_th', 'date_from', <?php echo json_encode($dateFrom); ?>);
        bindThaiDate('date_to_th', 'date_to', <?php echo json_encode($dateTo); ?>);

        document.getElementById('wtFilterForm').addEventListener('submit', function (e) {
            const from = document.getElementById('date_from').value;
            const to = document.getElementById('date_to').value;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(from) || !/^\d{4}-\d{2}-\d{2}$/.test(to)) {
                e.preventDefault();
                alert('กรุณาเลือกวันที่เริ่มและถึงวันที่ให้ครบ');
            }
        });
    })();
    </script>

    <?php if ($error): ?>
        <div class="wt-alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($dash): ?>

        <h3 class="wt-section-title">ภาพรวมจำนวนและระยะเวลารอคอย / กระบวนการ</h3>
        <div class="wt-kpi-grid">
            <div class="wt-kpi accent-slate">
                <div class="wt-kpi-label">จำนวนมารับบริการทั้งหมด(เว้น ER)</div>
                <div class="wt-kpi-value"><?php echo number_format($dash['total_vn']); ?><span class="wt-kpi-unit"> ราย</span></div>
            </div>

            <div class="wt-kpi accent-orange">
                <div class="wt-kpi-label">ช่วงที่ใช้เวลานานที่สุด<br>(เวลาที่อยู่โรงพยาบาลโดยเฉลี่ย)</div>
                <div class="wt-kpi-value" style="font-size:1.25rem;">
                    <?php echo $dash['longest_avg_slot'] ? htmlspecialchars($dash['longest_avg_slot']['label']) : '-'; ?>
                </div>
                <div class="wt-kpi-sub">
                    เฉลี่ย   <span style="font-size:1.25rem; font-weight:bold; color:#000;">
        <?php echo wt_fmt($dash['longest_avg_slot']['avg_minutes'] ?? null); ?>
    </span> นาที/ราย
                    <?php if (!empty($dash['longest_avg_slot']['vn_count'])): ?>
                        จำนวน <?php echo number_format($dash['longest_avg_slot']['vn_count']); ?> ราย
                    <?php endif; ?>
                </div>
            </div>

            <?php
                $slot0800 = null;
                foreach ($dash['hour_stats'] as $hourRow) {
                    if ((int)$hourRow['hour'] === 8) {
                        $slot0800 = $hourRow;
                        break;
                    }
                }
            ?>
            <div class="wt-kpi accent-blue">
                <div class="wt-kpi-label">เวลาเฉลี่ยของผู้รับบริการที่มาช่วงเวลา</div>
                <div class="wt-kpi-value" style="font-size:1.25rem;">
                    08:00-08:59 น.
                </div>
                <div class="wt-kpi-sub">
                    เฉลี่ย   <span style="font-size:1.25rem; font-weight:bold; color:#000;">
       <?php echo wt_fmt($slot0800['avg_minutes'] ?? null); ?>
    </span> นาที/ราย
                    <?php if (!empty($slot0800['vn_count'])): ?>
                        จำนวน <?php echo number_format((int)$slot0800['vn_count']); ?> ราย
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="wt-kpi">
                <div class="wt-kpi-label">ระยะเวลารับบริการเฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_total_opd_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">ส่งบัตร → รับยา(กลับบ้าน)</div>
            </div>

            <div class="wt-kpi accent-blue">
                <div class="wt-kpi-label">ระยะเวลารอซักประวัติเฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_wait_history_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">ส่งบัตร → เริ่มซักประวัติ</div>
            </div>

            <div class="wt-kpi accent-blue">
                <div class="wt-kpi-label">ระยะเวลาซักประวัติโดยเฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_history_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">เริ่มซักประวัติ → ซักประวัติเสร็จ</div>
            </div>

            <div class="wt-kpi accent-purple">
                <div class="wt-kpi-label">ระยะเวลารอแพทย์ตรวจเฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_wait_doctor_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">ซักประวัติเสร็จ → เริ่มตรวจ</div>
            </div>

            <div class="wt-kpi accent-purple">
                <div class="wt-kpi-label">ระยะเวลาเฉลี่ยเริ่มตรวจถึงตรวจเสร็จ</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_doctor_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">เริ่มตรวจ → ตรวจเสร็จ</div>
            </div>

            <div class="wt-kpi accent-pink">
                <div class="wt-kpi-label">ระยะเวลารอ Lab เฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_lab_wait_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">order → report · <?php echo number_format($dash['vn_with_lab']); ?> ราย</div>
            </div>

            <div class="wt-kpi accent-pink">
                <div class="wt-kpi-label">ระยะเวลารอ X-ray เฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_xray_wait_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">สั่ง X-ray → รายงานผล  <?php echo number_format($dash['vn_with_xray']); ?> ราย</div>
            </div>

            <div class="wt-kpi accent-orange">
                <div class="wt-kpi-label">ระยะเวลารอรับยาเฉลี่ย</div>
                <div class="wt-kpi-value"><?php echo wt_fmt($dash['avg_wait_drug_minutes']); ?><span class="wt-kpi-unit">นาที/ราย</span></div>
                <div class="wt-kpi-sub">พิมพ์รายการยา Mode2 → บันทึกจ่ายยา · <?php echo number_format($dash['vn_with_drug']); ?> ราย</div>
            </div>

            <div class="wt-kpi accent-red">
                <div class="wt-kpi-label">ช่วงเวลาที่คนไข้อยู่ รพ. มากที่สุด</div>
                <div class="wt-kpi-value" style="font-size:1.25rem;">
                    <?php echo $dash['busiest_slot'] ? htmlspecialchars($dash['busiest_slot']['label']) : '-'; ?>
                </div>
                <div class="wt-kpi-sub">
                    <?php echo $dash['busiest_slot'] ? number_format($dash['busiest_slot']['vn_count']) . ' ราย' : '-'; ?>
                </div>
            </div>
        </div>

        <h3 class="wt-section-title">กระจายตามชั่วโมง (06:00-15:59)</h3>
        <?php
            $hourLabels = [];
            $hourAvg = [];
            $hourVn = [];
            $busyH = $dash['busiest_slot']['hour'] ?? null;
            $longH = $dash['longest_avg_slot']['hour'] ?? null;
            foreach ($dash['hour_stats'] as $row) {
                $hourLabels[] = preg_replace('/ น\.$/', '', $row['label']);
                $hourAvg[] = $row['avg_minutes'] !== null ? (float)$row['avg_minutes'] : 0;
                $hourVn[] = (int)$row['vn_count'];
            }
        ?>
        <div class="wt-chart-grid">
            <div class="wt-chart-card">
                <h4><i class="fas fa-chart-line"></i> เวลาเฉลี่ย (นาที)</h4>
                <div class="wt-chart-wrap">
                    <canvas id="wtAvgOpdChart"></canvas>
                </div>
            </div>
            <div class="wt-chart-card">
                <h4><i class="fas fa-chart-bar"></i> จำนวนผู้รับบริการ</h4>
                <div class="wt-chart-wrap">
                    <canvas id="wtVnCountChart"></canvas>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        (function () {
            const labels = <?php echo json_encode($hourLabels, JSON_UNESCAPED_UNICODE); ?>;
            const avgData = <?php echo json_encode($hourAvg); ?>;
            const vnData = <?php echo json_encode($hourVn); ?>;
            const longestHour = <?php echo json_encode($longH); ?>;
            const busyHour = <?php echo json_encode($busyH); ?>;
            const hours = <?php echo json_encode(array_map(static fn($r) => (int)$r['hour'], $dash['hour_stats'])); ?>;

            const avgColors = hours.map(h => h === longestHour ? 'rgba(14, 165, 233, 0.85)' : 'rgba(13, 150, 104, 0.75)');
            const vnColors = hours.map(h => h === busyHour ? 'rgba(245, 158, 11, 0.9)' : 'rgba(99, 102, 241, 0.75)');

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y;
                                return ctx.dataset.label + ': ' + (Number.isInteger(v) ? v : v.toFixed(1));
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 30, font: { size: 11 } },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            };

            const avgCtx = document.getElementById('wtAvgOpdChart');
            if (avgCtx) {
                new Chart(avgCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'เฉลี่ยนาที',
                            data: avgData,
                            backgroundColor: avgColors,
                            borderRadius: 6,
                            maxBarThickness: 42
                        }]
                    },
                    options: {
                        ...commonOptions,
                        scales: {
                            ...commonOptions.scales,
                            y: {
                                ...commonOptions.scales.y,
                                title: { display: true, text: 'นาที' }
                            }
                        }
                    }
                });
            }

            const vnCtx = document.getElementById('wtVnCountChart');
            if (vnCtx) {
                new Chart(vnCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'จำนวน VN',
                            data: vnData,
                            backgroundColor: vnColors,
                            borderRadius: 6,
                            maxBarThickness: 42
                        }]
                    },
                    options: {
                        ...commonOptions,
                        scales: {
                            ...commonOptions.scales,
                            y: {
                                ...commonOptions.scales.y,
                                title: { display: true, text: 'VN' }
                            }
                        }
                    }
                });
            }
        })();
        </script>

        <h3 class="wt-section-title" style="margin-top:1.5rem;">ช่วงเวลารายชั่วโมง(06:00-15:59)</h3>
        <div class="card" style="margin-bottom:0; box-shadow:none; border:1px solid var(--gray-200);">
            <div style="overflow-x:auto;">
                <table class="wt-hour-table">
                    <thead>
                        <tr>
                            <th>ช่วงเวลา</th>
                            <th>จำนวนผู้รับบริการ</th>
                            <th>เวลาเฉลี่ย (นาที)</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dash['hour_stats'] as $row):
                            $cls = [];
                            if ($busyH !== null && (int)$row['hour'] === (int)$busyH) $cls[] = 'is-busy';
                            if ($longH !== null && (int)$row['hour'] === (int)$longH) $cls[] = 'is-longest';
                            $notes = [];
                            if ($busyH !== null && (int)$row['hour'] === (int)$busyH) $notes[] = 'คนไข้มากสุด';
                            if ($longH !== null && (int)$row['hour'] === (int)$longH) $notes[] = 'ใช้เวลานานสุด';
                        ?>
                        <tr class="<?php echo htmlspecialchars(implode(' ', $cls)); ?>">
                            <td><?php echo htmlspecialchars($row['label']); ?></td>
                            <td><?php echo number_format((int)$row['vn_count']); ?></td>
                            <td><?php echo wt_fmt($row['avg_minutes']); ?></td>
                            <td><?php echo $notes ? htmlspecialchars(implode(' · ', $notes)) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="wt-note">
            
            <strong>หมายเหตุ:</strong> กรองค่าผิดปกติเบื้องต้นออก (เช่น นาทีติดลบ/เกินเกณฑ์) ก่อนหาค่าเฉลี่ย · ไม่นับเคส ER
        </p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
