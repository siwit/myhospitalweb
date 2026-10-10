<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// สร้างตารางสำหรับเก็บข้อมูลเอกสาร หากยังไม่มี
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        is_published TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(PDOException $e) { /* ignore */ }

$types = [
    'form' => 'แบบฟอร์ม',
    'official' => 'หนังสือราชการ',
    'manual' => 'คู่มือหรือแนวปฏิบัติ',
    'other' => 'เอกสารอื่นๆ'
];

// จัดการเพิ่มเอกสาร
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_doc'])) {
    $title = $_POST['title'];
    $type = $_POST['type'];
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $filePath = '';

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $targetDir = "../assets/docs/downloads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . '_' . basename($_FILES["pdf_file"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $targetFilePath)) {
            $filePath = 'assets/docs/downloads/' . $fileName;
            $stmt = $conn->prepare("INSERT INTO documents (title, type, file_path, is_published) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $type, $filePath, $isPublished]);
            $msg = "อัปโหลดเอกสารเรียบร้อย";
        }
    } else {
        $error = "กรุณาเลือกไฟล์เอกสาร (PDF)";
    }
}

// ลบเอกสาร
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $doc = $stmt->fetch();
    if ($doc && file_exists('../' . $doc['file_path'])) {
        unlink('../' . $doc['file_path']);
    }
    $conn->prepare("DELETE FROM documents WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: uploaddocument.php");
    exit;
}

$docList = $conn->query("SELECT * FROM documents ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการเอกสารดาวน์โหลด</title>
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
        input[type="text"], select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #0ea5e9; color: white; border: none; cursor: pointer; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
    </style>
</head>
<body>
<div class="sidebar">
    <h2>BackOffice</h2>
    <ul>
        <li><a href="dashboard.php">แดชบอร์ด</a></li>
        <li><a href="news.php">จัดการข่าวสาร</a></li>
        <li><a href="uploaddocument.php" style="color: white; background: #374151;">จัดการเอกสารดาวน์โหลด</a></li>
        <?php if($_SESSION['role'] == 'admin'): ?>
        <li><a href="users.php">จัดการผู้ใช้งาน</a></li>
        <?php endif; ?>
        <li><a href="../logout.php">ออกจากระบบ</a></li>
    </ul>
</div>
<div class="content">
    <div class="card">
        <h3>เพิ่มเอกสารดาวน์โหลด</h3>
        <?php if(isset($msg)) echo "<p style='color:green'>$msg</p>"; ?>
        <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>หัวข้อเอกสาร</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>ประเภทเอกสาร</label>
                <select name="type">
                    <?php foreach($types as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>ไฟล์เอกสาร (PDF เท่านั้น)</label>
                <input type="file" name="pdf_file" accept=".pdf" required>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_published" value="1" checked> เผยแพร่ (แสดงให้บุคคลทั่วไปเห็น)</label>
            </div>
            <button type="submit" name="add_doc">อัปโหลดเอกสาร</button>
        </form>
    </div>
    <div class="card">
        <h3>รายการเอกสารทั้งหมด</h3>
        <table>
            <thead>
                <tr>
                    <th>หัวข้อ</th>
                    <th>ประเภท</th>
                    <th>สถานะ</th>
                    <th>วันที่อัปโหลด</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($docList as $doc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doc['title']); ?></td>
                    <td><?php echo $types[$doc['type']] ?? $doc['type']; ?></td>
                    <td>
                        <?php echo $doc['is_published'] 
                            ? '<span style="color: green;">เผยแพร่</span>' 
                            : '<span style="color: gray;">ร่าง (ไม่แสดงผล)</span>'; ?>
                    </td>
                    <td><?php echo date("d/m/Y", strtotime($doc['created_at'])); ?></td>
                    <td>
                        <a href="../<?php echo $doc['file_path']; ?>" target="_blank" style="color: blue;">ดูไฟล์</a> |
                        <a href="javascript:void(0)" style="color: green;" onclick="copyToClipboard('../<?php echo addslashes($doc['file_path']); ?>')">คัดลอก</a> |
                        <a href="uploaddocument.php?delete=<?php echo $doc['id']; ?>" style="color: red;" onclick="return confirm('ยืนยันการลบเอกสาร?');">ลบ</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function copyToClipboard(relativeUrl) {
    if (!relativeUrl) return;
    
    // ใช้ HTMLAnchorElement เพื่อให้เบราว์เซอร์คำนวณ Full URL ให้เหมือนกับปุ่ม "ดูไฟล์"
    const tempLink = document.createElement('a');
    tempLink.href = relativeUrl;
    const absoluteUrl = tempLink.href;

    navigator.clipboard.writeText(absoluteUrl).then(() => {
        showToast("คัดลอกลิงก์เรียบร้อยแล้ว");
    }).catch(err => {
        showToast("ไม่สามาารถคัดลอกได้");
        console.error('ไม่สามารถคัดลอกลิงก์ได้: ', err);
    });
}

function showToast(message) {
    let toast = document.getElementById('copy-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'copy-toast';
        toast.style.cssText = "position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #10b981; color: #fff; padding: 12px 24px; border-radius: 8px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-weight: bold; transition: opacity 0.5s ease; pointer-events: none;";
        document.body.appendChild(toast);
    }
    toast.innerText = message;
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
    }, 1500);
}
</script>

</body>
</html>