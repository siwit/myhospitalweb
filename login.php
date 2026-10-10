<?php
require_once 'includes/session.php';
require_once 'config/db_connect.php';
$providerConfig = require 'config/providerid.php';

$error = '';
$providerDebug = ['page' => 'login', 'provider_enabled' => (bool)$providerConfig['enabled']];
$providerCallbackError = $_GET['error_description'] ?? $_GET['error'] ?? '';

// เตรียมคอลัมน์สำหรับจับคู่ Provider ID กับ users เดิม
foreach ([
    "ALTER TABLE users ADD COLUMN cid CHAR(13) NULL UNIQUE",
    "ALTER TABLE users ADD COLUMN cid_sha256 CHAR(64) NULL UNIQUE"
] as $migration) {
    try { $conn->exec($migration); } catch (PDOException $e) { /* มีคอลัมน์แล้ว */ }
}

// Provider ID callback: code -> Health ID token -> Provider ID profile -> ตรวจสอบ cid_sha256 ใน officer
if (isset($_GET['code']) && $_GET['code'] !== '') {
    $code = trim($_GET['code']);
    $providerDebug['callback'] = ['received_code' => true, 'code_length' => strlen($code), 'state' => $_GET['state'] ?? null];
    try {
        if (!$providerConfig['enabled']) throw new RuntimeException('Provider ID ยังไม่ได้เปิดใช้งาน');
        foreach (['client_id','client_secret','redirect_uri','provider_client_id','provider_secret_key'] as $key) {
            if (empty($providerConfig[$key])) throw new RuntimeException('ตั้งค่า Provider ID ไม่ครบ: ' . $key);
        }
        $postForm = function ($url, $data, $headers = []) {
            $ch = curl_init($url);
            $isJson = in_array('Content-Type: application/json', $headers, true);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $isJson ? json_encode($data) : http_build_query($data), CURLOPT_HTTPHEADER => array_merge([$isJson ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $headers), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
            $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
            if ($body === false || $err) throw new RuntimeException('เชื่อมต่อ Provider ID ไม่สำเร็จ');
            return [$status, json_decode($body, true) ?: []];
        };
        [$status, $health] = $postForm($providerConfig['health_token_url'], ['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$providerConfig['redirect_uri'],'client_id'=>$providerConfig['client_id'],'client_secret'=>$providerConfig['client_secret']]);
        $healthToken = $health['data']['access_token'] ?? $health['access_token'] ?? null;
        $providerDebug['health_token'] = ['http_status' => $status, 'received' => (bool)$healthToken];
        if (!$healthToken) throw new RuntimeException('แลก Health ID token ไม่สำเร็จ');
        [$status, $provider] = $postForm($providerConfig['provider_token_url'], ['client_id'=>$providerConfig['provider_client_id'],'secret_key'=>$providerConfig['provider_secret_key'],'token_by'=>'Health ID','token'=>$healthToken], ['Content-Type: application/json']);
        $providerToken = $provider['data']['access_token'] ?? $provider['access_token'] ?? null;
        $providerDebug['provider_token'] = ['http_status' => $status, 'received' => (bool)$providerToken];
        if (!$providerToken) throw new RuntimeException('แลก Provider ID token ไม่สำเร็จ');
        $ch = curl_init($providerConfig['provider_profile_url']);
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.$providerToken,'client-id: '.$providerConfig['provider_client_id'],'secret-key: '.$providerConfig['provider_secret_key']], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
        $profileBody = curl_exec($ch); curl_close($ch);
        $profile = json_decode($profileBody, true)['data'] ?? [];
        $cidHash = strtolower(trim($profile['hash_cid'] ?? ''));
        $providerDebug['profile'] = ['fields' => array_keys($profile), 'hash_cid_received' => $cidHash !== '', 'hash_cid_format_valid' => (bool)preg_match('/^[a-f0-9]{64}$/', $cidHash)];
        if ($providerConfig['debug']) {
            $providerDebug['debug_profile'] = $profile;
            $providerDebug['debug_hash_cid'] = $cidHash;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $cidHash)) throw new RuntimeException('ไม่พบ hash_cid จาก Provider ID');

        $stmt = $conn->prepare("SELECT * FROM users WHERE cid_sha256 = ? LIMIT 1");
        $stmt->execute([$cidHash]); $user = $stmt->fetch();
        $providerDebug['user_match'] = ['found' => (bool)$user, 'active' => $user ? (bool)$user['is_active'] : false];
        if (!$user) throw new RuntimeException('ไม่พบผู้ใช้งานที่ผูกกับ Provider ID นี้ในระบบ');
        if ((int)$user['is_active'] !== 1) throw new RuntimeException('ผู้ใช้งานนี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');

        session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role']; $_SESSION['provider_id'] = $user['cid']; $_SESSION['last_activity'] = time(); $_SESSION['session_started_at'] = time();
        header('Location: admin/dashboard.php'); exit;
    } catch (Throwable $e) { $providerCallbackError = $e->getMessage(); $providerDebug['error'] = $providerCallbackError; }
}
$providerLoginUrl = '';
if (!empty($providerConfig['client_id']) && !empty($providerConfig['redirect_uri'])) {
    $providerLoginUrl = $providerConfig['auth_url'] . '?' . http_build_query([
        'client_id' => $providerConfig['client_id'],
        'redirect_uri' => $providerConfig['redirect_uri'],
        'response_type' => 'code',
        'state' => 'login'
    ]);
}

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
                $_SESSION['last_activity'] = time();
                $_SESSION['session_started_at'] = time();
                
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
$error = $providerCallbackError ?: $error;
include 'includes/header.php';
?>

<div class="container" style="max-width: 400px; margin-top: 50px; margin-bottom: 50px;">
    <div class="card">
        <h2 class="text-center" style="margin-bottom: 20px; color: var(--primary-color);">เข้าสู่ระบบ</h2>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <script>window.addEventListener('DOMContentLoaded', function () { alert(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>); });</script>
        <?php endif; ?>

        <?php if ($providerConfig['enabled'] && $providerLoginUrl): ?>
            <a href="<?php echo htmlspecialchars($providerLoginUrl, ENT_QUOTES, 'UTF-8'); ?>" style="display:block; text-align:center; margin-bottom:14px;">
                <img src="assets/images/providerbutton.png" alt="เข้าสู่ระบบด้วย Provider ID" style="max-width:100%; height:auto; border:0;">
            </a>
            <p class="text-center" style="font-size: 13px; color: #64748b; margin: 0 0 16px;">ระบบยืนยันตัวตนบุคลากรสาธารณสุข</p>
        <?php else: ?>
            <div class="alert alert-warning">ยังไม่ได้เปิดใช้งาน Provider ID หรือยังตั้งค่า Client ID / Redirect URI ไม่ครบ</div>
        <?php endif; ?>

        <?php /* ปิดการเข้าสู่ระบบสำรองด้วย Username / Password */ ?><details style="display:none; margin-top: 20px;">
            <summary style="cursor:pointer; color:#64748b;">เข้าสู่ระบบสำรองด้วย Username / Password</summary>
        <form method="POST" action="" style="margin-top: 15px;">
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
        </details>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
window.providerLoginDebug = <?php echo json_encode($providerDebug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
console.info('[Myhospital ProviderID] ข้อมูลการทำงาน:', window.providerLoginDebug);
</script>
