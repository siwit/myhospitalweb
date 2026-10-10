<?php
require_once '../includes/session.php';
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_url VARCHAR(255) NOT NULL,
            link_url VARCHAR(255) DEFAULT '',
            is_active TINYINT(1) DEFAULT 0,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $conn->exec("ALTER TABLE banners CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch(PDOException $e) {
    die("ไม่สามารถเตรียมตาราง banner ได้: " . $e->getMessage());
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $linkUrl = trim($_POST['link_url']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $imageUrl = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "../assets/images/banners/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName = time() . '_' . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $imageUrl = 'assets/images/banners/' . $fileName;
        }
    }

    if ($imageUrl) {
        $stmt = $conn->prepare("INSERT INTO banners (image_url, link_url, is_active, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$imageUrl, $linkUrl, $isActive, $_SESSION['user_id']]);
        $msg = "บันทึก banner เรียบร้อยแล้ว";
    } else {
        $error = "กรุณาอัปโหลดรูปภาพสำหรับ banner";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_banner'])) {
    $bannerId = (int) $_POST['banner_id'];
    $linkUrl = trim($_POST['link_url']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE banners SET link_url = ?, is_active = ? WHERE id = ?");
    $stmt->execute([$linkUrl, $isActive, $bannerId]);
    $msg = "อัปเดตสถานะ banner แล้ว";
}

if (isset($_GET['delete'])) {
    $bannerId = (int) $_GET['delete'];
    $stmt = $conn->prepare("SELECT image_url FROM banners WHERE id = ?");
    $stmt->execute([$bannerId]);
    $row = $stmt->fetch();
    if ($row && !empty($row['image_url']) && file_exists('../' . $row['image_url'])) {
        unlink('../' . $row['image_url']);
    }
    $conn->prepare("DELETE FROM banners WHERE id = ?")->execute([$bannerId]);
    header("Location: banner.php");
    exit;
}

$bannerList = $conn->query("SELECT * FROM banners ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการ Banner หน้าแรก</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; display: flex; background: #f3f4f6; margin: 0; }
        .sidebar { width: 250px; background: #1f2937; color: white; min-height: 100vh; padding: 20px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar a { color: #d1d5db; text-decoration: none; display: block; padding: 10px; }
        .content { flex: 1; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="file"] { border: 1px dashed #ddd; padding: 12px; border-radius: 4px; width: 100%; }
        button { padding: 10px 20px; background: #10b981; color: white; border: none; cursor: pointer; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95rem; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; vertical-align: middle; }
        img.thumb { width: 120px; height: 80px; object-fit: cover; border-radius: 4px; }
        .actions form { display: flex; flex-direction: column; gap: 8px; }
        .actions input[type="text"] { width: 100%; }
        .actions label { font-weight: normal; }
        .actions button { width: 100px; align-self: flex-start; }
        .msg { padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .msg.success { background: #d1fae5; color: #065f46; }
        .msg.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BackOffice</h2>
    <ul>
        <li><a href="dashboard.php">หน้าแรก</a></li>
        <li><a href="news.php">ข่าวสาร</a></li>
        <li><a href="popup.php">Popup</a></li>
        <li><a href="banner.php" style="color: white; background: #374151;">Banner</a></li>
        <?php if($_SESSION['role'] == 'admin'): ?>
        <li><a href="users.php">ผู้ดูแลระบบ</a></li>
        <?php endif; ?>
        <li><a href="../index.php" target="_blank">ดูหน้าเว็บ</a></li>
        <li><a href="../logout.php">ออกจากระบบ</a></li>
    </ul>
</div>

<div class="content">
    <div class="card">
        <h3>อัปโหลด Banner</h3>
        <?php if($msg): ?><div class="msg success"><?= $msg; ?></div><?php endif; ?>
        <?php if($error): ?><div class="msg error"><?= $error; ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>รูปภาพ Banner (1600x600)</label>
                <input type="file" name="image" accept="image/*" required>
            </div>
            <div class="form-group">
                <label>ลิงก์เมื่อคลิก</label>
                <input type="text" name="link_url" placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" checked> แสดงบนหน้าแรก</label>
            </div>
            <button type="submit" name="add_banner">บันทึก Banner</button>
        </form>
    </div>

    <div class="card">
        <h3>รายการ Banner</h3>
        <table>
            <thead>
                <tr>
                    <th>ภาพ</th>
                    <th>ลิงก์</th>
                    <th>สถานะ</th>
                    <th>วันที่เพิ่ม</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($bannerList as $banner): ?>
                <tr>
                    <td>
                        <?php if($banner['image_url']): ?>
                            <img src="../<?= htmlspecialchars($banner['image_url']); ?>" class="thumb">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($banner['link_url']); ?></td>
                    <td><?= $banner['is_active'] ? 'เปิด' : 'ปิด'; ?></td>
                    <td><?= date("d/m/Y H:i", strtotime($banner['created_at'])); ?></td>
                    <td class="actions">
                        <form method="POST">
                            <input type="hidden" name="banner_id" value="<?= $banner['id']; ?>">
                            <input type="text" name="link_url" value="<?= htmlspecialchars($banner['link_url']); ?>" placeholder="https://example.com">
                            <label><input type="checkbox" name="is_active" value="1" <?= $banner['is_active'] ? 'checked' : ''; ?>> แสดงผล</label>
                            <button type="submit" name="update_banner">อัปเดต</button>
                            <a href="banner.php?delete=<?= $banner['id']; ?>" onclick="return confirm('ลบ banner นี้ใช่หรือไม่?');" style="color:#ef4444; font-size:0.9rem;">ลบ</a>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($bannerList) === 0): ?>
                <tr>
                    <td colspan="5" style="text-align:center;">ยังไม่มี banner ใดๆ</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
