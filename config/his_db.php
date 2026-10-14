<?php
/**
 * เชื่อมต่อฐานข้อมูล HIS (slave) สำหรับรายงานระยะเวลาบริการ
 * ค่าเชื่อมต่ออ่านจาก Environment / .env.myhospital
 */
require_once __DIR__ . '/load_env.php';

$hisHost = getenv('HIS_DB_HOST') ?: '192.168.20.252';
$hisDb = getenv('HIS_DB_NAME') ?: 'myhpt_slave';
$hisUser = getenv('HIS_DB_USER') ?: '';
$hisPass = getenv('HIS_DB_PASS') !== false ? getenv('HIS_DB_PASS') : '';
$hisCharset = getenv('HIS_DB_CHARSET') ?: 'utf8mb4';

if ($hisUser === '') {
    throw new RuntimeException('ยังไม่ได้ตั้งค่า HIS_DB_USER ใน .env.myhospital');
}

try {
    $his = new PDO(
        "mysql:host={$hisHost};dbname={$hisDb};charset={$hisCharset}",
        $hisUser,
        $hisPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30,
        ]
    );
} catch (PDOException $e) {
    throw new RuntimeException('เชื่อมต่อฐานข้อมูล HIS ไม่สำเร็จ: ' . $e->getMessage(), 0, $e);
}
