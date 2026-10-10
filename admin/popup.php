<?php
require_once '../includes/session.php';
require_once '../config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_popup'])) {
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $linkUrl = $_POST['link_url'];
    if (!mb_check_encoding($linkUrl, 'UTF-8')) {
        $linkUrl = mb_convert_encoding($linkUrl, 'UTF-8', 'auto');
    }
    $imageUrl = $_POST['existing_image'];

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $targetDir = "../assets/images/popup/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . '_' . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $imageUrl = 'assets/images/popup/' . $fileName;
        }
    }

    try {
        // We assume only 1 row exists (ID=1) or update all
        $stmt = $conn->prepare("UPDATE popup_config SET is_active = ?, image_url = ?, link_url = ? WHERE id = 1");
        // If no rows, we insert (just in case)
        if ($stmt->rowCount() == 0) {
           $conn->prepare("INSERT INTO popup_config (id, is_active, image_url, link_url) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE is_active=?, image_url=?, link_url=?")->execute([$isActive, $imageUrl, $linkUrl, $isActive, $imageUrl, $linkUrl]);
        } else {
           $stmt->execute([$isActive, $imageUrl, $linkUrl]);
        }
        $msg = "บันทึกข้อมูลเรียบร้อย";
    } catch(PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Fetch Current Config
$popup = $conn->query("SELECT * FROM popup_config WHERE id = 1")->fetch();
if(!$popup) $popup = ['is_active' => 0, 'image_url' => '', 'link_url' => ''];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการ Popup ต้อนรับ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; display: flex; background: #f3f4f6; margin: 0; }
        .sidebar { width: 250px; background: #1f2937; color: white; min-height: 100vh; padding: 20px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar a { color: #d1d5db; text-decoration: none; display: block; padding: 10px; }
        .content { flex: 1; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #10b981; color: white; border: none; cursor: pointer; border-radius: 4px; }
        img.preview { max-width: 300px; margin-top: 10px; border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BackOffice</h2>
    <ul>
        <li><a href="dashboard.php">แดชบอร์ด</a></li>
        <li><a href="news.php">จัดการข่าวสาร</a></li>
        <li><a href="uploaddocument.php">จัดการเอกสารดาวน์โหลด</a></li>
        <li><a href="popup.php" style="color: white; background: #374151;">จัดการ Popup</a></li>
        <?php if($_SESSION['role'] == 'admin'): ?>
        <li><a href="users.php">จัดการผู้ใช้งาน</a></li>
        <?php endif; ?>
        <li><a href="../logout.php">ออกจากระบบ</a></li>
    </ul>
</div>

<div class="content">
    <div class="card">
        <h3>ตั้งค่า Popup ต้อนรับ</h3>
        <?php if(isset($msg)) echo "<p style='color:green'>$msg</p>"; ?>
        
<form method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
            <div class="form-group">
                <label>สถานะการใช้งาน</label>
                <input type="checkbox" name="is_active" value="1" <?php echo $popup['is_active'] ? 'checked' : ''; ?>> เปิดใช้งาน Popup
            </div>
            
            <div class="form-group">
                <label>รูปภาพ Popup</label>
                <?php if($popup['image_url']): ?>
                    <img src="../<?php echo $popup['image_url']; ?>" class="preview"><br>
                <?php endif; ?>
                <input type="hidden" name="existing_image" value="<?php echo $popup['image_url']; ?>">
                <input type="file" name="image" accept="image/*">
            </div>

            <div class="form-group">
                <label>ลิงค์เมื่อคลิกรูปภาพ (Optional)</label>
                <input type="text" name="link_url" value="<?php echo $popup['link_url']; ?>" placeholder="https://...">
            </div>

            <button type="submit" name="save_popup">บันทึกการตั้งค่า</button>
        </form>
    </div>
</div>

</body>
</html>
