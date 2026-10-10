<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

$types = [
    'news' => 'ข่าวประชาสัมพันธ์',
    'activity' => 'กิจกรรมของเรา',
    'procurement' => 'ประกาศจัดซื้อจัดจ้าง',
    'job' => 'ประกาศรับสมัครงาน'
];

$cat = isset($_GET['cat']) ? $_GET['cat'] : '';
$viewTypes = $cat && isset($types[$cat]) ? [$cat => $types[$cat]] : $types;
$newsGroups = [];
foreach ($viewTypes as $type => $label) {
    $stmt = $conn->prepare("SELECT * FROM news WHERE type = :type ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([':type' => $type]);
    $newsGroups[$type] = $stmt->fetchAll();
}
?>

<style>
    .news-section {
        margin-bottom: 40px;
    }
    .news-section h2 {
        color: var(--primary-color);
        border-bottom: 2px solid var(--gray-200);
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    .news-section .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
    }
    .news-section .card-grid .card {
        border: 1px solid #eee;
        padding: 1rem;
        box-shadow: none;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .news-section .card-grid .card a {
        text-decoration: none;
        color: var(--text-color);
        font-weight: 600;
    }
    .news-section .card-grid .card p {
        margin: 6px 0 0;
        color: var(--light-text);
        font-size: 0.85rem;
    }
</style>

<div class="container mt-4">
    <?php foreach ($viewTypes as $type => $label): ?>
        <?php if (empty($newsGroups[$type])) continue; ?>
        <div class="news-section">
            <h2><?php echo $label; ?></h2>
            <div class="card-grid">
                <?php foreach ($newsGroups[$type] as $news): ?>
                    <div class="card">
                        <?php if ($news['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="" style="width:100%; height:150px; object-fit:cover; margin-bottom:10px;">
                        <?php endif; ?>
                        <a href="news_detail.php?id=<?php echo $news['id']; ?>">
                            <?php echo htmlspecialchars($news['title']); ?>
                        </a>
                        <p><?php echo date("d/m/Y", strtotime($news['created_at'])); ?></p>
                        <?php if (!empty($news['source_url'])): ?>
                            <p style="font-size: 0.8rem;">
                                <i class="fas fa-link"></i> 
                                <a href="<?php echo htmlspecialchars($news['source_url']); ?>" target="_blank" rel="noreferrer" style="color: var(--primary-color); text-decoration: underline;">
                                    ลิงก์ต้นทาง หรือ ดาวน์โหลดเอกสารแนบ
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty(array_filter($newsGroups))): ?>
        <p class="text-center" style="color: #999;">ยังไม่มีข่าวสารในขณะนี้</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
