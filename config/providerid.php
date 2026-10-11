<?php
// Provider ID / MOPH Health ID configuration.
// กำหนดค่าเหล่านี้ผ่าน Environment Variables ของ Laragon/Apache
// ห้ามใส่ secret ลงใน Git

// ไฟล์ตั้งค่า:
// - Localhost: C:/laragon/www/myhospitalweb/.env.myhospital
// - Production DirectAdmin: domains/<domain>/environment/myhospitalweb/.env.myhospital
//   อย่าวาง secret ไว้ใน public_html และอย่า commit ไฟล์นี้ขึ้น Git
// โหลดไฟล์ local/production secret โดยไม่เขียนทับ Environment Variable ที่ระบบกำหนดไว้แล้ว
$envFiles = [dirname(__DIR__) . '/.env.myhospital', dirname(__DIR__) . '/.env'];
// DirectAdmin: domains/<domain>/public_html/... และ domains/<domain>/environment/...
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $domainRoot = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
    $envFiles[] = $domainRoot . '/environment/myhospitalweb/.env.myhospital';
    $envFiles[] = dirname($domainRoot) . '/environment/myhospitalweb/.env.myhospital';
}
foreach (array_unique($envFiles) as $envFile) {
    if (!is_readable($envFile)) continue;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }
        if ($key !== '' && getenv($key) === false) putenv($key . '=' . $value);
    }
}

return [
    'enabled' => getenv('PROVIDERID_ENABLED') === 'true',
    'debug' => getenv('PROVIDERID_DEBUG') === 'true',
    'client_id' => getenv('HEALTH_ID_CLIENT_ID') ?: '',
    'client_secret' => getenv('HEALTH_ID_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('HEALTH_ID_REDIRECT_URI') ?: '',
    'provider_client_id' => getenv('PROVIDER_CLIENT_ID') ?: '',
    'provider_secret_key' => getenv('PROVIDER_SECRET_KEY') ?: '',
    'auth_url' => 'https://moph.id.th/oauth/redirect',
    'health_token_url' => 'https://moph.id.th/api/v1/token',
    'provider_token_url' => 'https://provider.id.th/api/v1/services/token',
    'provider_profile_url' => 'https://provider.id.th/api/v1/services/profile',
];
