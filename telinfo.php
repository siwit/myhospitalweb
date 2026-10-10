<?php
include 'includes/header.php';
?>

<style>
.telinfo-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 10px 0;
}
.telinfo-header {
    text-align: center;
    margin-bottom: 30px;
}
.telinfo-header h2 {
    color: var(--primary-color, #0d9488);
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 5px;
}
.telinfo-header p {
    color: #6b7280;
    font-size: 1.1rem;
}
.search-wrapper {
    position: relative;
    max-width: 500px;
    margin: 0 auto 30px auto;
}
.search-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}
.search-input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 1px solid #d1d5db;
    border-radius: 50px;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.search-input:focus {
    outline: none;
    border-color: var(--primary-color, #0d9488);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15), 0 4px 6px rgba(0, 0, 0, 0.05);
}
.tel-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 15px;
}
@media (min-width: 640px) {
    .tel-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .tel-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.tel-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.25s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.tel-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.06);
    border-color: var(--primary-color, #0d9488);
}
.tel-number-badge {
    background: linear-gradient(135deg, var(--primary-color, #0d9488) 0%, var(--accent-color, #0891b2) 100%);
    color: #fff;
    font-weight: 700;
    font-size: 1.15rem;
    min-width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 8px rgba(13, 148, 136, 0.2);
}
.tel-details {
    flex-grow: 1;
}
.tel-name {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 2px;
    font-size: 0.95rem;
}
.tel-sub {
    font-size: 0.8rem;
    color: #9ca3af;
}
.tel-action-icon {
    color: var(--primary-color, #0d9488);
    background: #f0fdfa;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.7;
}
.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 50px 20px;
    color: #9ca3af;
    display: none;
}
.no-results i {
    font-size: 3rem;
    margin-bottom: 10px;
    color: #d1d5db;
}
</style>

<div class="card mt-2">
    <div class="telinfo-container">
        
        <!-- Header -->
        <div class="telinfo-header">
            <h2>โทรศัพท์ภายใน</h2>
            <p>โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า 100 ปี</p>
        </div>

        <!-- Search Box -->
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="telSearch" class="search-input" placeholder="ค้นหาเบอร์โทร หรือ ชื่อห้อง/หน่วยงาน...">
        </div>

        <!-- Grid of Numbers -->
        <div class="tel-grid" id="telGrid">
            <?php
            $numbers = [
                ['tel' => '0', 'name' => 'งานธุรการ/งานการเจ้าหน้าที่'],
                ['tel' => '101', 'name' => 'งานการเงิน'],
                ['tel' => '102', 'name' => 'งานพัสดุ'],
                ['tel' => '103', 'name' => 'สสอ.เมืองยาง'],
                ['tel' => '104', 'name' => 'งานยุทธศาสตร์/IT/เลขานุการ ผอ.'],
                ['tel' => '105', 'name' => 'งานพยาบาลผู้ป่วยใน'],
                ['tel' => '106', 'name' => 'ห้องบัตร'],
                ['tel' => '107', 'name' => 'งานพยาบาล ER'],
                ['tel' => '108', 'name' => 'กลุ่มงานทันตกรรม'],
                ['tel' => '109', 'name' => 'กลุ่มงานแพทย์แผนไทยฯ'],
                ['tel' => '110', 'name' => 'งานพยาบาลผู้คลอด'],
                ['tel' => '111', 'name' => 'คลังยา'],
                ['tel' => '112', 'name' => 'กลุ่มงานรังสีวิทยา'],
                ['tel' => '113', 'name' => 'ห้องเก็บเงิน'],
                ['tel' => '114', 'name' => 'กลุ่มงานเภสัชกรรมฯ'],
                ['tel' => '115', 'name' => 'งานส่งเสริมสุขภาพ'],
                ['tel' => '116', 'name' => 'กลุ่มงานเทคนิคการแพทย์'],
                ['tel' => '120', 'name' => 'งานพยาบาลผู้ป่วยนอก'],
                ['tel' => '121', 'name' => 'ศูนย์เปล'],
                ['tel' => '202', 'name' => 'งานยานพาหนะ'],
                ['tel' => '207', 'name' => 'กลุ่มงานการพยาบาล'],
                ['tel' => '208', 'name' => 'ร้านค้า'],
                ['tel' => '209', 'name' => 'กลุ่มงานเวชกรรมฟื้นฟู'],
                ['tel' => '303', 'name' => 'ห้องหัวหน้าบริหาร'],
                ['tel' => '315', 'name' => 'งานหน่วยจ่ายกลาง'],
                ['tel' => '401', 'name' => 'งานซักฟอก'],
                ['tel' => '402', 'name' => 'งานซ่อมบำรุง'],
                ['tel' => '404', 'name' => 'งานรักษาความปลอดภัย'],
                ['tel' => '415', 'name' => 'กลุ่มงานบริการด้านปฐมภูมิฯ'],
                ['tel' => '416', 'name' => 'กลุ่มงานโภชนศาสตร์'],
                ['tel' => '422', 'name' => 'คลินิก NCD'],
                ['tel' => '423', 'name' => 'คลินิก ARI'],
                ['tel' => '424', 'name' => 'กลุ่มงานจิตเวชฯ'],
                ['tel' => '311', 'name' => 'มินิธัญญารักษ์'],
                ['tel' => '324', 'name' => 'Home Ward (โฮมวอร์ด)'],
            ];

            foreach ($numbers as $item) {
                ?>
                <div class="tel-card">
                    <div class="tel-number-badge">
                        <?php echo htmlspecialchars($item['tel']); ?>
                    </div>
                    <div class="tel-details">
                        <div class="tel-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="tel-sub">หมายเลขโทรศัพท์ภายใน</div>
                    </div>
                    <div class="tel-action-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                </div>
                <?php
            }
            ?>
            
            <!-- No results message -->
            <div class="no-results" id="noResults">
                <i class="far fa-frown"></i>
                <p>ไม่พบเบอร์โทรศัพท์หรือหน่วยงานที่คุณค้นหา</p>
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('telSearch').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.tel-card');
    let hasResults = false;
    
    cards.forEach(card => {
        const name = card.querySelector('.tel-name').textContent.toLowerCase();
        const number = card.querySelector('.tel-number-badge').textContent.trim();
        
        if (name.includes(query) || number.includes(query)) {
            card.style.setProperty('display', 'flex', 'important');
            hasResults = true;
        } else {
            card.style.setProperty('display', 'none', 'important');
        }
    });
    
    document.getElementById('noResults').style.display = hasResults ? 'none' : 'block';
});
</script>

<?php include 'includes/footer.php'; ?>
