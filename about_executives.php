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

<?php include 'includes/footer.php'; ?>
