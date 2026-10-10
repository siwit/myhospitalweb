﻿<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$news = $stmt->fetch();

if (!$news) {
    echo "<div class='container mt-2'><div class='alert alert-danger'>แจ้งเตือน</div></div>";
    include 'includes/footer.php';
    exit;
}
?>

<div class="card mt-2">
    <div style="max-width: 800px; margin: 0 auto;">
        <h1 style="color: var(--primary-color); margin-bottom: 10px;"><?php echo $news['title']; ?></h1>
        <p style="color: var(--light-text); margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <i class="far fa-calendar-alt"></i> <?php echo date("d/m/Y H:i", strtotime($news['created_at'])); ?> | 
            <i class="fas fa-tag"></i> <?php echo ucfirst($news['type']); ?>
        </p>

        <?php if($news['image_url']): ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="<?php echo $news['image_url']; ?>" style="max-width: 100%; border-radius: 8px; box-shadow: var(--shadow);">
            </div>
        <?php endif; ?>

        <div style="line-height: 1.8; font-size: 1.1rem;">
            <?php echo nl2br($news['content']); ?>
        </div>

        <?php if(!empty($news['source_url'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid var(--primary-color);">
                            <strong><i class="fas fa-link"></i> ลิงก์ต้นทาง:</strong>
                <a href="<?php echo htmlspecialchars($news['source_url']); ?>" target="_blank" rel="noreferrer" style="color: var(--primary-color); text-decoration: underline;">
                    คลิ๊กเพื่อไปยังแหล่งอ้างอิง
                </a>
            </div>
        <?php endif; ?>

        <?php if(!empty($news['pdf_url'])): ?>
            <div style="margin-top: 10px; padding: 15px; background: #eef2f7; border-left: 4px solid #2563eb;">
                <strong><i class="fas fa-file-pdf"></i> เอกสารเพิ่มเติม:</strong>
                <a href="<?php echo htmlspecialchars($news['pdf_url']); ?>" target="_blank" style="color: #2563eb; text-decoration: underline;">
                    คลิกเพื่อดูเอกสารแนบ (.pdf)
                </a>
            </div>
        <?php endif; ?>

        <div style="margin-top: 50px; text-align: center;">
            <a href="javascript:history.back()" class="btn btn-outline"><i class="fas fa-arrow-left"></i> กลับหน้าหลัก</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
