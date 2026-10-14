<?php
/**
 * โหลดค่าจาก .env.myhospital / .env โดยไม่ทับ Environment Variable ที่มีอยู่แล้ว
 */
if (defined('MYHOSPITAL_ENV_LOADED')) {
    return;
}

$envFiles = [dirname(__DIR__) . '/.env.myhospital', dirname(__DIR__) . '/.env'];
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $domainRoot = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
    $envFiles[] = $domainRoot . '/environment/myhospitalweb/.env.myhospital';
    $envFiles[] = dirname($domainRoot) . '/environment/myhospitalweb/.env.myhospital';
}

foreach (array_unique($envFiles) as $envFile) {
    if (!is_readable($envFile)) {
        continue;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

define('MYHOSPITAL_ENV_LOADED', true);
