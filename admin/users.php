<?php
require_once '../includes/session.php';
require_once '../config/db_connect.php';

// Auth Check (Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

foreach (["ALTER TABLE users ADD COLUMN cid CHAR(13) NULL UNIQUE", "ALTER TABLE users ADD COLUMN cid_sha256 CHAR(64) NULL UNIQUE"] as $migration) {
    try { $conn->exec($migration); } catch (PDOException $e) {}
}
$editUser = null;
if (isset($_GET['edit'])) { $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?'); $stmt->execute([(int)$_GET['edit']]); $editUser = $stmt->fetch(); }

// Create User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $cid = preg_replace('/\D/', '', $_POST['cid'] ?? '') ?: null;
    $cidSha256 = $cid ? hash('sha256', $cid) : null;

    try {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, cid, cid_sha256) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $role, $cid, $cidSha256]);
        $msg = "สร้างผู้ใช้สำเร็จ";
    } catch(PDOException $e) {
        $error = "ชื่อผู้ใช้ซ้ำ หรือเกิดข้อผิดพลาด";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $id = (int)$_POST['id']; $username = trim($_POST['username']); $role = $_POST['role']; $isActive = isset($_POST['is_active']) ? 1 : 0;
    $cid = preg_replace('/\D/', '', $_POST['cid'] ?? '') ?: null;
    $cidSha256 = $cid ? hash('sha256', $cid) : null;
    try {
        $sql = 'UPDATE users SET username = ?, role = ?, is_active = ?, cid = ?, cid_sha256 = ?'; $params = [$username, $role, $isActive, $cid, $cidSha256];
        if (!empty($_POST['password'])) { $sql .= ', password = ?'; $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT); }
        $sql .= ' WHERE id = ?'; $params[] = $id; $conn->prepare($sql)->execute($params);
        header('Location: users.php?updated=1'); exit;
    } catch (PDOException $e) { $error = 'บันทึกไม่สำเร็จ อาจมี Username หรือ CID ซ้ำ'; }
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
            <div><label>CID</label><br><input type="text" name="cid" maxlength="13" pattern="[0-9]{13}" style="padding: 8px;"></div>
            <button type="submit" name="create_user" class="btn">บันทึก</button>
        </form>
    </div>

    <?php if ($editUser): ?><div class="card"><h3>แก้ไขข้อมูลผู้ใช้</h3><form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;"><input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>"><div><label>Username</label><br><input type="text" name="username" value="<?php echo htmlspecialchars($editUser['username']); ?>" required></div><div><label>รหัสผ่านใหม่</label><br><input type="password" name="password"></div><div><label>Role</label><br><select name="role"><option value="editor" <?php echo $editUser['role']==='editor'?'selected':''; ?>>Editor</option><option value="admin" <?php echo $editUser['role']==='admin'?'selected':''; ?>>Admin</option></select></div><div><label>CID</label><br><input type="text" name="cid" value="<?php echo htmlspecialchars($editUser['cid'] ?? ''); ?>" maxlength="13" pattern="[0-9]{13}"></div><button type="submit" name="update_user" class="btn">บันทึกการแก้ไข</button><a href="users.php">ยกเลิก</a></form></div><?php endif; ?>
    <?php if ($editUser): ?><form method="POST" style="margin:10px 0"><input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>"><input type="hidden" name="username" value="<?php echo htmlspecialchars($editUser['username']); ?>"><input type="hidden" name="role" value="<?php echo htmlspecialchars($editUser['role']); ?>"><input type="hidden" name="cid" value="<?php echo htmlspecialchars($editUser['cid'] ?? ''); ?>"><input type="checkbox" name="is_active" value="1" <?php echo $editUser['is_active'] ? 'checked' : ''; ?>> Active <button type="submit" name="update_user" class="btn">บันทึกสถานะ</button></form><?php endif; ?>
    <div class="card">
        <h3>รายชื่อผู้ใช้งาน</h3>
        <table>
            <thead>
                <tr>
                    <th>Username</th><th>CID</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['username']); ?></td><td><?php echo htmlspecialchars($u['cid'] ?? '-'); ?></td>
                    <td><?php echo $u['role']; ?></td>
                    <td><a href="users.php?edit=<?php echo (int)$u['id']; ?>" class="btn" style="background:#2563eb;" title="แก้ไข"><i class="fas fa-edit"></i></a>
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
