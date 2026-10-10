<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
const SESSION_TIMEOUT_SECONDS = 3600;
if (isset($_SESSION['user_id'])) {
    $sessionStarted = (int)($_SESSION['session_started_at'] ?? $_SESSION['last_activity'] ?? 0);
    if ($sessionStarted > 0 && (time() - $sessionStarted) >= SESSION_TIMEOUT_SECONDS) {
        $_SESSION = []; session_destroy(); header('Location: ../login.php?timeout=1'); exit;
    }
    $_SESSION['last_activity'] = time();
}
