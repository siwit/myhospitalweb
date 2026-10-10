<?php
require_once '../includes/session.php';
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'editor'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS doctors (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        first_name VARCHAR(100) NOT NULL,\n        last_name VARCHAR(100) NOT NULL,\n        specialty VARCHAR(150) NOT NULL,\n        schedule VARCHAR(255) NOT NULL,\n        room VARCHAR(100) NOT NULL,\n        image_url VARCHAR(255) DEFAULT '',\n        is_active TINYINT(1) DEFAULT 0,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // ignore table creation errors
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_doctor'])) {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $specialty = trim($_POST['specialty']);
        $schedule = trim($_POST['schedule']);
        $room = trim($_POST['room']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $imageUrl = '';

        if (!empty($_FILES['doctor_image']['name']) && $_FILES['doctor_image']['error'] === UPLOAD_ERR_OK) {
            $targetDir = '../assets/images/doctors/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $filename = time() . '_' . basename($_FILES['doctor_image']['name']);
            $targetPath = $targetDir . $filename;
            if (move_uploaded_file($_FILES['doctor_image']['tmp_name'], $targetPath)) {
                $imageUrl = 'assets/images/doctors/' . $filename;
            }
        }

        $stmt = $conn->prepare('INSERT INTO doctors (first_name, last_name, specialty, schedule, room, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$firstName, $lastName, $specialty, $schedule, $room, $imageUrl, $isActive]);
        $message = 'บันทึกข้อมูลแพทย์เรียบร้อยแล้ว';
    }

    if (isset($_POST['toggle_doctor_visibility'])) {
        $doctorId = (int) $_POST['doctor_id'];
        $visible = isset($_POST['visible']) ? 1 : 0;
        $conn->prepare('UPDATE doctors SET is_active = ? WHERE id = ?')->execute([$visible, $doctorId]);
        $message = 'อัปเดตสถานะการแสดงผลแล้ว';
    }
}

$doctorList = $conn->query('SELECT * FROM doctors ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการแพทย์ประจำ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; display: flex; background: #f3f4f6; margin: 0; }
        .sidebar { width: 220px; background: #111827; color: white; min-height: 100vh; padding: 20px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar a { color: #d1d5db; text-decoration: none; display: block; padding: 10px; border-radius: 4px; margin-bottom: 8px; }
        .sidebar a:hover, .sidebar a.active { background: #374151; color: white; }
        .main-content { flex: 1; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.08); margin-bottom: 20px; }
        input[type="text"], input[type="file"], textarea { width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; box-sizing: border-box; }
        label { font-weight: 600; display: block; margin-bottom: 5px; }
        button { padding: 10px 16px; border: none; border-radius: 4px; background: #0f766e; color: white; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        img.thumb { height: 50px; width: 50px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>BackOffice</h2>
        <a href="dashboard.php">แดชบอร์ด</a>
        <a href="news.php">ข่าวสาร</a>
        <a href="popup.php">Popup</a>
        <a href="banner.php">Banner</a>
        <a href="dctmanage.php" class="active">จัดการแพทย์</a>
    </div>
    <div class="main-content">
        <div class="card">
            <h3>เพิ่มแพทย์ประจำ</h3>
            <?php if($message): ?>
                <p style="color:#047857;"><?php echo $message; ?></p>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div>
                    <label>ชื่อ</label>
                    <input type="text" name="first_name" required>
                </div>
                <div>
                    <label>นามสกุล</label>
                    <input type="text" name="last_name" required>
                </div>
                <div>
                    <label>สาขาเฉพาะทาง</label>
                    <input type="text" name="specialty" required>
                </div>
                <div>
                    <label>วันที่/เวลาที่ออกตรวจ</label>
                    <input type="text" name="schedule" required>
                </div>
                <div>
                    <label>ห้องตรวจ</label>
                    <input type="text" name="room" required>
                </div>
                <div>
                    <label>แนบรูปภาพ</label>
                    <input type="file" name="doctor_image" accept="image/*">
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_active" id="is_active">
                    <label for="is_active" style="margin:0; font-weight:500;">Active</label>
                </div>
                <div>
                    <button type="submit" name="save_doctor">บันทึกข้อมูลแพทย์</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3>รายการแพทย์</h3>
            <?php if(count($doctorList) === 0): ?>
                <p>ยังไม่มีแพทย์ในระบบ</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ</th>
                            <th>สาขา / ห้องตรวจ</th>
                            <th>สถานะ</th>
                            <th>รูปภาพ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($doctorList as $doctor): ?>
                        <tr>
                            <td><?php echo $doctor['id']; ?></td>
                            <td><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($doctor['specialty']); ?><br>
                                <small>ห้อง <?php echo htmlspecialchars($doctor['room']); ?></small><br>
                                <small><?php echo htmlspecialchars($doctor['schedule']); ?></small>
                            </td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                    <input type="hidden" name="toggle_doctor_visibility" value="1">
                                    <label style="display:flex; gap:4px; align-items:center;">
                                        <input type="checkbox" name="visible" <?php echo $doctor['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        Active
                                    </label>
                                </form>
                            </td>
                            <td>
                                <?php if($doctor['image_url']): ?>
                                    <img src="../<?php echo htmlspecialchars($doctor['image_url']); ?>" class="thumb" alt="<?php echo htmlspecialchars($doctor['first_name']); ?>">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
