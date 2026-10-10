<?php
include 'includes/header.php';
$pages = [
    'doctors.php' => 'คณะแพทย์และบุคลากร',
    'about_message.php' => 'สารจากผู้บริหาร',
    'about_executives.php' => 'คณะผู้บริหาร',
    'about_vision.php' => 'วิสัยทัศน์ - พันธกิจ',
    'about_strategy.php' => 'ยุทธศาสตร์',
    'gallery.php' => 'คลังภาพ',
    'downloads.php' => 'เอกสารดาวน์โหลด'
];
$current_page = basename($_SERVER['PHP_SELF']);
$title = $pages[$current_page] ?? 'หน้าเว็บ';
?>

<div class="card mt-2" style="max-width: 520px;">
    <img src="assets/images/strategy/strategy_plan69.jpg" alt="แผนยุทธศาสตร์" style="width:100%; border-radius:12px; object-fit:cover; display:block;">
    <p style="margin-top:0.75rem; font-weight:600;">Download เอกสารที่นี่</p>
    <a href="https://drive.google.com/file/d/1R0Ym_8KCC2sEHAyOh8G0JkBSBul4o0Gb/view?usp=drive_link" class="btn" style="margin-top:0.25rem; background:#0ea5e9; color:#fff; text-decoration:none;">คลิกดู</a>
</div>

<?php include 'includes/footer.php'; ?>
