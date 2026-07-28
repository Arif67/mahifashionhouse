<?php
require_once '../Config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// ─── বাংলাদেশ টাইম হেল্পার (DB তে UTC সেভ হয়) ───
function bdDate($timestamp, $format) {
    try {
        $dt = new DateTime($timestamp, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Dhaka'));
        return $dt->format($format);
    } catch (Exception $e) {
        return date($format, strtotime($timestamp));
    }
}

// বাংলাদেশি "আজ" এর UTC রেঞ্জ
$bdTodayStart = "CONVERT_TZ(CURDATE(), '+06:00', '+00:00')";

// ─── স্ট্যাটস (টেবিল না থাকলে ০ দেখাবে, এরর হবে না) ───
$stats = [
    'total_visitors' => 0, 'today_visitors' => 0,
    'total_views' => 0, 'today_views' => 0,
    'total_carts' => 0, 'today_carts' => 0
];
$daily = [];
$pageStats = [];
$recentVisits = [];
$trackingReady = true;

try {
    $stats['total_visitors'] = intval(fetchOne("SELECT COUNT(DISTINCT device_token) as c FROM site_visits")['c'] ?? 0);
    $stats['today_visitors'] = intval(fetchOne("SELECT COUNT(DISTINCT device_token) as c FROM site_visits WHERE created_at >= $bdTodayStart")['c'] ?? 0);
    $stats['total_views']    = intval(fetchOne("SELECT COUNT(*) as c FROM site_visits")['c'] ?? 0);
    $stats['today_views']    = intval(fetchOne("SELECT COUNT(*) as c FROM site_visits WHERE created_at >= $bdTodayStart")['c'] ?? 0);
    $stats['total_carts']    = intval(fetchOne("SELECT COUNT(*) as c FROM site_visits WHERE event = 'cart_add'")['c'] ?? 0);
    $stats['today_carts']    = intval(fetchOne("SELECT COUNT(DISTINCT device_token) as c FROM site_visits WHERE event = 'cart_add' AND created_at >= $bdTodayStart")['c'] ?? 0);

    // শেষ ১৪ দিনের দৈনিক ভিউ + ভিজিটর (বাংলাদেশ টাইমে)
    $daily = fetchAll("SELECT
        DATE(CONVERT_TZ(created_at, '+00:00', '+06:00')) as d,
        COUNT(*) as views,
        COUNT(DISTINCT device_token) as visitors
        FROM site_visits
        WHERE created_at >= (UTC_TIMESTAMP() - INTERVAL 14 DAY)
        GROUP BY d ORDER BY d ASC");

    // কোন পেজে কত ভিজিটর
    $pageStats = fetchAll("SELECT page,
        COUNT(*) as views,
        COUNT(DISTINCT device_token) as visitors
        FROM site_visits
        GROUP BY page ORDER BY views DESC LIMIT 12");

    // সাম্প্রতিক ভিজিটর
    $recentVisits = fetchAll("SELECT * FROM site_visits ORDER BY id DESC LIMIT 15");
} catch (Exception $e) {
    $trackingReady = false;
}

// ১৪ দিনের গ্যাপ-মুক্ত সিরিজ তৈরি
$chartLabels = [];
$chartViews = [];
$chartVisitors = [];
$dailyMap = [];
foreach ($daily as $row) { $dailyMap[$row['d']] = $row; }
for ($i = 13; $i >= 0; $i--) {
    $d = (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->modify("-{$i} days")->format('Y-m-d');
    $chartLabels[] = bdDate($d . ' 00:00:00', 'd M');
    $chartViews[] = intval($dailyMap[$d]['views'] ?? 0);
    $chartVisitors[] = intval($dailyMap[$d]['visitors'] ?? 0);
}
$maxPageViews = 0;
foreach ($pageStats as $ps) { $maxPageViews = max($maxPageViews, intval($ps['views'])); }
if ($maxPageViews === 0) $maxPageViews = 1;

// পেজের সুন্দর নাম
$pageNames = [
    'index.php'      => 'হোম পেজ',
    'product.php'    => 'প্রোডাক্ট পেজ',
    'categories.php' => 'ক্যাটাগরি পেজ',
    'category.php'   => 'ক্যাটাগরি ভিউ',
    'cart.php'       => 'কার্ট পেজ',
    'checkout.php'   => 'চেকআউট পেজ'
];

$pendingCount = intval(fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")['c'] ?? 0);
$siteName = getSetting('site_name', 'Mahi Fashion House');
$adminName = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#667eea">
    <title>অ্যানালিটিক্স – <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #6C63FF;
            --primary-dark: #5a52d5;
            --primary-light: #8b83ff;
            --secondary: #FF6B6B;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
            --bg: #f0f2f5;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --shadow: 0 8px 30px rgba(108, 99, 255, 0.12);
            --shadow-lg: 0 12px 40px rgba(108, 99, 255, 0.18);
            --radius: 20px;
            --radius-sm: 14px;
            --sidebar-width: 280px;
            --bottom-nav-height: 72px;
            --header-height: 70px;
            --safe-top: env(safe-area-inset-top);
            --safe-bottom: env(safe-area-inset-bottom);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 16px);
            padding-top: var(--safe-top);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }

        .app { max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }

        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            padding: 16px 20px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 4px 24px rgba(102, 126, 234, 0.4);
            min-height: var(--header-height);
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .menu-btn {
            color: #fff; font-size: 20px; width: 42px; height: 42px; border-radius: 12px;
            background: rgba(255,255,255,0.15);
            display: none; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .menu-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.25); }
        .brand { display: flex; align-items: center; gap: 10px; color: #fff; }
        .brand i { font-size: 24px; }
        .brand h1 { font-size: 19px; font-weight: 700; letter-spacing: 0.3px; line-height: 1.2; }
        .brand span { font-size: 11px; font-weight: 400; opacity: 0.85; display: block; margin-top: -2px; }
        .admin-avatar {
            width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.20);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;
            color: #fff; border: 1.5px solid rgba(255,255,255,0.25);
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed; top: var(--header-height); left: 0; bottom: 0; width: var(--sidebar-width);
            background: #fff; box-shadow: 4px 0 30px rgba(0,0,0,0.06); overflow-y: auto;
            z-index: 90; transition: transform 0.35s cubic-bezier(0.32,0.72,0,1);
            border-right: 1px solid rgba(0,0,0,0.04); display: flex; flex-direction: column;
        }
        .sidebar-profile {
            padding: 24px 20px 20px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            position: relative; overflow: hidden;
        }
        .sidebar-profile::before { content: ''; position: absolute; top: -30px; right: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .profile-top { display: flex; align-items: center; gap: 14px; position: relative; z-index: 1; }
        .profile-avatar {
            width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px;
            color: #fff; border: 2px solid rgba(255,255,255,0.3);
        }
        .profile-info h4 { color: #fff; font-size: 16px; font-weight: 700; margin-bottom: 2px; }
        .profile-info p { color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 500; }
        .profile-status {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 8px;
            background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px;
            font-size: 11px; color: #fff; font-weight: 600;
        }
        .profile-status .dot { width: 8px; height: 8px; background: #4ade80; border-radius: 50%; }
        .sidebar-nav { flex: 1; padding: 16px 12px; }
        .sidebar .nav-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); padding: 12px 14px 6px; font-weight: 700; opacity: 0.7; }
        .sidebar .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 3px 0;
            border-radius: 14px; color: var(--text-secondary); font-weight: 500; font-size: 14px;
            transition: all 0.25s cubic-bezier(0.22,1,0.36,1); position: relative; overflow: hidden;
        }
        .sidebar .nav-item::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light)); opacity: 0.08;
            transition: width 0.3s; border-radius: 14px;
        }
        .sidebar .nav-item:hover::before { width: 100%; }
        .sidebar .nav-item i { width: 22px; font-size: 18px; text-align: center; }
        .sidebar .nav-item:hover { color: var(--primary); transform: translateX(4px); }
        .sidebar .nav-item.active {
            background: linear-gradient(135deg, rgba(108,99,255,0.12), rgba(108,99,255,0.06));
            color: var(--primary); font-weight: 600;
        }
        .sidebar .nav-item.active i { color: var(--primary); }
        .sidebar .nav-item .badge {
            margin-left: auto; background: linear-gradient(135deg, var(--secondary), #ff8e8e);
            color: #fff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px;
        }
        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(0,0,0,0.05); background: #fafbfc; }
        .sidebar-footer .nav-item { color: #dc3545; margin: 0; }
        .sidebar-bottom-info { padding: 12px 16px; text-align: center; font-size: 11px; color: var(--text-secondary); opacity: 0.6; border-top: 1px solid rgba(0,0,0,0.04); }
        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 85; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }

        .main-content { margin-left: var(--sidebar-width); padding: 24px 28px 100px; flex: 1; }

        /* ===== PAGE TITLE ===== */
        .page-head { margin-bottom: 22px; animation: fadeUp 0.5s ease forwards; }
        .page-head h2 { font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .page-head h2 .live-dot { width: 10px; height: 10px; background: #22C55E; border-radius: 50%; box-shadow: 0 0 0 0 rgba(34,197,94,0.5); animation: livePulse 1.6s infinite; }
        @keyframes livePulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.45); } 70% { box-shadow: 0 0 0 10px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        .page-head p { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

        /* ===== STAT CARDS ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
        .stat-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow); border: 1px solid rgba(108,99,255,0.05);
            position: relative; overflow: hidden;
            animation: fadeUp 0.5s ease forwards; opacity: 0;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .stat-card:nth-child(1) { animation-delay: 0.03s; }
        .stat-card:nth-child(2) { animation-delay: 0.06s; }
        .stat-card:nth-child(3) { animation-delay: 0.09s; }
        .stat-card:nth-child(4) { animation-delay: 0.12s; }
        .stat-card:nth-child(5) { animation-delay: 0.15s; }
        .stat-card:nth-child(6) { animation-delay: 0.18s; }
        .stat-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--card-accent, linear-gradient(90deg, var(--gradient-start), var(--gradient-end))); }
        .stat-card.green { --card-accent: linear-gradient(90deg, #0caa5e, #4ade80); }
        .stat-card.orange { --card-accent: linear-gradient(90deg, #f57c00, #ffb74d); }
        .stat-card.pink { --card-accent: linear-gradient(90deg, #EC407A, #F48FB1); }
        .stat-card.blue { --card-accent: linear-gradient(90deg, #4a6cf7, #6b8cff); }
        .stat-card.teal { --card-accent: linear-gradient(90deg, #00b894, #55efc4); }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            box-shadow: 0 6px 16px rgba(102,126,234,0.3); margin-bottom: 14px;
        }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #0caa5e, #4ade80); box-shadow: 0 6px 16px rgba(12,170,94,0.3); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f57c00, #ffb74d); box-shadow: 0 6px 16px rgba(245,124,0,0.3); }
        .stat-card.pink .stat-icon { background: linear-gradient(135deg, #EC407A, #F48FB1); box-shadow: 0 6px 16px rgba(236,64,122,0.3); }
        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #4a6cf7, #6b8cff); box-shadow: 0 6px 16px rgba(74,108,247,0.3); }
        .stat-card.teal .stat-icon { background: linear-gradient(135deg, #00b894, #55efc4); box-shadow: 0 6px 16px rgba(0,184,148,0.3); }
        .stat-value { font-size: 28px; font-weight: 700; line-height: 1.1; }
        .stat-label { font-size: 13px; color: var(--text-secondary); font-weight: 600; margin-top: 4px; }
        .stat-sub { font-size: 11px; color: var(--text-secondary); margin-top: 8px; display: flex; align-items: center; gap: 5px; opacity: 0.85; }
        .stat-sub i { font-size: 10px; }

        /* ===== CHART CARD ===== */
        .chart-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 22px 24px;
            box-shadow: var(--shadow); border: 1px solid rgba(108,99,255,0.05);
            margin-bottom: 20px; animation: fadeUp 0.5s ease 0.2s both;
        }
        .chart-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .chart-title { font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .chart-title i { width: 34px; height: 34px; border-radius: 10px; background: rgba(108,99,255,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .chart-legend { display: flex; gap: 14px; font-size: 12px; color: var(--text-secondary); font-weight: 600; }
        .chart-legend .lg { display: flex; align-items: center; gap: 6px; }
        .chart-legend .dot { width: 10px; height: 10px; border-radius: 3px; }
        .chart-wrap { position: relative; height: 300px; }

        /* ===== PAGE-WISE TABLE ===== */
        .pages-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 22px 24px;
            box-shadow: var(--shadow); border: 1px solid rgba(108,99,255,0.05);
            margin-bottom: 20px; animation: fadeUp 0.5s ease 0.25s both;
        }
        .page-row { display: flex; align-items: center; gap: 14px; padding: 13px 0; border-bottom: 1px solid #f0f2f5; }
        .page-row:last-child { border-bottom: none; }
        .page-icon { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.06)); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .page-info { flex: 1; min-width: 0; }
        .page-name { font-size: 14px; font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
        .page-name small { font-size: 11px; color: var(--text-secondary); font-weight: 500; }
        .page-bar-track { height: 7px; background: #f0f2f5; border-radius: 10px; overflow: hidden; }
        .page-bar-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end)); width: 0; transition: width 1s cubic-bezier(0.22,1,0.36,1); }
        .page-nums { text-align: right; flex-shrink: 0; }
        .page-views { font-size: 16px; font-weight: 700; color: var(--primary); }
        .page-visitors { font-size: 11px; color: var(--text-secondary); }

        /* ===== RECENT VISITORS ===== */
        .recent-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 22px 24px;
            box-shadow: var(--shadow); border: 1px solid rgba(108,99,255,0.05);
            animation: fadeUp 0.5s ease 0.3s both;
        }
        .visit-row { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid #f0f2f5; }
        .visit-row:last-child { border-bottom: none; }
        .visit-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .visit-avatar.cart { background: linear-gradient(135deg, #f57c00, #ffb74d); }
        .visit-info { flex: 1; min-width: 0; }
        .visit-page { font-size: 13px; font-weight: 700; }
        .visit-meta { font-size: 11px; color: var(--text-secondary); display: flex; gap: 8px; flex-wrap: wrap; }
        .visit-time { font-size: 11px; color: var(--text-secondary); flex-shrink: 0; text-align: right; }
        .event-pill { font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px; flex-shrink: 0; }
        .event-pill.view { background: rgba(108,99,255,0.1); color: var(--primary); }
        .event-pill.cart_add { background: rgba(245,124,0,0.12); color: #f57c00; }

        /* ===== EMPTY / NOTICE ===== */
        .notice-card {
            background: linear-gradient(135deg, #fff8e6, #fff); border: 1px solid #ffe0a3;
            border-radius: var(--radius); padding: 18px 20px; margin-bottom: 20px;
            display: flex; gap: 12px; align-items: flex-start; animation: fadeUp 0.5s ease both;
        }
        .notice-card i { color: #f57c00; font-size: 20px; margin-top: 2px; }
        .notice-card b { display: block; margin-bottom: 4px; }
        .notice-card code { background: #fff; border: 1px solid #ffe0a3; padding: 2px 8px; border-radius: 6px; font-size: 12px; }
        .empty-state { text-align: center; padding: 40px 0; color: var(--text-secondary); }
        .empty-state i { font-size: 44px; color: #ddd; display: block; margin-bottom: 12px; }

        /* ===== BOTTOM NAV ===== */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            display: none; align-items: center; justify-content: space-around;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.08); border-top: 1px solid rgba(0,0,0,0.04); z-index: 200;
        }
        .bottom-nav .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            color: var(--text-secondary); font-size: 10px; font-weight: 600;
            padding: 6px 10px; border-radius: 14px; transition: all 0.2s; position: relative; min-width: 56px; flex: 1;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--primary); }
        .bottom-nav .nav-item.active::after { content: ''; position: absolute; bottom: -2px; width: 20px; height: 4px; background: var(--primary); border-radius: 4px; }
        .bottom-nav .nav-item .badge { position: absolute; top: 0; right: 6px; background: var(--secondary); color: #fff; font-size: 9px; font-weight: 700; padding: 1px 7px; border-radius: 20px; line-height: 1.5; }

        @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            :root { --sidebar-width: 300px; --header-height: 60px; --bottom-nav-height: 68px; }
            .menu-btn { display: flex; }
            .sidebar { top: 0; transform: translateX(-100%); width: var(--sidebar-width); border-radius: 0 24px 24px 0; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px 16px 90px; }
            .bottom-nav { display: flex; }
            .header { padding: 10px 14px; min-height: var(--header-height); }
            .brand h1 { font-size: 16px; }
            .brand span { font-size: 10px; }
            .stats-grid { gap: 10px; }
            .stat-card { padding: 16px; }
            .stat-value { font-size: 22px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; margin-bottom: 10px; }
            .chart-wrap { height: 240px; }
            .chart-card, .pages-card, .recent-card { padding: 16px 18px; border-radius: var(--radius-sm); }
            .page-head h2 { font-size: 20px; }
        }
        @media (max-width: 420px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-value { font-size: 19px; }
            .stat-label { font-size: 11px; }
        }
        @keyframes fadeUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
<div class="app">

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <div class="brand">
                <i class="fas fa-store-alt"></i>
                <div><h1><?php echo $siteName; ?></h1><span>Admin Panel</span></div>
            </div>
        </div>
        <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-profile">
            <div class="profile-top">
                <div class="profile-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
                <div class="profile-info">
                    <h4><?php echo $adminName; ?></h4>
                    <p>Administrator</p>
                    <div class="profile-status"><span class="dot"></span><span>Online</span></div>
                </div>
            </div>
        </div>
        <div class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="orders.php" class="nav-item">
                <i class="fas fa-shopping-bag"></i> <span>Orders</span>
                <?php if ($pendingCount > 0): ?><span class="badge"><?php echo $pendingCount; ?></span><?php endif; ?>
            </a>
            <a href="products.php" class="nav-item"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> <span>Categories</span></a>
            <a href="analytics.php" class="nav-item active"><i class="fas fa-chart-line"></i> <span>Analytics</span></a>
            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item"><i class="fas fa-image"></i> <span>Banners</span></a>
            <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> <span>Settings</span></a>
            <div class="sidebar-footer" style="margin-top:8px;">
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </div>
        <div class="sidebar-bottom-info">v1.0.0 &middot; Mahi Fashion House</div>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="main-content">

        <div class="page-head">
            <h2><span class="live-dot"></span> ভিজিটর অ্যানালিটিক্স</h2>
            <p>রিয়েল ভিজিটর, পেজ ভিউ ও কার্ট অ্যাক্টিভিটি — লাইভ ডেটা (বাংলাদেশ টাইম)</p>
        </div>

        <?php if (!$trackingReady): ?>
        <div class="notice-card">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <b>ট্র্যাকিং এখনো চালু হয়নি</b>
                ১) phpMyAdmin-এ <code>site_visits</code> টেবিলের SQL রান করুন &nbsp;২) রুটে <code>track.php</code> আপলোড করুন &nbsp;৩) <code>Config.php</code> এর শেষে <code>include_once __DIR__ . '/track.php';</code> লাইনটি যোগ করুন।
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== STAT CARDS ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_visitors']); ?></div>
                <div class="stat-label">মোট ভিজিটর</div>
                <div class="stat-sub"><i class="fas fa-fingerprint"></i> ইউনিক ডিভাইস ধরে হিসাব</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                <div class="stat-value"><?php echo number_format($stats['today_visitors']); ?></div>
                <div class="stat-label">আজকের ভিজিটর</div>
                <div class="stat-sub"><i class="fas fa-calendar-day"></i> <?php echo bdDate(gmdate('Y-m-d H:i:s'), 'd M Y'); ?></div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-eye"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_views']); ?></div>
                <div class="stat-label">মোট পেজ ভিউ</div>
                <div class="stat-sub"><i class="fas fa-globe"></i> সব পাবলিক পেজ মিলে</div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value"><?php echo number_format($stats['today_views']); ?></div>
                <div class="stat-label">আজকের পেজ ভিউ</div>
                <div class="stat-sub"><i class="fas fa-bolt"></i> আজকের টোটাল হিট</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-cart-plus"></i></div>
                <div class="stat-value"><?php echo number_format($stats['today_carts']); ?></div>
                <div class="stat-label">আজ কার্ট করেছেন</div>
                <div class="stat-sub"><i class="fas fa-user-check"></i> ইউনিক ভিজিটর কার্টে যোগ দিয়েছে</div>
            </div>
            <div class="stat-card pink">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_carts']); ?></div>
                <div class="stat-label">মোট কার্ট অ্যাড</div>
                <div class="stat-sub"><i class="fas fa-history"></i> শুরু থেকে সব কার্ট অ্যাকশন</div>
            </div>
        </div>

        <!-- ===== DAILY GRAPH ===== -->
        <div class="chart-card">
            <div class="chart-head">
                <div class="chart-title"><i class="fas fa-chart-area"></i> দৈনিক ভিউ ও ভিজিটর (শেষ ১৪ দিন)</div>
                <div class="chart-legend">
                    <span class="lg"><span class="dot" style="background:#667eea;"></span> পেজ ভিউ</span>
                    <span class="lg"><span class="dot" style="background:#22C55E;"></span> ভিজিটর</span>
                </div>
            </div>
            <div class="chart-wrap"><canvas id="visitsChart"></canvas></div>
        </div>

        <!-- ===== PAGE-WISE ===== -->
        <div class="pages-card">
            <div class="chart-head">
                <div class="chart-title"><i class="fas fa-file-alt"></i> কোন পেজে কত ভিজিটর</div>
            </div>
            <?php if (!empty($pageStats)): ?>
                <?php foreach ($pageStats as $ps):
                    $nm = $pageNames[$ps['page']] ?? $ps['page'];
                    $pct = round((intval($ps['views']) / $maxPageViews) * 100);
                    $icon = 'fa-file';
                    if ($ps['page'] === 'index.php') $icon = 'fa-house';
                    elseif ($ps['page'] === 'product.php') $icon = 'fa-shirt';
                    elseif (strpos($ps['page'], 'categor') === 0) $icon = 'fa-border-all';
                    elseif ($ps['page'] === 'cart.php') $icon = 'fa-cart-shopping';
                    elseif ($ps['page'] === 'checkout.php') $icon = 'fa-credit-card';
                ?>
                <div class="page-row">
                    <div class="page-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                    <div class="page-info">
                        <div class="page-name"><?php echo $nm; ?> <small><?php echo $ps['page']; ?></small></div>
                        <div class="page-bar-track"><div class="page-bar-fill" data-w="<?php echo $pct; ?>"></div></div>
                    </div>
                    <div class="page-nums">
                        <div class="page-views"><?php echo number_format($ps['views']); ?></div>
                        <div class="page-visitors"><?php echo number_format($ps['visitors']); ?> ভিজিটর</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-chart-bar"></i><p>এখনো কোনো ডেটা নেই — ট্র্যাকিং চালু হলে এখানে দেখাবে</p></div>
            <?php endif; ?>
        </div>

        <!-- ===== RECENT VISITORS ===== -->
        <div class="recent-card">
            <div class="chart-head">
                <div class="chart-title"><i class="fas fa-stream"></i> সাম্প্রতিক ভিজিটর অ্যাক্টিভিটি</div>
            </div>
            <?php if (!empty($recentVisits)): ?>
                <?php foreach ($recentVisits as $v):
                    $nm = $pageNames[$v['page']] ?? $v['page'];
                    $isCart = ($v['event'] === 'cart_add');
                ?>
                <div class="visit-row">
                    <div class="visit-avatar <?php echo $isCart ? 'cart' : ''; ?>">
                        <i class="fas <?php echo $isCart ? 'fa-cart-plus' : 'fa-user'; ?>"></i>
                    </div>
                    <div class="visit-info">
                        <div class="visit-page"><?php echo $nm; ?></div>
                        <div class="visit-meta">
                            <span><i class="fas fa-network-wired"></i> <?php echo clean($v['ip_address']); ?></span>
                            <span><i class="fas fa-fingerprint"></i> <?php echo substr($v['device_token'], 0, 10); ?>…</span>
                        </div>
                    </div>
                    <span class="event-pill <?php echo $v['event']; ?>"><?php echo $isCart ? 'কার্ট অ্যাড' : 'পেজ ভিউ'; ?></span>
                    <div class="visit-time">
                        <?php echo bdDate($v['created_at'], 'd M'); ?><br>
                        <?php echo bdDate($v['created_at'], 'h:i A'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-users"></i><p>এখনো কোনো ভিজিটর ডেটা নেই</p></div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ===== BOTTOM NAV ===== -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item"><i class="fas fa-home"></i><span>ড্যাশ</span></a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-shopping-bag"></i><span>অর্ডার</span>
            <?php if ($pendingCount > 0): ?><span class="badge"><?php echo $pendingCount; ?></span><?php endif; ?>
        </a>
        <a href="analytics.php" class="nav-item active"><i class="fas fa-chart-line"></i><span>ভিজিটর</span></a>
        <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i><span>সেটিংস</span></a>
        <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>লগআউট</span></a>
    </nav>
</div>

<script>
// সাইডবার টগল
(function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuToggle = document.getElementById('menuToggle');
    function openSidebar() { sidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    menuToggle.addEventListener('click', function(e) { e.stopPropagation(); sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
    overlay.addEventListener('click', closeSidebar);
})();

// পেজ বার এনিমেশন
document.querySelectorAll('.page-bar-fill').forEach(function(bar) {
    setTimeout(function() { bar.style.width = bar.dataset.w + '%'; }, 300);
});

// চার্ট
const ctx = document.getElementById('visitsChart').getContext('2d');
const gradViews = ctx.createLinearGradient(0, 0, 0, 280);
gradViews.addColorStop(0, 'rgba(102, 126, 234, 0.35)');
gradViews.addColorStop(1, 'rgba(102, 126, 234, 0.02)');
const gradVisitors = ctx.createLinearGradient(0, 0, 0, 280);
gradVisitors.addColorStop(0, 'rgba(34, 197, 94, 0.30)');
gradVisitors.addColorStop(1, 'rgba(34, 197, 94, 0.02)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [
            {
                label: 'পেজ ভিউ',
                data: <?php echo json_encode($chartViews); ?>,
                borderColor: '#667eea',
                backgroundColor: gradViews,
                fill: true,
                tension: 0.45,
                borderWidth: 2.5,
                pointRadius: 3,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5
            },
            {
                label: 'ভিজিটর',
                data: <?php echo json_encode($chartVisitors); ?>,
                borderColor: '#22C55E',
                backgroundColor: gradVisitors,
                fill: true,
                tension: 0.45,
                borderWidth: 2.5,
                pointRadius: 3,
                pointBackgroundColor: '#22C55E',
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(26, 26, 46, 0.92)',
                padding: 12,
                cornerRadius: 10,
                titleFont: { family: "'Hind Siliguri', sans-serif", weight: '700' },
                bodyFont: { family: "'Hind Siliguri', sans-serif" }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: "'Hind Siliguri', sans-serif", size: 11 }, color: '#9ca3af' }
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { precision: 0, font: { family: "'Hind Siliguri', sans-serif", size: 11 }, color: '#9ca3af' }
            }
        }
    }
});
</script>
</body>
</html>