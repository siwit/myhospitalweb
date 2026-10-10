<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

$types = [
    'form' => 'แบบฟอร์ม',
    'official' => 'หนังสือราชการ',
    'manual' => 'คู่มือหรือแนวปฏิบัติ',
    'other' => 'เอกสารอื่นๆ'
];

$filter = isset($_GET['type']) ? $_GET['type'] : '';
$query = "SELECT * FROM documents WHERE is_published = 1";
$params = [];

if ($filter && isset($types[$filter])) {
    $query .= " AND type = ?";
    $params = [$filter];
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll();
?>

<div class="container mt-4">
    <div class="card">
        <h2 style="color: var(--primary-color); border-bottom: 2px solid var(--gray-200); padding-bottom: 10px; margin-bottom: 20px;">
            <i class="fas fa-file-download"></i> เอกสารดาวน์โหลด
        </h2>

        <!-- Filter Dropdown -->
        <div style="margin-bottom: 25px; background: #f8fafc; padding: 15px; border-radius: 8px;">
            <form method="GET" id="filterForm" style="display: flex; align-items: center; gap: 10px;">
                <label for="type" style="font-weight: 600;">เลือกประเภทเอกสาร:</label>
                <select name="type" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ddd; min-width: 200px;">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if(count($documents) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f1f5f9; text-align: left;">
                            <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">ชื่อหัวข้อเอกสาร</th>
                            <th style="padding: 12px; border-bottom: 2px solid #e2e8f0; width: 150px;">ดาวน์โหลด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($documents as $doc): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px 12px;">
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($doc['title']); ?></div>
                                    <small style="color: #64748b;">
                                        <i class="far fa-folder"></i> <?php echo $types[$doc['type']]; ?> | 
                                        <i class="far fa-calendar-alt"></i> <?php echo date("d/m/Y", strtotime($doc['created_at'])); ?>
                                    </small>
                                </td>
                                <td style="padding: 15px 12px;">
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-sm" style="background: #0ea5e9; color: white; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-file-pdf"></i> ดาวน์โหลดแนบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center" style="padding: 40px; color: #94a3b8;">
                <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 10px; display: block;"></i>
                ไม่พบเอกสารในหมวดหมู่นี้
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>