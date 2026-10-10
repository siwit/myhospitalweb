<?php
include 'includes/header.php';
?>

<style>
    /* ตกแต่ง Container หลัก */
    .schedule-container {
        padding: 20px;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    /* ตกแต่งตาราง */
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        font-family: 'Sarabun', sans-serif;
    }

    /* ส่วนหัวตาราง */
    .custom-table thead th {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        padding: 15px 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
    }

    /* ส่วนเนื้อหาตาราง */
    .custom-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #e2e8f0;
        /* เส้นทึบจางๆ สำหรับแบ่งแถวหลัก */
        text-align: center;
        line-height: 1.6;
        color: #334155;
        vertical-align: middle;
    }

    /* เส้นประสำหรับแบ่งเนื้อหาภายในเซลล์ (แทน <br>) */
    .sub-divider {
        border-top: 1px dashed #cbd5e1;
        margin: 8px 0;
        width: 100%;
    }

    /* แถวสลับสีอ่อนๆ */
    .custom-table tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    /* ไฮไลท์เมื่อเอาเมาส์วาง */
    .custom-table tbody tr:hover {
        background-color: #f1f5f9;
        transition: background-color 0.2s;
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .badge-info {
        display: inline-block;
        padding: 4px 10px;
        background: #e0f2fe;
        color: #0369a1;
        border-radius: 20px;
        font-size: 0.85em;
        border: 1px solid #bae6fd;
    }

    .dept-name {
        font-weight: 600;
        color: var(--primary-color);
    }

    .text-holiday {
        color: #e11d48;
        font-size: 0.9em;
    }
</style>

<div class="card mt-2 schedule-container">
    <h2 style="color: var(--primary-color); border-bottom: 2px solid var(--gray-200); padding-bottom: 10px; margin-bottom: 20px;">
        <i class="fas fa-calendar-alt"></i> ตารางการให้บริการ
    </h2>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>แผนก</th>
                    <th>วัน</th>
                    <th>เวลา</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="dept-name">ผู้ป่วยนอก ตรวจโรคทั่วไป</td>
                    <td>จันทร์ - ศุกร์</td>
                    <td>08.00 - 16.00 น.</td>
                    <td>หยุด เสาร์-อาทิตย์ และ วันหยุดนักขัตฤกษ์</td>
                </tr>
                <tr>
                    <td class="dept-name" style="color: #e11d48;">อุบัติเหตุฉุกเฉิน</td>
                    <td>ทุกวัน</td>
                    <td><strong>24 ชั่วโมง</strong></td>
                    <td> <!--  <span class="badge-info">พร้อมให้บริการตลอดเวลา</span>--> </td>
                </tr>
                <tr>
                    <td class="dept-name">ทันตกรรม</td>
                    <td>จันทร์ - อาทิตย์ <div class="sub-divider"></div> <small>คลินิกนอกเวลา อังคาร พุธ พฤหัสบดี</small> </td>
                    <td>
                        <div>08.00 - 16.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>16.00 - 20.00 น.</div>
                    </td>
                    <td>ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์</td>
                </tr>
                <tr>
                    <td class="dept-name">กายภาพบำบัด</td>
                    <td>จันทร์ - ศุกร์ <div class="sub-divider"></div> <small>คลินิกนอกเวลา จันทร์ - พฤหัสบดี</small> </td>
                    <td>
                        <div>08.00 - 16.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>16.00 - 20.00 น.</div>
                    </td>
                    <td>ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์ <div class="sub-divider"></div> <small>รับเฉพาะผู้ป่วยนัดผ่านแอปหมอพร้อม</small></td>
                </tr>
                <tr>
                    <td class="dept-name">แพทย์แผนไทย</td>
                    <td>จันทร์ - ศุกร์ <div class="sub-divider"></div> <small>คลินิกนอกเวลา พุธ - พฤหัสบดี</small> </td>
                    <td>
                        <div>08.00 - 16.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>16.00 - 20.00 น.</div>
                    </td>
                    <td>จันทร์ อังคาร พฤหัสบดี 08.00-12.00 งดให้บริการนวดรักษา <div class="sub-divider"></div> <small>รับเฉพาะผู้ป่วยนัดผ่านแอปหมอพร้อม</small></td>
                </tr>
                <tr>
                    <td class="dept-name">ฝากครรภ์รายเก่า</td>
                    <td>พุธ</td>
                    <td>08.00 - 12.00 น.</td>
                    <td>ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์</td>
                </tr>
                <tr>
                    <td class="dept-name">ฝากครรภ์รายใหม่</td>
                    <td>พฤหัสบดี</td>
                    <td>08.00 - 12.00 น.</td>
                    <td>ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์</td>
                </tr>
                <tr>
                    <td class="dept-name">ส่งเสริมสุขภาพ</td>
                    <td>จันทร์ คลินิกตรวจและกระตุ้นพัฒนาการ <div class="sub-divider"></div>อังคาร คลินิกวางแผนครอบครัว
                        <div class="sub-divider"></div> พุธ คลินิกวัคซีนผู้ใหญ่
                        <div class="sub-divider"></div> พฤหัสบดี คลินิกสุขภาพเด็กดี
                        <div class="sub-divider"></div> ศุกร์ คลินิกวัณโรค/คลินิกเติมภูมิ
                    </td>
                    <td>
                        <div>08.00 - 12.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>08.00 - 12.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>08.00 - 12.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>08.00 - 12.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>08.00 - 12.00 น.</div>
                    </td>
                    <td>ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์
                        <div class="sub-divider"></div>
                        ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์
                        <div class="sub-divider"></div>
                        ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์
                        <div class="sub-divider"></div>
                        ไม่เปิดให้บริการในวันหยุดนักขัตฤกษ์
                        <div class="sub-divider"></div>
                        เดือนละ 1 ครั้ง เฉพาะผู้ป่วยนัด
                    </td>

                </tr>
                <tr>
                    <td class="dept-name">จิตเวชและยาเสพติด</td>
                    <td>จันทร์-ศุกร์ ให้คำปรึกษาด้านสุขภาพจิต <div class="sub-divider"></div>จันทร์-ศุกร์ ให้คำปรึกษาด้านยาเสพติด
                        <div class="sub-divider"></div> พฤหัสบดี กระตุ้นพัฒนาการเด็ก
                    </td>
                    <td>
                        <div>08.00 - 16.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>08.00 - 16.00 น.</div>
                        <div class="sub-divider"></div>
                        <div>08.00 - 12.00 น.</div>
                    </td>
                    <td>หยุด เสาร์-อาทิตย์ และ วันหยุดนักขัตฤกษ์</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>