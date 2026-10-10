<?php
require_once 'config/db_connect.php';
include 'includes/header.php';

$doctors = $conn->query("SELECT * FROM doctors WHERE is_active = 1 ORDER BY created_at")->fetchAll();
?>

<section class="mt-2">
    <div class="card">
        <p style="margin-bottom: 1.5rem;">
            โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า 100 ปี เป็นโรงพยาบาลขนาด 30 เตียง มีแพทย์ประจำทั้งหมด 5 ท่าน ให้บริการตรวจรักษาโรคทั่วไป คลินิกโรคเรื้อรัง ผู้ป่วยส่งต่อ ผู้ป่วยจิตเวชและยาเสพติด โดยให้บริการเต็มอัตราในวันและเวลาราชการ นอกเวลาราชการ และวันหยุดนักขัตฤกษ์ มีแพทย์ให้บริการ 1 ท่าน
        </p>
        <h2 style="color: var(--primary-color); border-left: 5px solid var(--accent-color); padding-left: 10px; margin-bottom: 20px;">แพทย์ประจำโรงพยาบาล</h2>
        <?php if(count($doctors) > 0): ?>
            <div class="doctor-grid">
                <?php foreach($doctors as $doctor): ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <?php if($doctor['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($doctor['image_url']); ?>" alt="<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>" style="width: 100%; height: 220px; object-fit: cover;">
                    <?php else: ?>
                        <div style="background: #e5e7eb; width: 100%; height: 220px; display:flex; align-items:center; justify-content:center;">
                            <span style="color:#6b7280;">ไม่มีรูปภาพ</span>
                        </div>
                    <?php endif; ?>
                    <div style="padding: 16px;">
                        <h3 style="margin-bottom: 8px;"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                        <p style="margin-bottom: 4px;"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                        <p style="margin-bottom: 4px;"><strong>ห้องตรวจ:</strong> <?php echo htmlspecialchars($doctor['room']); ?></p>
                        <p style="font-size: 0.9rem; color: var(--light-text);"><?php echo htmlspecialchars($doctor['schedule']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>ยังไม่มีแพทย์ประจำโรงพยาบาลในขณะนี้</p>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
