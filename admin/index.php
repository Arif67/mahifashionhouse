<?php
require_once '../Config.php';

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get analytics data
try {
    $totalOrders = fetchOne("SELECT COUNT(*) as c FROM orders")['c'] ?? 0;
    $pendingOrders = fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")['c'] ?? 0;
    $totalSales = fetchOne("SELECT SUM(total_amount) as s FROM orders WHERE status != 'cancelled'")['s'] ?? 0;
    $totalProducts = fetchOne("SELECT COUNT(*) as c FROM products WHERE status = 1")['c'] ?? 0;
    
    $recentOrders = fetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10");
    
    $monthlySales = fetchAll("SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as order_count,
        SUM(total_amount) as revenue
        FROM orders WHERE status != 'cancelled'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC LIMIT 6");
    
} catch (Exception $e) {
    $totalOrders = $pendingOrders = $totalSales = $totalProducts = 0;
    $recentOrders = [];
    $monthlySales = [];
}

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
    <title>ড্যাশবোর্ড – <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== ROOT VARIABLES ===== */
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
            --bottom-nav-height: 68px;
            --header-height: 70px;
            --safe-top: env(safe-area-inset-top);
            --safe-bottom: env(safe-area-inset-bottom);
        }

        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 16px);
            padding-top: var(--safe-top);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; touch-action: manipulation; }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }

        /* ===== APP SHELL ===== */
        .app {
            max-width: 1400px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 24px rgba(102, 126, 234, 0.4);
            min-height: var(--header-height);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .menu-btn {
            color: #fff;
            font-size: 20px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.22,1,0.36,1);
            display: none;
        }
        .menu-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.25); }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
        }
        .brand i { font-size: 24px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .brand h1 {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }
        .brand span {
            font-size: 11px;
            font-weight: 400;
            opacity: 0.85;
            display: block;
            margin-top: -2px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-btn {
            color: #fff;
            font-size: 18px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.2s;
        }
        .header-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.22); }
        .badge-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            background: var(--secondary);
            border-radius: 50%;
            border: 2px solid var(--gradient-start);
            box-shadow: 0 0 0 2px rgba(255,107,107,0.3);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255,255,255,0.20);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            backdrop-filter: blur(10px);
            border: 1.5px solid rgba(255,255,255,0.25);
            transition: transform 0.2s;
        }
        .admin-avatar:active { transform: scale(0.9); }

        /* ===== SIDEBAR (Redesigned) ===== */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: #fff;
            box-shadow: 4px 0 30px rgba(0,0,0,0.06);
            padding: 0;
            overflow-y: auto;
            z-index: 90;
            transition: transform 0.35s cubic-bezier(0.32,0.72,0,1);
            border-right: 1px solid rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }

        /* Sidebar Profile Header */
        .sidebar-profile {
            padding: 24px 20px 20px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            position: relative;
            overflow: hidden;
        }
        .sidebar-profile::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .sidebar-profile::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: -20px;
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .profile-top {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            z-index: 1;
        }
        .profile-avatar {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .profile-info h4 {
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .profile-info p {
            color: rgba(255,255,255,0.8);
            font-size: 12px;
            font-weight: 500;
        }
        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            color: #fff;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .profile-status .dot {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(74,222,128,0.3);
        }

        /* Sidebar Navigation */
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }
        .sidebar .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            padding: 12px 14px 6px;
            font-weight: 700;
            opacity: 0.7;
        }
        .sidebar .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin: 3px 0;
            border-radius: 14px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.25s cubic-bezier(0.22,1,0.36,1);
            position: relative;
            overflow: hidden;
        }
        .sidebar .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0.08;
            transition: width 0.3s;
            border-radius: 14px;
        }
        .sidebar .nav-item:hover::before { width: 100%; }
        .sidebar .nav-item i {
            width: 22px;
            font-size: 18px;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar .nav-item:hover {
            color: var(--primary);
            transform: translateX(4px);
        }
        .sidebar .nav-item:hover i { transform: scale(1.1); }
        .sidebar .nav-item.active {
            background: linear-gradient(135deg, rgba(108,99,255,0.12), rgba(108,99,255,0.06));
            color: var(--primary);
            font-weight: 600;
            box-shadow: 0 2px 12px rgba(108,99,255,0.1);
        }
        .sidebar .nav-item.active i {
            color: var(--primary);
        }
        .sidebar .nav-item .badge {
            margin-left: auto;
            background: linear-gradient(135deg, var(--secondary), #ff8e8e);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(255,107,107,0.3);
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid rgba(0,0,0,0.05);
            background: #fafbfc;
        }
        .sidebar-footer .nav-item {
            color: #dc3545;
            margin: 0;
        }
        .sidebar-footer .nav-item:hover {
            background: rgba(220, 53, 69, 0.06);
            color: #dc3545;
            transform: translateX(4px);
        }
        .sidebar-footer .nav-item::before {
            background: rgba(220, 53, 69, 0.08);
        }

        /* Sidebar Bottom Info */
        .sidebar-bottom-info {
            padding: 12px 16px;
            text-align: center;
            font-size: 11px;
            color: var(--text-secondary);
            opacity: 0.6;
            border-top: 1px solid rgba(0,0,0,0.04);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px 100px;
            flex: 1;
            transition: margin 0.3s;
        }

        /* ===== GREETING ===== */
        .greeting {
            margin-bottom: 4px;
        }
        .greeting h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .greeting p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }
        .greeting-date {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
            opacity: 0.8;
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin: 20px 0 24px;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 20px 18px;
            box-shadow: var(--shadow);
            transition: all 0.25s cubic-bezier(0.22,1,0.36,1);
            border: 1px solid rgba(108,99,255,0.04);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-card:hover::after { opacity: 1; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .stat-card:active { transform: scale(0.98); }
        .stat-card .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .stat-card .icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            transition: transform 0.3s;
        }
        .stat-card:hover .icon { transform: scale(1.1) rotate(-5deg); }
        .icon.blue { background: linear-gradient(135deg, #e8edfd, #d4e0ff); color: #4a6cf7; }
        .icon.orange { background: linear-gradient(135deg, #fef0e6, #ffe4d1); color: #f57c00; }
        .icon.green { background: linear-gradient(135deg, #e6f7ed, #d1f5e0); color: #0caa5e; }
        .icon.purple { background: linear-gradient(135deg, #f0eafe, #e4d5ff); color: #7b3fe4; }
        .stat-card .label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .stat-card .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 2px;
            letter-spacing: -0.5px;
        }
        .stat-trend {
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .trend-up { color: #0caa5e; }
        .trend-down { color: #e74c3c; }

        /* ===== CHART ===== */
        .chart-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px 22px 18px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            border: 1px solid rgba(108,99,255,0.04);
            overflow: hidden;
        }
        .chart-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .chart-card .card-header h3 {
            font-size: 17px;
            font-weight: 600;
        }
        .chart-card .card-header .period {
            font-size: 12px;
            color: var(--text-secondary);
            background: var(--bg);
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 500;
        }
        .chart-scroll-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -6px;
            padding: 0 6px;
        }
        .chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            height: 180px;
            padding-top: 8px;
            min-width: 100%;
        }
        .chart-bar-wrapper {
            flex: 1;
            min-width: 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .chart-bar {
            width: 100%;
            max-width: 36px;
            background: linear-gradient(to top, var(--gradient-start), var(--gradient-end));
            border-radius: 10px 10px 4px 4px;
            min-height: 20px;
            transition: height 0.8s cubic-bezier(0.22,1,0.36,1), filter 0.3s;
            position: relative;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }
        .chart-bar:hover { filter: brightness(1.1); }
        .chart-bar-wrapper .bar-value {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
        }
        .chart-bar-wrapper .bar-label {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* ===== ORDERS ===== */
        .orders-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px 22px 14px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
        }
        .orders-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .orders-card .card-header h3 {
            font-size: 17px;
            font-weight: 600;
        }
        .orders-card .card-header a {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 10px;
            background: rgba(108,99,255,0.06);
            transition: all 0.2s;
        }
        .orders-card .card-header a:active { background: rgba(108,99,255,0.12); transform: scale(0.95); }
        .order-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f0f2f5;
            transition: background 0.2s;
            margin: 0 -6px;
            padding-left: 6px;
            padding-right: 6px;
            border-radius: 12px;
        }
        .order-item:last-child { border-bottom: none; }
        .order-item:active { background: rgba(108,99,255,0.04); }
        .order-item .order-avatar {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f0eafe, #e4d5ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: var(--primary);
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(108,99,255,0.1);
        }
        .order-item .order-info {
            flex: 1;
            min-width: 0;
        }
        .order-item .order-info .name {
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .order-item .order-info .meta {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
            flex-wrap: wrap;
        }
        .order-item .order-info .meta .dot {
            width: 3px;
            height: 3px;
            background: #ccc;
            border-radius: 50%;
        }
        .order-item .order-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }
        .order-item .order-amount {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-primary);
        }
        .status-pill {
            font-size: 10px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .status-pill.pending { background: #fef0e6; color: #f57c00; }
        .status-pill.confirmed { background: #e8edfd; color: #4a6cf7; }
        .status-pill.shipped { background: #e6f7ed; color: #0caa5e; }
        .status-pill.delivered { background: #e6f7ed; color: #0caa5e; }
        .status-pill.cancelled { background: #fee; color: #e74c3c; }
        .status-pill.processing { background: #e8edfd; color: #4a6cf7; }

        .empty-state {
            text-align: center;
            padding: 40px 0 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 40px;
            color: #ddd;
            display: block;
            margin-bottom: 12px;
        }

        /* ===== BOTTOM NAV (Mobile) ===== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(var(--bottom-nav-height) + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: none;
            align-items: center;
            justify-content: space-around;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.08);
            border-top: 1px solid rgba(0,0,0,0.04);
            z-index: 200;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: var(--text-secondary);
            font-size: 10px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 14px;
            transition: all 0.2s;
            position: relative;
            min-width: 56px;
            flex: 1;
        }
        .bottom-nav .nav-item i {
            font-size: 20px;
            transition: all 0.3s cubic-bezier(0.22,1,0.36,1);
        }
        .bottom-nav .nav-item.active {
            color: var(--primary);
        }
        .bottom-nav .nav-item.active i {
            transform: translateY(-2px);
        }
        .bottom-nav .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            width: 20px;
            height: 4px;
            background: var(--primary);
            border-radius: 4px;
        }
        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 6px;
            background: var(--secondary);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
            line-height: 1.5;
            box-shadow: 0 2px 6px rgba(255,107,107,0.3);
        }

        /* ===== SIDEBAR OVERLAY ===== */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 85;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 300px;
                --header-height: 60px;
                --bottom-nav-height: 68px;
            }
            body { padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 12px); }
            .menu-btn { display: flex; }
            .sidebar {
                top: 0;
                transform: translateX(-100%);
                box-shadow: 8px 0 40px rgba(0,0,0,0.15);
                border-right: none;
                width: var(--sidebar-width);
                border-radius: 0 24px 24px 0;
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-profile {
                border-radius: 0 24px 0 0;
                padding: 28px 20px 24px;
            }
            .main-content {
                margin-left: 0;
                padding: 16px 16px 90px;
            }
            .bottom-nav { display: flex; }
            .header {
                padding: 10px 14px;
                min-height: var(--header-height);
            }
            .brand h1 { font-size: 16px; }
            .brand span { font-size: 10px; }
            .admin-avatar { width: 36px; height: 36px; font-size: 14px; }
            .header-btn { width: 36px; height: 36px; font-size: 16px; }
            
            .greeting h2 { font-size: 20px; }
            .greeting p { font-size: 13px; }
            
            .stats-grid { gap: 10px; margin: 16px 0 20px; }
            .stat-card { padding: 16px 14px; border-radius: var(--radius-sm); }
            .stat-card .value { font-size: 20px; }
            .stat-card .icon { width: 40px; height: 40px; font-size: 16px; }
            .stat-card .label { font-size: 11px; }
            
            .chart-card { padding: 16px; border-radius: var(--radius-sm); }
            .chart-bars { gap: 10px; height: 150px; }
            .chart-bar { max-width: 28px; border-radius: 8px 8px 3px 3px; }
            
            .orders-card { padding: 16px; border-radius: var(--radius-sm); }
            .order-item { padding: 12px 6px; gap: 12px; }
            .order-item .order-avatar { width: 42px; height: 42px; font-size: 15px; }
            .order-item .order-info .name { font-size: 13px; }
            .order-item .order-info .meta { font-size: 11px; }
            .order-item .order-amount { font-size: 14px; }
            .status-pill { font-size: 9px; padding: 3px 10px; }
        }

        @media (max-width: 420px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card .value { font-size: 18px; }
            .brand h1 { font-size: 14px; }
            .bottom-nav .nav-item { font-size: 9px; min-width: 48px; padding: 4px 6px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .chart-bars { gap: 8px; height: 130px; }
            .chart-bar { max-width: 24px; }
            .chart-bar-wrapper { min-width: 40px; }
            .chart-bar-wrapper .bar-value { font-size: 10px; }
            .chart-bar-wrapper .bar-label { font-size: 10px; }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeUp {
            from { transform: translateY(24px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .stat-card, .chart-card, .orders-card {
            animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) forwards;
            opacity: 0;
        }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }
        .chart-card { animation-delay: 0.25s; }
        .orders-card { animation-delay: 0.35s; }
        
        .bottom-nav {
            animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) forwards;
        }
    </style>
</head>
<body>

<div class="app">

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="brand">
                <i class="fas fa-store-alt"></i>
                <div>
                    <h1><?php echo $siteName; ?></h1>
                    <span>Admin Panel</span>
                </div>
            </div>
        </div>
        <div class="header-right">
            <button class="header-btn" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="badge-dot"></span>
            </button>
            <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
        </div>
    </header>

    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== SIDEBAR (Redesigned) ===== -->
    <aside class="sidebar" id="sidebar">
        
        <!-- Profile Header -->
        <div class="sidebar-profile">
            <div class="profile-top">
                <div class="profile-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
                <div class="profile-info">
                    <h4><?php echo $adminName; ?></h4>
                    <p>Administrator</p>
                    <div class="profile-status">
                        <span class="dot"></span>
                        <span>Online</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="index.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="orders.php" class="nav-item">
                <i class="fas fa-shopping-bag"></i> <span>Orders</span>
                <?php if ($pendingOrders > 0): ?>
                <span class="badge"><?php echo $pendingOrders; ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php" class="nav-item">
                <i class="fas fa-box"></i> <span>Products</span>
            </a>
            <a href="categories.php" class="nav-item">
                <i class="fas fa-tags"></i> <span>Categories</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <i class="fas fa-chart-line"></i> <span>Analytics</span>
            </a>

            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item">
                <i class="fas fa-image"></i> <span>Banners</span>
            </a>
            <a href="facebook-pixel.php" class="nav-item">
                <i class="fab fa-facebook"></i> <span>Facebook Pixel</span>
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i> <span>Settings</span>
            </a>

            <div class="sidebar-footer" style="margin-top:8px;">
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="sidebar-bottom-info">
            v1.0.0 &middot; Mahi Fashion House
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <div class="greeting">
            <h2>👋 স্বাগতম, <?php echo $adminName; ?></h2>
            <p>আজকের ড্যাশবোর্ড সংক্ষিপ্ত বিবরণ</p>
            <div class="greeting-date" id="greetingDate"></div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="top"><span class="icon blue"><i class="fas fa-shopping-bag"></i></span></div>
                <div class="label">মোট অর্ডার</div>
                <div class="value"><?php echo number_format($totalOrders); ?></div>
                <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> <span>12% এই মাসে</span></div>
            </div>
            <div class="stat-card">
                <div class="top"><span class="icon orange"><i class="fas fa-clock"></i></span></div>
                <div class="label">পেন্ডিং</div>
                <div class="value"><?php echo number_format($pendingOrders); ?></div>
                <div class="stat-trend trend-down"><i class="fas fa-arrow-down"></i> <span>দ্রুত চেক করুন</span></div>
            </div>
            <div class="stat-card">
                <div class="top"><span class="icon green"><i class="fas fa-money-bill-wave"></i></span></div>
                <div class="label">মোট বিক্রি</div>
                <div class="value">৳<?php echo number_format($totalSales, 0); ?></div>
                <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> <span>8% গত সপ্তাহে</span></div>
            </div>
            <div class="stat-card">
                <div class="top"><span class="icon purple"><i class="fas fa-box"></i></span></div>
                <div class="label">সক্রিয় প্রোডাক্ট</div>
                <div class="value"><?php echo number_format($totalProducts); ?></div>
                <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> <span>নতুন যুক্ত</span></div>
            </div>
        </div>

        <!-- Chart -->
        <div class="chart-card">
            <div class="card-header">
                <h3>মাসিক বিক্রি</h3>
                <span class="period">শেষ ৬ মাস</span>
            </div>
            <?php if (!empty($monthlySales)): 
                $reversed = array_reverse($monthlySales);
                $maxRevenue = max(array_column($monthlySales, 'revenue'));
                $months = ['','জানু','ফেব্রু','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টে','অক্টো','নভে','ডিসে'];
            ?>
            <div class="chart-scroll-wrapper">
                <div class="chart-bars">
                    <?php foreach ($reversed as $ms): 
                        $heightPercent = $maxRevenue > 0 ? ($ms['revenue'] / $maxRevenue) * 100 : 0;
                        $parts = explode('-', $ms['month']);
                        $monthName = $months[intval($parts[1])] ?? $parts[1];
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="bar-value">৳<?php echo number_format($ms['revenue'], 0); ?></div>
                        <div class="chart-bar" style="height: <?php echo max(20, $heightPercent); ?>%;"></div>
                        <div class="bar-label"><?php echo $monthName; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>কোনো বিক্রি ডেটা নেই</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Orders -->
        <div class="orders-card">
            <div class="card-header">
                <h3>সাম্প্রতিক অর্ডার</h3>
                <a href="orders.php">সব দেখুন</a>
            </div>
            <?php if (!empty($recentOrders)): 
                $statusLabels = [
                    'pending'    => 'পেন্ডিং',
                    'confirmed'  => 'কনফার্মড',
                    'processing' => 'প্রসেসিং',
                    'shipped'    => 'শিপড',
                    'delivered'  => 'ডেলিভার্ড',
                    'cancelled'  => 'ক্যান্সেল্ড'
                ];
                foreach ($recentOrders as $order): 
                    $initial = mb_substr($order['customer_name'], 0, 1, 'UTF-8');
                    $statusClass = $order['status'] ?? 'pending';
            ?>
            <div class="order-item">
                <div class="order-avatar"><?php echo $initial; ?></div>
                <div class="order-info">
                    <div class="name"><?php echo $order['customer_name']; ?></div>
                    <div class="meta">
                        <span>#<?php echo $order['order_number']; ?></span>
                        <span class="dot"></span>
                        <span><?php echo date('d M, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                </div>
                <div class="order-right">
                    <div class="order-amount">৳<?php echo number_format($order['total_amount'], 0); ?></div>
                    <span class="status-pill <?php echo $statusClass; ?>">
                        <?php echo $statusLabels[$statusClass] ?? $statusClass; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>কোনো অর্ডার নেই</p>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ===== BOTTOM NAV (Mobile) ===== -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item active">
            <i class="fas fa-home"></i>
            <span>ড্যাশ</span>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-shopping-bag"></i>
            <span>অর্ডার</span>
            <?php if ($pendingOrders > 0): ?>
            <span class="badge"><?php echo $pendingOrders; ?></span>
            <?php endif; ?>
        </a>
        <a href="products.php" class="nav-item">
            <i class="fas fa-box"></i>
            <span>প্রোডাক্ট</span>
        </a>
        <a href="analytics.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>ভিজিটর</span>
        </a>
        <a href="settings.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>সেটিংস</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>লগআউট</span>
        </a>
    </nav>

</div>

<script>
    (function() {
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) closeSidebar();
            else openSidebar();
        });

        overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
        });

        document.querySelectorAll('.sidebar .nav-item').forEach(function(item) {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        // Active State Sync
        const currentPath = window.location.pathname.split('/').pop() || 'index.php';
        document.querySelectorAll('.bottom-nav .nav-item, .sidebar .nav-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPath) {
                item.classList.add('active');
                item.parentElement.querySelectorAll('.nav-item').forEach(sib => {
                    if (sib !== item) sib.classList.remove('active');
                });
            }
        });

        // Greeting Date
        const dateEl = document.getElementById('greetingDate');
        if (dateEl) {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('bn-BD', options);
        }

        // Number Counter Animation
        const counters = document.querySelectorAll('.stat-card .value');
        counters.forEach(counter => {
            const text = counter.textContent;
            const num = parseInt(text.replace(/[^\d]/g, ''));
            if (!num) return;
            const prefix = text.replace(/[\d,]/g, '');
            let start = 0;
            const duration = 1200;
            const startTime = performance.now();
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const ease = 1 - Math.pow(1 - progress, 3);
                const current = Math.floor(ease * num);
                counter.textContent = prefix + current.toLocaleString('bn-BD');
                if (progress < 1) requestAnimationFrame(update);
            }
            requestAnimationFrame(update);
        });
    })();
</script>

</body>
</html>