<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า 100 ปี - New Website</title>
    <link href="assets/images/favicon.png" rel="icon">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <div class="container navbar">
        <!-- Logo -->
        <a href="index.php" class="logo">
            <img src="assets/images/MOPH logo.png" alt="MOPH logo" style="height: 80px; margin-right: 8px;">
            <div class="logo-text">
                <span class="logo-title">H.R.H. THE PRINCESS MOTHER'S <br> CENTENARY CELEBRATIONS HOSPITAL</span>
                <span class="logo-subtitle">โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า 100 ปี</span>
            </div>
        </a>

        <!-- Hamburger for Mobile -->
        <div class="hamburger">
            <i class="fas fa-bars"></i>
        </div>

        <!-- Navigation -->
        <ul class="nav-links">
            <li><a href="index.php">หน้าหลัก</a></li>
            
            <li class="dropdown">
                <a href="#">ข่าวสาร <i class="fas fa-chevron-down"></i></a>
                <div class="dropdown-content">
                    <a href="news.php?cat=news">ข่าวประชาสัมพันธ์</a>
                    <a href="news.php?cat=activity">กิจกรรมของเรา</a>
                    <a href="news.php?cat=procurement">ประกาศจัดซื้อจัดจ้าง</a>
                    <a href="news.php?cat=job">ประกาศรับสมัครงาน</a>
                </div>
            </li>

            <li class="dropdown">
                <a href="#">บริการของเรา <i class="fas fa-chevron-down"></i></a>
                <div class="dropdown-content">
                    <a href="https://myhospital.moph.go.th/IM/index.php"  rel="noreferrer">MDC - Dashboard</a>  
                    <!-- target="_blank"  -->
                    <a href="service_schedule.php">ตารางคลินิกพิเศษ</a>
                    <a href="doctors.php">แพทย์ประจำโรงพยาบาล</a>
                    <a href="FAQ.php">คำถามที่พบบ่อย</a>
                </div>
            </li>

                        <!-- <li><a href="https://myhospital.moph.go.th/newweb/ita2025.html" rel="noreferrer">ITA</a></li> -->
                         <li><a href="https://myhospital.moph.go.th/ita" rel="noreferrer">ITA</a></li>

            <li class="dropdown">
                <a href="#">เกี่ยวกับเรา <i class="fas fa-chevron-down"></i></a>
                <div class="dropdown-content">
                    <a href="about_history.php">ประวัติโรงพยาบาล</a>
                    <!-- <a href="about_executives.php">คณะผู้บริหาร</a> -->
                    <a href="about_vision.php">วิสัยทัศน์ - พันธกิจ</a>
                    <a href="about_strategy.php">แผนยุทธศาสตร์</a>
                    <!-- <a href="gallery.php">คลังภาพ</a> -->
                    <a href="downloads.php">เอกสารดาวน์โหลด</a>
                </div>
            </li>

            <li><a href="contact.php">ติดต่อเรา</a></li>
        </ul>

        <!-- Right Side: Toggle + Login -->
        <div class="header-right">
            <!-- Grayscale Toggle -->
            <label class="switch" title="โหมดไว้อาลัย">
                <input type="checkbox" id="grayscale-toggle">
                <span class="slider"></span>
            </label>

            <!-- Login / User Menu -->
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="dropdown" id="userDropdown">
                    <a href="javascript:void(0)" class="btn-login" onclick="toggleUserMenu()"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['username']; ?></a>
                    <div class="dropdown-content" id="userDropdownContent" style="right: 0; left: auto;">
                        <a href="admin/dashboard.php">จัดการระบบ</a>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container">
