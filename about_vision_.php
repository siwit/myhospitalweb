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
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Infographic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f4f8;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
        }
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .vision-box {
            border-left: 5px solid #0d9488;
        }
        .strategy-number {
            background: #0d9488;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .value-tag {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-4xl mx-auto bg-white rounded-3xl overflow-hidden card-shadow">
        
        <!-- Header -->
        <div class="gradient-bg p-8 text-white text-center">
            <h1 class="text-3xl md:text-4xl font-bold mb-2">โรงพยาบาลเฉลิมพระเกียรติสมเด็จย่า ๑๐๐ ปี</h1>
            <div class="w-24 h-1 bg-white mx-auto opacity-50 rounded-full mb-4"></div>
            <p class="text-xl opacity-90">โรงพยาบาลชุมชนขนาด (F2) ๓๐ เตียง</p>
        </div>

        <!-- Vision Section -->
        <div class="p-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-eye text-3xl text-teal-600 mr-4"></i>
                <h2 class="text-2xl font-bold text-gray-800">วิสัยทัศน์ (Vision)</h2>
            </div>
            <div class="bg-teal-50 p-6 rounded-2xl border-l-8 border-teal-500 mb-8">
                <p class="text-2xl text-teal-900 font-bold text-center italic">
                    "โรงพยาบาลชุมชนมาตรฐาน ประชาชนสุขภาพดี เจ้าหน้าที่มีความสุข"
                </p>
            </div>

            <!-- Vision Details -->
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                <div class="p-4 bg-white border border-teal-100 rounded-xl shadow-sm">
                    <h3 class="font-bold text-teal-700 mb-2"><i class="fas fa-hospital-alt mr-2"></i>มาตรฐาน</h3>
                    <p class="text-sm text-gray-600">บริการสุขภาพครอบคลุม ๔ มิติ (ส่งเสริม ป้องกัน รักษา ฟื้นฟู) มีคุณภาพและปลอดภัย</p>
                </div>
                <div class="p-4 bg-white border border-teal-100 rounded-xl shadow-sm">
                    <h3 class="font-bold text-teal-700 mb-2"><i class="fas fa-heartbeat mr-2"></i>สุขภาพดี</h3>
                    <p class="text-sm text-gray-600">เข้าถึงระบบบริการ ครอบคลุมสิทธิประโยชน์ เครือข่ายเข้มแข็ง ชุมชนพึ่งพาตนเองได้</p>
                </div>
                <div class="p-4 bg-white border border-teal-100 rounded-xl shadow-sm">
                    <h3 class="font-bold text-teal-700 mb-2"><i class="fas fa-smile mr-2"></i>มีความสุข</h3>
                    <p class="text-sm text-gray-600">บุคลากรเก่ง ดี มีความสมดุลระหว่างชีวิตการทำงานและชีวิตส่วนตัว</p>
                </div>
            </div>

            <!-- Strategy Section -->
            <div class="grid md:grid-cols-2 gap-8 mb-12">
                <div>
                    <div class="flex items-center mb-6">
                        <i class="fas fa-chess text-2xl text-cyan-600 mr-4"></i>
                        <h2 class="text-2xl font-bold text-gray-800">ประเด็นยุทธศาสตร์</h2>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="strategy-number text-sm">1</div>
                            <p class="text-gray-700"><b>PP&P Excellence:</b> ส่งเสริมสุขภาพ ป้องกันโรค และคุ้มครองผู้บริโภคเป็นเลิศ</p>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="strategy-number text-sm">2</div>
                            <p class="text-gray-700"><b>Service Excellence:</b> ด้านบริการเป็นเลิศ</p>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="strategy-number text-sm">3</div>
                            <p class="text-gray-700"><b>People Excellence:</b> ด้านบุคลากรเป็นเลิศ</p>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="strategy-number text-sm">4</div>
                            <p class="text-gray-700"><b>Governance Excellence:</b> บริหารเป็นเลิศด้วยธรรมาภิบาล</p>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center mb-6">
                        <i class="fas fa-bullseye text-2xl text-red-500 mr-4"></i>
                        <h2 class="text-2xl font-bold text-gray-800">เป้าประสงค์หลัก</h2>
                    </div>
                    <div class="bg-gray-50 p-6 rounded-2xl space-y-4">
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i> ประชาชนสุขภาพดี
                        </div>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i> เจ้าหน้าที่เก่ง ดี มีคุณค่า มีความสุข
                        </div>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i> ระบบสุขภาพยั่งยืน
                        </div>
                    </div>
                </div>
            </div>

            <!-- Values Section -->
            <div class="border-t pt-8">
                <div class="flex items-center justify-center mb-6">
                    <i class="fas fa-gem text-2xl text-amber-500 mr-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800">ค่านิยม (Core Values)</h2>
                </div>
                <div class="flex flex-wrap justify-center gap-3">
                    <span class="value-tag px-4 py-2 rounded-full font-bold">Agility</span>
                    <span class="value-tag px-4 py-2 rounded-full font-bold">Customer Focus</span>
                    <span class="value-tag px-4 py-2 rounded-full font-bold">Teamwork</span>
                    <span class="value-tag px-4 py-2 rounded-full font-bold">Management by Fact</span>
                    <span class="value-tag px-4 py-2 rounded-full font-bold">Empowerment</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-100 p-6 text-center text-gray-500 text-sm">
            <p>โรงพยาบาลชุมชนมาตรฐาน ประชาชนสุขภาพดี เจ้าหน้าที่มีความสุข</p>
        </div>
    </div>

</body>
</html>
<?php include 'includes/footer.php'; ?>
