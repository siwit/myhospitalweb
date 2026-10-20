<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

// Fetch Latest News
$newsList = [];
$procurementList = [];
$jobList = [];
try {
    $stmt = $conn->prepare("SELECT * FROM news WHERE type IN ('news', 'activity') ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $newsList = $stmt->fetchAll();
    $procurementList = $conn->query("SELECT * FROM news WHERE type = 'procurement' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $jobList = $conn->query("SELECT * FROM news WHERE type = 'job' ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch(PDOException $e) {
    $newsList = [];
    $procurementList = [];
    $jobList = [];
}

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS site_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visit_date DATE NOT NULL UNIQUE,
            visit_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch(PDOException $e) {
    // ignore
}

$today = date('Y-m-d');
$visitStmt = $conn->prepare("INSERT INTO site_visits (visit_date, visit_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE visit_count = visit_count + 1");
$visitStmt->execute([$today]);
try {
    $externalLinks = $conn->query("SELECT * FROM externallink ORDER BY created_at LIMIT 5")->fetchAll();
} catch(PDOException $e) {
    $externalLinks = [];
}

$twinNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$twinDate = $twinNow->format('Y-m-d');
$requestedTwinDate = $_GET['twin_date'] ?? '';
if (is_string($requestedTwinDate) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $requestedTwinDate, $dateParts)
    && checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
    $twinDate = $requestedTwinDate;
}
$twinPayload = ['date' => $twinDate, 'defaultHour' => 9, 'rows' => [], 'status' => 'unavailable'];
try {
    require_once 'includes/opd_twin.php';
    $twinPayload = opd_twin_payload($conn, $twinDate);
} catch (Throwable $e) {
    error_log('OPD twin: unable to read hourly statistics');
}
?>

<?php
// Check for active popup
$activePopup = $conn->query("SELECT * FROM popup_config WHERE id = 1 AND is_active = 1")->fetch();

if ($activePopup): 
?>
<div class="modal-overlay" id="welcomePopup" style="display: none;">
    <div class="modal-content-popup">
        <span onclick="closePopup()" class="modal-close">&times;</span>
        
        <?php if($activePopup['link_url']): ?>
        <a href="<?php echo htmlspecialchars($activePopup['link_url']); ?>" target="_blank">
            <img src="<?php echo htmlspecialchars($activePopup['image_url']); ?>" style="max-width: 100%; height: auto; border-radius: 4px;">
        </a>
        <?php else: ?>
        <img src="<?php echo htmlspecialchars($activePopup['image_url']); ?>" style="max-width: 100%; height: auto; border-radius: 4px;">
        <?php endif; ?>

        <div style="margin-top: 15px; text-align: right; border-top: 1px solid #eee; padding-top: 10px;">
            <label style="cursor: pointer; color: #666; font-size: 0.9rem; margin-right: 15px;">
                <input type="checkbox" id="dontShowAgain"> ไม่ต้องแสดงอีกในวันนี้
            </label>
            <button onclick="closePopup()" class="btn btn-sm btn-primary">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_url VARCHAR(255) NOT NULL,
            link_url VARCHAR(255) DEFAULT '',
            is_active TINYINT(1) DEFAULT 0,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch(PDOException $e) {
    // ignore table creation errors
}
$activeBanners = $conn->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
?>

<?php if(count($activeBanners) > 0): ?>
<section class="banner-slider" aria-label="แบนเนอร์หน้าแรก">
    <?php foreach($activeBanners as $index => $banner): ?>
    <div class="banner-slide <?= $index === 0 ? 'active' : ''; ?>">
        <!-- ?php if(!empty($banner['link_url'])): ?> -->
        <!-- <a href="?php echo htmlspecialchars($banner['link_url']); ?>"> -->
            <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="Banner <?= $index + 1; ?>">
        <!-- </a> -->
        <!-- ?php else: ?>
        img src="?php echo htmlspecialchars($banner['image_url']); ?>" alt="Banner <?= $index + 1; ?>"> -->
        <!-- ?php endif; ?> -->
    </div>
    <?php endforeach; ?>
    <?php if(count($activeBanners) > 1): ?>
    <button class="slider-btn slider-prev" type="button" aria-label="แบนเนอร์ก่อนหน้า">&#10094;</button>
    <button class="slider-btn slider-next" type="button" aria-label="แบนเนอร์ถัดไป">&#10095;</button>
    <?php endif; ?>
    <div class="slider-dots">
        <?php foreach($activeBanners as $index => $_): ?>
        <button class="slider-dot <?= $index === 0 ? 'active' : ''; ?>" type="button" data-index="<?= $index; ?>"></button>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>


<!-- Hero Section -->
<!-- <section class="card text-center" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); color: white; display:flex; align-items:center; gap:1.5rem;">
    <div style="flex-shrink:0;">
        <img src="assets/images/HA.png" alt="HA Logo" style="height:130px; width:auto; object-fit:contain;">
    </div>
     <div style="text-align:center; width:100%;">
        <h1 style="font-size: 2.0rem; margin-bottom: 1rem;">ยินดีต้อนรับสู่โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า 100 ปี</h1>
        <p style="font-size: 1.2rem;">โรงพยาบาลชุมชนมาตรฐาน ประชาชนสุขภาพดี เจ้าหน้าที่มีความสุข</p>
    </div> 
</section> -->
<section class="card text-center" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); color: white; display:flex; align-items:center; gap:1.5rem;">
    <!-- <div style="flex-shrink:0;">
        <img src="assets/images/HA.png" alt="HA Logo" style="height:130px; width:auto; object-fit:contain;">
    </div> -->
     <div style="text-align:center; width:100%;">
        <!-- <h1 style="font-size: 2.0rem; margin-bottom: 1rem;">ยินดีต้อนรับสู่โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า 100 ปี</h1> -->
        <p style="font-size: 2.0rem;">แบบจำลองปริมาณผู้ป่วยที่รับบริการในอาคารบริการผู้ป่วยนอก </p>
    </div> 
</section>

<?php include __DIR__ . '/includes/opd_twin_reference.php'; ?>

<!-- Stats Section -->
<!-- <section class="grid mt-2">
    <div class="card text-center">
        <h3><i class="fas fa-users-medical"></i> สถิติผู้มารับบริการ </h3>
        <p style="font-size: 3rem; color: var(--primary-color); font-weight: bold;">ระหว่างดำเนินการ</p>
        <p>ปี</p>
    </div>
    <div class="card text-center">
        <h3><i class="fas fa-bed"></i> ผู้ป่วยในปัจจุบัน</h3>
        <p style="font-size: 3rem; color: var(--accent-color); font-weight: bold;">42</p>
        <p>เตียง</p>
    </div>
    <div class="card text-center">
        <h3><i class="fas fa-user-md"></i> แพทย์ออกตรวจ</h3>
        <p style="font-size: 3rem; color: var(--primary-color); font-weight: bold;">8</p>
        <p>ท่าน</p>
    </div>
</section> -->

<!-- Online Services -->
<!-- Online Services (Temporarily Disabled) -->
<!-- <section class="mt-2">
    <h2 class="text-center" style="margin-bottom: 20px; color: var(--primary-color);">บริการออนไลน์</h2>
    <div class="grid">
        <a href="#" class="card text-center" style="text-decoration:none; color:inherit; transition: 0.3s; border:1px solid #eee;">
            <i class="fas fa-calendar-check" style="font-size: 3rem; color: var(--primary-color); margin-bottom:10px;"></i>
            <h3>นัดหมายออนไลน์</h3>
        </a>
        <a href="#" class="card text-center" style="text-decoration:none; color:inherit; transition: 0.3s; border:1px solid #eee;">
            <i class="fas fa-file-medical-alt" style="font-size: 3rem; color: var(--primary-color); margin-bottom:10px;"></i>
            <h3>ตรวจสอบสิทธิการรักษา</h3>
        </a>
        <a href="#" class="card text-center" style="text-decoration:none; color:inherit; transition: 0.3s; border:1px solid #eee;">
            <i class="fas fa-comments" style="font-size: 3rem; color: var(--primary-color); margin-bottom:10px;"></i>
            <h3>ปรึกษาปัญหาสุขภาพ</h3>
        </a>
    </div>
</section> -->

<!-- News & Events -->
<section class="mt-2">
    <div class="grid" style="grid-template-columns: 2fr 1fr;">
        <!-- Left: News -->
        <div>
            <h2 style="color: var(--primary-color); border-left: 5px solid var(--accent-color); padding-left: 10px; margin-bottom: 20px;">ข่าวประชาสัมพันธ์ / กิจกรรมของเรา</h2>
            <?php if(count($newsList) > 0): ?>
                <?php foreach($newsList as $news): ?>
                    <?php
                        $newsImage = null;
                        if (!empty($news['image_url'])) {
                            $newsImage = $news['image_url'];
                        } elseif (!empty($news['source_url']) && preg_match('/\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i', $news['source_url'])) {
                            $newsImage = $news['source_url'];
                        } elseif (!empty($news['content']) && preg_match('/<img[^>]+src=(?:"([^"]+)"|\'([^\']+)\'|([^\s>]+))/i', $news['content'], $imgMatch)) {
                            $newsImage = !empty($imgMatch[1]) ? $imgMatch[1] : (!empty($imgMatch[2]) ? $imgMatch[2] : $imgMatch[3]);
                        }
                    ?>
                    <div class="card" style="display: flex; gap: 20px; align-items: start;">
                        <?php if(!empty($newsImage)): ?>
                            <img src="<?php echo htmlspecialchars($newsImage); ?>" style="width: 150px; height: 100px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                            <div style="width: 150px; height: 100px; background: linear-gradient(180deg, #f5f5f5 0%, #e6e6e6 100%); display: flex; align-items: center; justify-content: center; border-radius: 8px; color: #666; font-size: 0.85rem; text-align: center; padding: 12px; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.03);">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                        <path d="M6 2H14L18 6V22C18 22.5523 17.5523 23 17 23H6C5.44772 23 5 22.5523 5 22V3C5 2.44772 5.44772 2 6 2Z" fill="#c3c3c3"/>
                                        <path d="M14 2V6H18" fill="#e2e2e2"/>
                                        <path d="M8.25 13.5H11" stroke="#6d6d6d" stroke-width="1.5" stroke-linecap="round"/>
                                        <path d="M8.25 16.5H11.75" stroke="#6d6d6d" stroke-width="1.5" stroke-linecap="round"/>
                                        <path d="M7 7H12" stroke="#6d6d6d" stroke-width="1.5" stroke-linecap="round"/>
                                        <path d="M13 17.5H16" stroke="#b33" stroke-width="1.5" stroke-linecap="round"/>
                                        <path d="M13 14.5H16" stroke="#b33" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                    <span style="font-weight: 600; color: #555;">เอกสารประกาศ</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div>
                            <?php $detailUrl = "news_detail.php?id=" . $news['id']; ?>
                            <h4>
                                <a href="<?php echo $detailUrl; ?>" style="text-decoration: none; color: var(--text-color);">
                                    <?php echo htmlspecialchars($news['title']); ?>
                                </a>
                            </h4>
                            <p style="font-size: 0.9rem; color: var(--light-text); margin-top: 5px;"><i class="far fa-clock"></i> <?php echo date("d/m/Y", strtotime($news['created_at'])); ?></p>
                            <p style="margin-top: 10px;"><?php echo mb_substr(strip_tags($news['content']), 0, 100); ?>...</p>
                            <!-- ?php if(!empty($news['source_url'])): ?>
                                <div style="margin-top: 12px; font-size: 0.9rem; color: var(--primary-color);">
                                    <i class="fas fa-link"></i>
                                    <a href="?php echo htmlspecialchars($news['source_url']); ?>" target="_blank" rel="noreferrer" style="color: var(--primary-color); text-decoration: underline;">
                                        ลิงก์ต้นทาง หรือ เอกสารแนบ
                                    </a>
                                </div>
                            ?php endif; ?> -->
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center" style="margin-bottom: 4rem;">
                    <a href="news.php" class="btn btn-outline">ดูข่าวทั้งหมด</a>
                </div>
            <?php else: ?>
                <div class="card"><p>ยังไม่มีข่าวสารในขณะนี้</p></div>
            <?php endif; ?>
        </div>

        <!-- Right: Announcements -->
        <div>
            <h2 style="color: var(--primary-color); border-left: 5px solid var(--accent-color); padding-left: 10px; margin-bottom: 20px;">ข่าวประกาศ</h2>
            <?php if(count($procurementList) > 0): ?>
            <div class="card" style="padding: 1rem;">
                <h4 style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">ประกาศจัดซื้อจัดจ้าง</h4>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach(array_slice($procurementList, 0, 5) as $item): ?>
                    <li style="margin-bottom: 10px;">
                        <a href="news_detail.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: var(--text-color);">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </a>
                        <p style="font-size: 0.9rem; color: var(--light-text); margin-top: 5px; margin-bottom: 0;"><i class="far fa-clock"></i> <?php echo date("d/m/Y", strtotime($item['created_at'])); ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="text-right" style="text-align: right;"><a href="news.php?cat=procurement" style="font-size: 0.9rem;">ดูทั้งหมด &raquo;</a></div>
            </div>
            <?php endif; ?>
            <?php if(count($jobList) > 0): ?>
            <div class="card" style="padding: 1rem; margin-top: 20px;">
                <h4 style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">ประกาศรับสมัครงาน</h4>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach(array_slice($jobList, 0, 5) as $item): ?>
                    <li style="margin-bottom: 10px;">
                        <a href="news_detail.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: var(--text-color);">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </a>
                        <p style="font-size: 0.9rem; color: var(--light-text); margin-top: 5px; margin-bottom: 0;"><i class="far fa-clock"></i> <?php echo date("d/m/Y", strtotime($item['created_at'])); ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="text-right" style="text-align: right;"><a href="news.php?cat=job" style="font-size: 0.9rem;">ดูทั้งหมด &raquo;</a></div>
            </div>
            <?php endif; ?>
            <?php if(count($externalLinks) > 0): ?>
            <div class="card" style="padding: 1rem; margin-top: 20px;">
                <h4 style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">แนะนำลิงก์หน่วยงานภายนอก</h4>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach($externalLinks as $link): ?>
                    <li style="margin-bottom: 10px;">
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noreferrer" style="text-decoration: none; color: var(--text-color);">
                            <?php echo htmlspecialchars($link['name']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

<div class="cookie-banner" id="cookieBanner">
    <div>
        เว็บไซต์นี้ใช้คุกกี้เพื่อพัฒนาประสิทธิภาพ และประสบการณ์ที่ดีในการใช้เว็บไซต์ของคุณ
        คุณสามารถศึกษารายละเอียดได้ที่ <a href="cookie_policy.php">นโยบายการใช้คุกกี้</a>
    </div>
    <div style="display:flex; gap:0.5rem;">
        <button class="cookie-banner__btn" id="declineCookies">ไม่ยอมรับ</button>
        <button class="cookie-banner__btn accept" id="acceptCookies">ยอมรับ</button>
    </div>
</div>

<script>
    (function () {
        const banner = document.getElementById('cookieBanner');
        if (!banner) return;
        const storageKey = 'nh_cookie_consent';

        const hideBanner = () => banner.classList.add('cookie-banner--hidden');
        const setConsent = value => {
            localStorage.setItem(storageKey, value);
            hideBanner();
        };

        const stored = localStorage.getItem(storageKey);
        if (stored) return hideBanner();

        document.getElementById('acceptCookies').addEventListener('click', () => setConsent('accepted'));
        document.getElementById('declineCookies').addEventListener('click', () => setConsent('declined'));
    })();
</script>

<?php include 'includes/footer.php'; ?>
