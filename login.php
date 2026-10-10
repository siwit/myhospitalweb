<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role or home
                if ($user['role'] == 'admin' || $user['role'] == 'editor') {
                    echo "<script>window.location.href='admin/dashboard.php';</script>"; 
                } else {
                    echo "<script>window.location.href='index.php';</script>";
                }
                exit;
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch(PDOException $e) {
            $error = 'ระบบผิดพลาด โปรดลองใหม่ภายหลัง';
        }
    } else {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    }
}
?>

<div class="container" style="max-width: 400px; margin-top: 50px; margin-bottom: 50px;">
    <div class="card">
        <h2 class="text-center" style="margin-bottom: 20px; color: var(--primary-color);">เข้าสู่ระบบ</h2>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div style="margin-bottom: 15px;">
                <label for="username">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" required>
            </div>
            <div style="margin-bottom: 20px;">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">เข้าสู่ระบบ</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
