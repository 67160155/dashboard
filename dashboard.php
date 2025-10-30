<?php
// ===== START: 1. เพิ่มระบบ Session และ Logout (ในไฟล์เดียว) =====
session_start(); // เริ่ม session ที่ด้านบนสุดเสมอ

// 🚀 [ส่วนที่ 1/2] ตรวจจับคำสั่ง Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset(); // ลบตัวแปร session ทั้งหมด
    session_destroy(); // ทำลาย session
    
    // ส่งกลับไปหน้า login.php
    header("Location: login.php"); 
    exit();
}

// ตรวจสอบการ Login (ถ้าไม่ใช่การ Logout)
if (!isset($_SESSION['user_id'])) {
    
    // ตั้งค่าข้อความสำหรับแสดงในหน้า login.php
    $_SESSION['flash'] = 'กรุณา login เพื่อเข้าสู่ Dashboard';
    
    // ส่งกลับไปหน้า login.php
    header("Location: login.php"); 
    exit();
}
// ===== END: 1. เพิ่มระบบ Session และ Logout =====


// dashboard.php (โค้ดเดิม)
// 1. ดึงไฟล์ config และเชื่อมต่อฐานข้อมูล
require_once 'config_mysqli.php'; 
// Note: $mysqli object is now available, or the script exited on error.

// ข้อมูลเริ่มต้น
$data = [
    'monthly' => [],
    'category' => [],
    'region' => [],
    'topProducts' => [],
    'payment' => [],
    'hourly' => [],
    'newReturning' => [],
    'kpi' => ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0],
    'error' => null
];

try {
    function q($db, $sql) {
        $res = $db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // 2. ดึงข้อมูลสำหรับแผนภูมิต่างๆ
    $data['monthly'] = q($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
    $data['category'] = q($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
    $data['region'] = q($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
    $data['topProducts'] = q($mysqli, "SELECT product_name, qty_sold FROM v_top_products");
    $data['payment'] = q($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
    $data['hourly'] = q($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
    $data['newReturning'] = q($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

    // 3. ดึงข้อมูล KPI 30 วัน
    $kpi = q($mysqli, "
        SELECT SUM(net_amount) sales_30d, SUM(quantity) qty_30d, COUNT(DISTINCT customer_id) buyers_30d
        FROM fact_sales
        WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    ");
    if ($kpi && !empty($kpi)) $data['kpi'] = $kpi[0];

} catch (Exception $e) {
    // จัดการข้อผิดพลาดในการคิวรี่ฐานข้อมูล
    $data['error'] = 'Database Query Error: ' . $e->getMessage();
}

// 4. Function สำหรับจัดรูปแบบตัวเลข (Number Format)
function nf($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="th" data-bs-theme="dark"> 
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW — Modern Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@500;600;700&display=swap" rel="stylesheet">

<style>
/* CSS: ธีม "Modern Glassmorphism" + ฟอนต์ Kanit */
body {
    background: radial-gradient(circle at 10% 20%, #1a1a2e, #0f0f1a 80%);
    color: #e0e0e0;
    font-family: 'Kanit', sans-serif;
    min-height: 100vh;
}
h2 { color: #c77dff; font-weight: 700; } 
h5 { 
    font-size: 1.25rem; 
    font-weight: 600; 
    color: #ffffff;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 0.5rem; 
    margin-bottom: 1rem; 
} 
.card {
    background: rgba(26, 26, 46, 0.6);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
    transition: all 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.3);
}
.kpi-card {
    text-align: center;
    padding: 1.5rem 1rem;
}
.kpi-title {
    font-size: 1rem;
    font-weight: 500;
    color: #c77dff;
    margin-bottom: 0.5rem;
}
.kpi-value {
    font-size: 2.2rem;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.2;
}
canvas { max-height: 400px; } 
footer { 
    text-align: center; 
    font-size: 0.8rem; 
    color: #aaa;
    margin-top: 2rem; 
    padding-top: 1rem; 
    border-top: 1px solid rgba(255,255,255,0.05); 
}
h2 i { color: #00f2ea; }
</style>
</head>
<body class="p-4">

<div class="container-fluid">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        
        <div style="flex: 1;">
            </div>

        <div style="flex: 1;" class="text-center">
            <h2>Retail DW Analytics Dashboard</h2>
        </div>
        
        <div style="flex: 1;" class="d-flex justify-content-end align-items-center">
            <span class="text-secondary small me-3"><i class="bi bi-calendar-check me-1"></i>อัพเดตล่าสุด: <?= date("d M Y") ?></span>
            
            <a href="dashboard.php?action=logout" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
    <?php if (isset($mysqli) && $mysqli->connect_error): ?>
        <div class="alert alert-danger">Database Connection Error: <?= htmlspecialchars($mysqli->connect_error) ?></div>
    <?php elseif ($data['error']): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($data['error']) ?></div>
    <?php else: ?>
    <div class="row g-4 mb-5">
        <div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-title"><i class="bi bi-currency-dollar me-2"></i>ยอดขาย 30 วัน</div>
            <div class="kpi-value">฿<?= nf($data['kpi']['sales_30d']) ?></div>
        </div></div>
        <div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-title"><i class="bi bi-box me-2"></i>จำนวนชิ้นขาย</div>
            <div class="kpi-value"><?= number_format((int)$data['kpi']['qty_30d']) ?> ชิ้น</div>
        </div></div>
        <div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-title"><i class="bi bi-people-fill me-2"></i>ผู้ซื้อ (30 วัน)</div>
            <div class="kpi-value"><?= number_format((int)$data['kpi']['buyers_30d']) ?> คน</div>
        </div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8"><div class="card p-4">
            <h5><i class="bi bi-graph-up me-2"></i>ยอดขายรายเดือน</h5><canvas id="monthlyChart"></canvas>
        </div></div>
        <div class="col-lg-4"><div class="card p-4">
            <h5><i class="bi bi-tags-fill me-2"></i>ยอดขายตามหมวด</h5><canvas id="categoryChart"></canvas>
        </div></div>

        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-geo-alt-fill me-2"></i>ยอดขายตามภูมิภาค</h5><canvas id="regionChart"></canvas>
        </div></div>
        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-star-fill me-2"></i>สินค้าขายดี</h5><canvas id="topChart"></canvas>
        </div></div>

        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-credit-card-2-front-fill me-2"></i>วิธีการชำระเงิน</h5><canvas id="payChart"></canvas>
        </div></div>
        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-clock-fill me-2"></i>ยอดขายรายชั่วโมง</h5><canvas id="hourChart"></canvas>
        </div></div>

        <div class="col-12"><div class="card p-4">
            <h5><i class="bi bi-person-lines-fill me-2"></i>ลูกค้าใหม่ vs ลูกค้าเดิม</h5><canvas id="custChart"></canvas>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<footer>© <?= date("Y") ?> Retail DW Analytics Dashboard. All rights reserved.</footer>

<script>
// JavaScript/Chart.js Configuration (ธีม Dark Mode)
const d = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
const ctx = id => document.getElementById(id);
const chartContext = id => ctx(id) ? ctx(id).getContext('2d') : null; 
const toXY = (a, x, y) => ({labels:a.map(o=>o[x]),values:a.map(o=>parseFloat(o[y]))});

// 🚀 สี Neon/Vibrant สำหรับธีมมืด
const COLOR_CYAN = '#00f2ea';
const COLOR_PURPLE = '#c77dff';
const COLOR_PINK = '#f9a8d4';
const COLOR_RED = '#ff5c5c';
const COLOR_YELLOW = '#fde047';
const COLOR_GRID = 'rgba(255, 255, 255, 0.1)';
const COLOR_TEXT = '#e0e0e0';
const COLOR_TEXT_DIM = '#aaa';

// Base Options สำหรับแผนภูมิ (ปรับสำหรับ Dark Mode)
const baseOpt = {
    responsive:true,
    maintainAspectRatio: false,
    plugins:{
        legend:{
            labels:{
                color: COLOR_TEXT, // 🚀 สีตัวอักษร Legend
                boxWidth: 15,
                padding: 15
            }
        },
        tooltip:{
            backgroundColor:'#0f0f1a', // 🚀 พื้นหลัง Tooltip (สีเข้ม)
            titleColor: COLOR_CYAN, // 🚀 สีหัวข้อ Tooltip
            bodyColor: COLOR_TEXT, // 🚀 สีเนื้อหา Tooltip
            borderColor: 'rgba(255,255,255,0.2)',
            borderWidth: 1
        }
    },
    scales:{
        x:{
            grid:{ color: COLOR_GRID }, // 🚀 สีเส้น Grid
            ticks:{ color: COLOR_TEXT_DIM } // 🚀 สีตัวอักษรแกน X
        },
        y:{
            grid:{ color: COLOR_GRID }, // 🚀 สีเส้น Grid
            ticks:{ color: COLOR_TEXT_DIM } // 🚀 สีตัวอักษรแกน Y
        }
    },
    animation:{ duration:1200, easing:'easeOutCubic' }
};

// Monthly Chart (Line)
(() => {
    const context = chartContext('monthlyChart');
    if (!context) return;
    const {labels,values} = toXY(d.monthly,'ym','net_sales');
    new Chart(context, {
        type:'line',
        data:{ labels, datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            borderColor: COLOR_CYAN, // 🚀 สี Cyan
            backgroundColor: 'rgba(0, 242, 234, 0.2)', // 🚀 สี Cyan (โปร่งแสง)
            pointBackgroundColor: '#0f0f1a',
            pointBorderColor: COLOR_CYAN,
            pointRadius: 4,
            fill:true,
            tension:0.4
        }]},
        options:baseOpt
    });
})();

// Category Chart (Doughnut)
(() => {
    const context = chartContext('categoryChart');
    if (!context) return;
    const {labels,values}=toXY(d.category,'category','net_sales');
    new Chart(context, {
        type:'doughnut',
        data:{labels,datasets:[{
            data:values,
            backgroundColor:[COLOR_CYAN, COLOR_PURPLE, COLOR_RED, COLOR_YELLOW, COLOR_PINK], // 🚀 ชุดสี Vibrant
            hoverOffset: 10,
            borderColor: 'rgba(26, 26, 46, 0.8)' // 🚀 สีขอบ (เหมือนพื้นหลังการ์ด)
        }]},
        options:{...baseOpt,
            scales:{ x:{display:false}, y:{display:false} },
            plugins:{...baseOpt.plugins,legend:{position:'right', labels:{color:COLOR_TEXT}}}
        }
    });
})();

// Top products Chart (Vertical Bar)
(() => {
    const context = chartContext('topChart');
    if (!context) return;
    const labels=d.topProducts.map(o=>o.product_name);
    const vals=d.topProducts.map(o=>parseFloat(o.qty_sold) || 0); 

    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ชิ้นขาย',
            data:vals,
            backgroundColor: COLOR_PURPLE, // 🚀 สีม่วง
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// Region Chart (Bar)
(() => {
    const context = chartContext('regionChart');
    if (!context) return;
    const {labels,values}=toXY(d.region,'region','net_sales');
    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            backgroundColor: COLOR_CYAN, // 
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// Payment Chart (Pie)
(() => {
    const context = chartContext('payChart');
    if (!context) return;
    const {labels,values}=toXY(d.payment,'payment_method','net_sales');
    new Chart(context, {
        type:'pie',
        data:{labels,datasets:[{
            data:values,
            // 🚀 ✅✅✅ แก้ไขบั๊กจาก YELLOW เป็น COLOR_YELLOW ตรงนี้ครับ ✅✅✅
            backgroundColor:[COLOR_CYAN, COLOR_PURPLE, COLOR_RED, COLOR_YELLOW, COLOR_PINK], 
            hoverOffset: 10,
            borderColor: 'rgba(26, 26, 46, 0.8)' // 🚀 สีขอบ
        }]},
        options:{...baseOpt,
            scales:{ x:{display:false}, y:{display:false} },
            plugins:{...baseOpt.plugins,legend:{position:'right', labels:{color:COLOR_TEXT}}}
        }
    });
})();

// Hourly Chart (Bar)
(() => {
    const context = chartContext('hourChart');
    if (!context) return;
    const {labels,values}=toXY(d.hourly,'hour_of_day','net_sales');
    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            backgroundColor: COLOR_PINK, // 🚀 สีชมพู
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// New vs Returning Chart (Line)
(() => {
    const context = chartContext('custChart');
    if (!context) return;
    const labels=d.newReturning.map(o=>o.date_key);
    const n=d.newReturning.map(o=>parseFloat(o.new_customer_sales));
    const r=d.newReturning.map(o=>parseFloat(o.returning_sales));
    new Chart(context,{
        type:'line',
        data:{labels,datasets:[
            {label:'ลูกค้าใหม่',data:n,borderColor:COLOR_YELLOW,tension:0.4, fill:false, pointRadius: 3}, // 🚀 สีเหลือง
            {label:'ลูกค้าเดิม',data:r,borderColor:COLOR_RED,tension:0.4, fill:false, pointRadius: 3} // 🚀 สีแดงสว่าง
        ]},
        options:baseOpt
    });
})();
</script>
</body>
</html>