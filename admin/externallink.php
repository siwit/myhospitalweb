<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$msg = '';
$error = '';

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS externallink (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    // ignore
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_link'])) {
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        if ($name === '' || $url === '') {
            $error = 'กรุณากรอกชื่อและ URL ให้ครบถ้วน';
        } else {
            $stmt = $conn->prepare("INSERT INTO externallink (name, url) VALUES (?, ?)");
            $stmt->execute([$name, $url]);
            $msg = 'บันทึกลิงก์เรียบร้อยแล้ว';
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM externallink WHERE id = ?")->execute([$id]);
    header("Location: externallink.php");
    exit;
}

$links = $conn->query("SELECT * FROM externallink ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการลิงก์ภายนอก</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; display:flex; background:#f3f4f6; margin:0; }
        .sidebar { width:250px; background:#1f2937; color:white; min-height:100vh; padding:20px; }
        .sidebar ul { list-style:none; padding:0; }
        .sidebar a { color:#d1d5db; text-decoration:none; display:block; padding:10px; border-radius:4px; margin-bottom:8px; }
        .sidebar a:hover, .sidebar a.active { background:#374151; color:white; }
        .content { flex:1; padding:30px; }
        .card { background:white; padding:20px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1); margin-bottom:20px; }
        input, button { font-family:'Sarabun', sans-serif; }
        input[type="text"], input[type="url"] { width:100%; padding:10px; border-radius:4px; border:1px solid #d1d5db; box-sizing:border-box; }
        button { padding:10px 16px; border:none; border-radius:4px; background:#0f766e; color:#fff; cursor:pointer; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; }
        .btn-delete { background:#ef4444; }
    </style>
</head>
<body>
<div class="sidebar">
    <h2>BackOffice</h2>
    <ul>
        <li><a href="dashboard.php">แดชบอร์ด</a></li>
        <li><a href="news.php">ข่าวสาร</a></li>
        <li><a href="popup.php">Popup</a></li>
        <li><a href="banner.php">Banner</a></li>
        <li><a href="dctmanage.php">แพทย์ประจำ</a></li>
        <li><a href="users.php">จัดการผู้ใช้งาน</a></li>
        <li><a href="externallink.php" class="active">ลิงก์ภายนอก</a></li>
        <li><a href="../logout.php" style="color:#ef4444;">ออกจากระบบ</a></li>
    </ul>
</div>
<div class="content">
    <div class="card">
        <h3>จัดการลิงก์ภายนอก</h3>
        <?php if($msg) echo "<p style='color:green'>$msg</p>"; ?>
        <?php if($error) echo "<p style='color:red'>$error</p>"; ?>
        <form method="POST" style="display:grid; gap:1rem;">
            <div>
                <label>ชื่อ</label>
                <input type="text" name="name" required>
            </div>
            <div>
                <label>URL</label>
                <input type="url" name="url" required>
            </div>
            <button type="submit" name="add_link">บันทึก</button>
        </form>
    </div>
    <div class="card">
        <h3>รายการลิงก์</h3>
        <?php if(count($links) === 0): ?>
            <p>ยังไม่มีลิงก์</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ชื่อ</th>
                        <th>URL</th>
                        <th>วันที่เพิ่ม</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($links as $link): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($link['name']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"><?php echo htmlspecialchars($link['url']); ?></a></td>
                        <td><?php echo date("d/m/Y H:i", strtotime($link['created_at'])); ?></td>
                        <td>
                            <a href="externallink.php?delete=<?php echo $link['id']; ?>" class="btn btn-delete" style="background:#ef4444; text-decoration:none; color:#fff; padding:6px 12px; border-radius:4px;">ลบ</a>
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
