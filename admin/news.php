<?php
require_once '../includes/session.php';
require_once '../config/db_connect.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Add News
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_news'])) {
    $type = $_POST['type'];
    $title = mb_check_encoding($_POST['title'], 'UTF-8') ? $_POST['title'] : mb_convert_encoding($_POST['title'], 'UTF-8', 'auto');
    $content = mb_check_encoding($_POST['content'], 'UTF-8') ? $_POST['content'] : mb_convert_encoding($_POST['content'], 'UTF-8', 'auto');
    $source_url = mb_check_encoding($_POST['source_url'], 'UTF-8') ? $_POST['source_url'] : mb_convert_encoding($_POST['source_url'], 'UTF-8', 'auto');
    $userId = $_SESSION['user_id'];
    $imageUrl = '';
    $pdfUrl = '';

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $targetDir = "../assets/images/news/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . '_' . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $imageUrl = 'assets/images/news/' . $fileName;
        }
    }

    // Handle PDF Upload
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $targetDirPdf = "../assets/docs/news/";
        if (!is_dir($targetDirPdf)) mkdir($targetDirPdf, 0777, true);
        
        $fileNamePdf = time() . '_' . basename($_FILES["pdf_file"]["name"]);
        $targetFilePathPdf = $targetDirPdf . $fileNamePdf;
        
        if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $targetFilePathPdf)) {
            $pdfUrl = 'assets/docs/news/' . $fileNamePdf;
        }
    }

    // กำหนดวันที่ลงประกาศ (ถ้าไม่เลือก ให้ใช้วันที่ปัจจุบัน)
    $createdAt = !empty($_POST['created_at']) ? date('Y-m-d H:i:s', strtotime($_POST['created_at'])) : date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO news (title, content, type, image_url, source_url, pdf_url, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $debugData = [
        'title' => $title,
        'content' => $content,
        'type' => $type,
        'image' => $_FILES['image']['name'] ?? null,
        'source_url' => $source_url,
        'created_at' => $createdAt
    ];
    // error_log('NEWS INSERT DEBUG: ' . json_encode($debugData, JSON_UNESCAPED_UNICODE));
    // echo "<script>console.log('NEWS INSERT DEBUG:', " . json_encode($debugData, JSON_UNESCAPED_UNICODE) . ");</script>";

    $stmt->execute([$title, $content, $type, $imageUrl, $source_url, $pdfUrl, $userId, $createdAt]);
    $msg = "เพิ่มข่าวสารเรียบร้อย";
}

// Delete News
if (isset($_GET['delete'])) {
    $conn->prepare("DELETE FROM news WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: news.php");
    exit;
}

$newsList = $conn->query("SELECT * FROM news ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข่าวสาร</title>
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
        button { padding: 10px 20px; background: #10b981; color: white; border: none; cursor: pointer; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        img.thumb { width: 50px; height: 50px; object-fit: cover; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BackOffice</h2>
    <ul>
        <li><a href="dashboard.php">แดชบอร์ด</a></li>
        <li><a href="news.php" style="color: white; background: #374151;">จัดการข่าวสาร</a></li>
        <li><a href="uploaddocument.php">จัดการเอกสารดาวน์โหลด</a></li>
        <?php if($_SESSION['role'] == 'admin'): ?>
        <li><a href="users.php">จัดการผู้ใช้งาน</a></li>
        <?php endif; ?>
        <li><a href="../index.php"><i class="fas fa-external-link-alt"></i> ดูหน้าเว็บ</a></li> 
        <li><a href="../logout.php">ออกจากระบบ</a></li>
    </ul>
</div>

<div class="content">
    <div class="card">
        <h3>เพิ่มข่าวสาร / ประกาศ</h3>
        <?php if(isset($msg)) echo "<p style='color:green'>$msg</p>"; ?>
        <form method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
            <div class="form-group">
                <label>หัวข้อ</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>ประเภท</label>
                <select name="type">
                    <option value="news">ข่าวประชาสัมพันธ์</option>
                    <option value="activity">กิจกรรมของเรา</option>
                    <option value="procurement">ประกาศจัดซื้อจัดจ้าง</option>
                    <option value="job">ประกาศรับสมัครงาน</option>
                </select>
            </div>
            <div class="form-group">
                <label>เนื้อหา</label>
                <textarea name="content" rows="5"></textarea>
            </div>
            <div class="form-group">
                <label>ลิงก์ต้นทาง (Optional)</label>
                <input type="text" name="source_url" placeholder="https://example.com/news/123">
            </div>
            <div class="form-group">
                <label>วันที่ลงประกาศ (หากไม่ระบุจะเป็นเวลาปัจจุบัน)</label>
                <input type="datetime-local" name="created_at">
            </div>
            <div class="form-group">
                <label>รูปภาพปก (Optional)</label>
                <input type="file" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label>เอกสารแนบ (PDF เท่านั้น)</label>
                <input type="file" name="pdf_file" accept=".pdf">
            </div>
            <button type="submit" name="add_news">โพสต์ข่าว</button>
        </form>
    </div>

    <div class="card">
        <h3>รายการข่าวสาร</h3>
        <table>
            <thead>
                <tr>
                    <th>ภาพ</th>
                    <th>วันที่</th>
                    <th>หัวข้อ</th>
                    <th>ประเภท</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($newsList as $news): ?>
                <tr>
                    <td>
                        <?php if($news['image_url']): ?>
                            <img src="../<?php echo $news['image_url']; ?>" class="thumb">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo date("d/m/Y", strtotime($news['created_at'])); ?></td>
                    <td><?php echo $news['title']; ?></td>
                    <td><?php echo ucfirst($news['type']); ?></td>
                    <td>
                        <a href="news.php?delete=<?php echo $news['id']; ?>" style="color: red;" onclick="return confirm('ลบข่าวนี้?');">ลบ</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
