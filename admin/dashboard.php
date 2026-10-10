<?php
session_start();
require_once '../config/db_connect.php';

// Check Auth
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'editor'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$visitStats = ['total' => 0, 'average' => 0];
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS site_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_date DATE NOT NULL,
            visit_count INT DEFAULT 0,
            UNIQUE KEY unique_date (visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch(PDOException $e) {
    // ignore
}
if ($userRole == 'admin') {
    try {
        $visitStats = $conn->query("
            SELECT 
                COALESCE(SUM(visit_count), 0) AS total,
                COALESCE(ROUND(SUM(visit_count)/12), 0) AS average
            FROM site_visits
            WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        ")->fetch(PDO::FETCH_ASSOC);
        if (!$visitStats) {
            $visitStats = ['total' => 0, 'average' => 0];
        }
    } catch(PDOException $e) {
        // ignore missing table
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; display: flex; background: #f3f4f6; margin: 0; }
        .sidebar { width: 250px; background: #1f2937; color: white; min-height: 100vh; padding: 20px; }
        .sidebar h2 { margin-bottom: 30px; text-align: center; color: #34d399; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { margin-bottom: 15px; }
        .sidebar a { color: #d1d5db; text-decoration: none; display: block; padding: 10px; border-radius: 4px; }
        .sidebar a:hover, .sidebar a.active { background: #374151; color: white; }
        .main-content { flex: 1; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn { padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #ef4444; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2><i class="fas fa-user-shield"></i> BackOffice</h2>
    <ul>
        <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> แดชบอร์ด</a></li>
        <li><a href="news.php"><i class="fas fa-newspaper"></i> จัดการข่าวสาร</a></li>
        <li><a href="uploaddocument.php"><i class="fas fa-file-upload"></i> จัดการเอกสารดาวน์โหลด</a></li>
        <li><a href="popup.php"><i class="fas fa-bullhorn"></i> จัดการ Popup</a></li>
        <li><a href="banner.php"><i class="fas fa-images"></i> จัดการ Banner</a></li>
        <li><a href="dctmanage.php"><i class="fas fa-user-md"></i> แพทย์ประจำ</a></li>
        <?php if($userRole == 'admin'): ?>
            <li><a href="users.php"><i class="fas fa-users-cog"></i> จัดการผู้ใช้งาน</a></li>
            <li><a href="externallink.php"><i class="fas fa-external-link-square-alt"></i> ลิงก์ภายนอก</a></li>
        <?php endif; ?>
        <li><a href="../index.php"><i class="fas fa-external-link-alt"></i> ดูหน้าเว็บ</a></li>
        <li><a href="../logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="card">
        <h1>สวัสดีคุณ <?php echo $_SESSION['username']; ?></h1>        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px;">
            <div class="card" style="background: #ecfdf5; border: 1px solid #10b981; color: #065f46; text-align: center;">
                <h3>จำนวนข่าวสะสม</h3>
                <?php $newsCount = $conn->query("SELECT COUNT(*) FROM news")->fetchColumn(); ?>
                <p style="font-size: 2rem; font-weight: bold;"><?php echo $newsCount; ?></p>
            </div>
            <?php if($userRole == 'admin'): ?>
            <div class="card" style="background: #fff7ed; border: 1px solid #f97316; color: #7c2d12; text-align: center;">
                <h3>จำนวน User ทั้งหมด</h3>
                <?php $userCount = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?>
                <p style="font-size: 2rem; font-weight: bold;"><?php echo $userCount; ?></p>
            </div>
            <div class="card" style="background: #eef2ff; border: 1px solid #6366f1; color: #312e81; text-align: center;">
                <h3>ผู้เข้าชมเว็บไซต์ (12 เดือน)</h3>
                <p style="font-size: 2rem; font-weight: bold;"><?php echo number_format($visitStats['total']); ?></p>
                <p>เฉลี่ยต่อเดือน <?php echo number_format($visitStats['average']); ?> ครั้ง</p>
                <button class="btn mt-2" id="openVisitModal">ดูกราฟ</button>
            </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
    <?php if($userRole == 'admin'): ?>
    <dialog id="visitDialog" style="padding:0; border:none; border-radius:14px;">
        <div style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">แผนภูมิผู้เข้าชมเว็บไซต์ <?php echo date("Y"); ?></h3>
                <button type="button" id="closeVisitDialog" class="modal-close" aria-label="ปิด">&times;</button>
            </div>
            <div class="visit-stats-summary" style="margin:1rem 0; display:flex; gap:1rem; flex-wrap:wrap;">
                <div>
                    <p style="margin:0; color: var(--light-text);">ยอดรวม 12 เดือน</p>
                    <strong style="font-size:1.5rem;"><?php echo number_format($visitStats['total']); ?></strong>
                </div>
                <div>
                    <p style="margin:0; color: var(--light-text);">เฉลี่ยต่อเดือน</p>
                    <strong style="font-size:1.5rem;"><?php echo number_format($visitStats['average']); ?></strong>
                </div>
            </div>
            <canvas id="adminVisitChart" style="height:320px;"></canvas>
        </div>
    </dialog>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const labels = <?php
                $months = [];
                for ($m = 1; $m <= 12; $m++) {
                    $months[] = DateTime::createFromFormat('!n', $m)->format('F');
                }
                echo json_encode($months);
            ?>;
            const values = <?php
                $year = date('Y');
                $counts = array_fill(1, 12, 0);
                $rows = $conn->prepare("
                    SELECT MONTH(visit_date) AS month, SUM(visit_count) AS total
                    FROM site_visits
                    WHERE YEAR(visit_date) = ?
                    GROUP BY MONTH(visit_date)
                ");
                $rows->execute([$year]);
                foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $counts[(int)$row['month']] = (int)$row['total'];
                }
                echo json_encode(array_values($counts));
            ?>;
            const ctx = document.getElementById('adminVisitChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'จำนวนผู้เข้าชม',
                            data: values,
                            borderColor: '#0ea5e9',
                            backgroundColor: 'rgba(14, 165, 233, 0.2)',
                            borderWidth: 2,
                            pointRadius: 4,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'จำนวน'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'เดือน'
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }

            const dialog = document.getElementById('visitDialog');
            const closeBtn = document.getElementById('closeVisitDialog');
            const openBtn = document.getElementById('openVisitModal');
            openBtn?.addEventListener('click', () => dialog?.showModal());
            closeBtn?.addEventListener('click', () => dialog?.close());
            dialog?.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        })();
    </script>
    <?php endif; ?>

</body>
</html>

