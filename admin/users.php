<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check (Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Create User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $role]);
        $msg = "สร้างผู้ใช้สำเร็จ";
    } catch(PDOException $e) {
        $error = "ชื่อผู้ใช้ซ้ำ หรือเกิดข้อผิดพลาด";
    }
}

// Toggle Status / Delete
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($_GET['action'] == 'delete') {
        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    } elseif ($_GET['action'] == 'toggle') {
        $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    }
    header("Location: users.php");
    exit;
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse Dashboard CSS partials */
        body { font-family: 'Sarabun', sans-serif; display: flex; background: #f3f4f6; margin: 0; }
        .sidebar { width: 250px; background: #1f2937; color: white; min-height: 100vh; padding: 20px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar a { color: #d1d5db; text-decoration: none; display: block; padding: 10px; }
        .content { flex: 1; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn { padding: 8px 12px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BackOffice</h2>
    <ul>
        <li><a href="dashboard.php">แดชบอร์ด</a></li>
        <li><a href="news.php">จัดการข่าวสาร</a></li>
        <li><a href="users.php" style="color: white; background: #374151;">จัดการผู้ใช้งาน</a></li>
        <li><a href="../logout.php">ออกจากระบบ</a></li>
    </ul>
</div>

<div class="content">
    <div class="card">
        <h3>เพิ่มผู้ใช้งานใหม่</h3>
        <?php if(isset($msg)) echo "<p style='color:green'>$msg</p>"; ?>
        <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        
        <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
            <div>
                <label>ชื่อผู้ใช้</label><br>
                <input type="text" name="username" required style="padding: 8px;">
            </div>
            <div>
                <label>รหัสผ่าน</label><br>
                <input type="password" name="password" required style="padding: 8px;">
            </div>
            <div>
                <label>สิทธิ์</label><br>
                <select name="role" style="padding: 8px;">
                    <option value="editor">Editor (ผู้ประกาศข่าว)</option>
                    <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                </select>
            </div>
            <button type="submit" name="create_user" class="btn">บันทึก</button>
        </form>
    </div>

    <div class="card">
        <h3>รายชื่อผู้ใช้งาน</h3>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?php echo $u['username']; ?></td>
                    <td><?php echo $u['role']; ?></td>
                    <td>
                        <?php echo $u['is_active'] ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'; ?>
                    </td>
                    <td>
                        <?php if($u['username'] != 'Admin'): ?>
                            <a href="users.php?action=toggle&id=<?php echo $u['id']; ?>" class="btn" style="background:#f59e0b;">
                                <?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?>
                            </a>
                            <a href="users.php?action=delete&id=<?php echo $u['id']; ?>" class="btn" style="background:#ef4444;" onclick="return confirm('ยืนยันการลบ?');">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
