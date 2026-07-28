<?php
require_once '../Config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = clean($_POST['name'] ?? '');
        $icon = clean($_POST['icon'] ?? 'fa-tag');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        if (empty($name)) {
            $error = 'ক্যাটাগরি নাম দিন!';
        } else {
            try {
                query("INSERT INTO categories (name, icon, sort_order) VALUES (?, ?, ?)", [$name, $icon, $sortOrder]);
                $message = 'ক্যাটাগরি সফলভাবে যোগ হয়েছে!';
            } catch (Exception $e) {
                $error = 'ত্রুটি: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = clean($_POST['name'] ?? '');
        $icon = clean($_POST['icon'] ?? 'fa-tag');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (empty($name)) {
            $error = 'ক্যাটাগরি নাম দিন!';
        } else {
            try {
                query("UPDATE categories SET name = ?, icon = ?, sort_order = ?, status = ? WHERE id = ?", [$name, $icon, $sortOrder, $status, $id]);
                $message = 'ক্যাটাগরি আপডেট হয়েছে!';
            } catch (Exception $e) {
                $error = 'ত্রুটি: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            query("DELETE FROM categories WHERE id = ?", [$id]);
            $message = 'ক্যাটাগরি মুছে ফেলা হয়েছে!';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
}

// Fetch all categories
$categories = fetchAll("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY c.sort_order ASC");

$editCategory = null;
if (isset($_GET['edit'])) {
    $editCategory = fetchOne("SELECT * FROM categories WHERE id = ?", [intval($_GET['edit'])]);
}

$pendingCount = fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")['c'] ?? 0;
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
    <title>ক্যাটাগরিস – <?php echo $siteName; ?></title>
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
            --bottom-nav-height: 72px;
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
        img { max-width: 100%; display: block; }

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
        .sidebar .nav-item.active i { color: var(--primary); }
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
        .sidebar-bottom-info {
            padding: 12px 16px;
            text-align: center;
            font-size: 11px;
            color: var(--text-secondary);
            opacity: 0.6;
            border-top: 1px solid rgba(0,0,0,0.04);
        }

        /* ===== OVERLAY ===== */
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

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px 100px;
            flex: 1;
            transition: margin 0.3s;
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
            animation: fadeUp 0.5s ease forwards;
        }
        .page-title {
            font-size: 24px;
            font-weight: 700;
        }

        /* ===== ALERT ===== */
        .alert {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeUp 0.5s ease 0.1s both;
        }
        .alert-success {
            background: #e6f7ed;
            color: #0caa5e;
        }
        .alert-error {
            background: #fee;
            color: #e74c3c;
        }

        /* ===== FORM ===== */
        .form-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 28px 32px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease forwards;
        }
        .form-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .form-input, .form-select {
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Hind Siliguri', sans-serif;
            outline: none;
            transition: all 0.2s;
            background: #fff;
            width: 100%;
            -webkit-appearance: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            touch-action: manipulation;
        }
        .btn:active { transform: scale(0.95); }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(108,99,255,0.25);
            white-space: nowrap;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108,99,255,0.35);
        }
        .btn-secondary {
            background: #f0f2f5;
            color: var(--text-secondary);
        }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
            box-shadow: 0 4px 12px rgba(231,76,60,0.2);
        }
        .btn-sm {
            padding: 8px 14px;
            font-size: 12px;
            border-radius: 10px;
        }

        /* ===== CATEGORY CARDS (Mobile) ===== */
        .categories-mobile {
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        .category-card {
            background: var(--card-bg);
            border-radius: var(--radius-sm);
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.25s;
            animation: fadeUp 0.5s ease forwards;
            opacity: 0;
        }
        .category-card:active { transform: scale(0.98); }
        .category-card .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(108,99,255,0.2);
        }
        .category-card .card-body { flex: 1; min-width: 0; }
        .category-card .card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .category-card .card-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .category-card .card-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .category-card .card-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .category-card .card-status.active {
            background: #e6f7ed;
            color: #0caa5e;
        }
        .category-card .card-status.inactive {
            background: #fee;
            color: #e74c3c;
        }
        .category-card .card-actions {
            display: flex;
            gap: 6px;
        }

        /* ===== CATEGORY TABLE (Desktop) ===== */
        .categories-table-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            animation: fadeUp 0.5s ease forwards;
        }
        .categories-table-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        .data-table th {
            text-align: left;
            padding: 14px 16px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f0f2f5;
            background: #fafbfc;
        }
        .data-table td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: rgba(108,99,255,0.02); }
        .cat-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f0eafe, #e4d5ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--primary);
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-dot.active {
            background: #0caa5e;
            box-shadow: 0 0 0 2px rgba(12,170,94,0.2);
        }
        .status-dot.inactive {
            background: #e74c3c;
            box-shadow: 0 0 0 2px rgba(231,76,60,0.2);
        }
        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr 1fr; }
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
            .sidebar-profile { border-radius: 0 24px 0 0; padding: 28px 20px 24px; }
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
            
            .page-title { font-size: 20px; }
            
            .form-section { padding: 18px; border-radius: var(--radius-sm); }
            .form-grid { grid-template-columns: 1fr; gap: 14px; }
            .form-grid .btn { width: 100%; justify-content: center; }
            
            .categories-table-section { display: none; }
            .categories-mobile { display: flex; }
        }

        @media (max-width: 420px) {
            .brand h1 { font-size: 14px; }
            .bottom-nav .nav-item { font-size: 9px; min-width: 48px; padding: 4px 6px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .form-section { padding: 14px; }
            .category-card { padding: 12px; gap: 10px; }
            .category-card .card-icon { width: 44px; height: 44px; font-size: 18px; }
            .category-card .card-title { font-size: 14px; }
            .btn-sm { padding: 6px 12px; font-size: 11px; }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeUp {
            from { transform: translateY(24px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .category-card:nth-child(1) { animation-delay: 0.05s; }
        .category-card:nth-child(2) { animation-delay: 0.10s; }
        .category-card:nth-child(3) { animation-delay: 0.15s; }
        .category-card:nth-child(4) { animation-delay: 0.20s; }
        .category-card:nth-child(5) { animation-delay: 0.25s; }
        .category-card:nth-child(6) { animation-delay: 0.30s; }
        
        .bottom-nav {
            animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) forwards;
        }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--text-secondary);
            animation: fadeUp 0.5s ease forwards;
        }
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            display: block;
            margin-bottom: 12px;
        }
    
        /* ===== STATUS TOGGLE ===== */
        .status-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; padding: 8px 0; }
        .status-toggle input { display: none; }
        .toggle-slider { position: relative; width: 46px; height: 26px; background: #e0e0e0; border-radius: 26px; transition: all 0.25s ease; flex-shrink: 0; }
        .toggle-slider::before { content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: all 0.25s cubic-bezier(0.22,1,0.36,1); box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        .status-toggle input:checked + .toggle-slider { background: linear-gradient(135deg, #0caa5e, #4ade80); }
        .status-toggle input:checked + .toggle-slider::before { left: 23px; }
        .toggle-text { font-size: 14px; font-weight: 600; color: var(--text-primary, #1a1a2e); }
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
        <div class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="orders.php" class="nav-item">
                <i class="fas fa-shopping-bag"></i> <span>Orders</span>
                <?php if ($pendingCount > 0): ?>
                <span class="badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php" class="nav-item">
                <i class="fas fa-box"></i> <span>Products</span>
            </a>
            <a href="categories.php" class="nav-item active">
                <i class="fas fa-tags"></i> <span>Categories</span>
            </a>
            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item">
                <i class="fas fa-image"></i> <span>Banners</span>
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

        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">ক্যাটাগরি ম্যানেজমেন্ট</h1>
        </div>

        <!-- Alert -->
        <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ===== FORM ===== -->
        <div class="form-section">
            <h2><?php echo $editCategory ? 'ক্যাটাগরি এডিট করুন' : 'নতুন ক্যাটাগরি যোগ করুন'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editCategory ? 'edit' : 'add'; ?>">
                <?php if ($editCategory): ?>
                <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">ক্যাটাগরি নাম *</label>
                        <input type="text" name="name" class="form-input" placeholder="যেমন: স্টিজ পাঞ্জাবি" value="<?php echo $editCategory['name'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">আইকন (Font Awesome)</label>
                        <input type="text" name="icon" class="form-input" placeholder="যেমন: fa-male" value="<?php echo $editCategory['icon'] ?? 'fa-tag'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">সর্ট অর্ডার</label>
                        <input type="number" name="sort_order" class="form-input" value="<?php echo $editCategory['sort_order'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">স্ট্যাটাস</label>
                        <label class="status-toggle">
                            <input type="checkbox" name="status" value="1" id="statusToggle" <?php echo ($editCategory ? ($editCategory['status'] ? 'checked' : '') : 'checked'); ?> onchange="document.getElementById('statusToggleText').textContent = this.checked ? 'সক্রিয়' : 'নিষ্ক্রিয়';">
                            <span class="toggle-slider"></span>
                            <span class="toggle-text" id="statusToggleText"><?php echo ($editCategory ? ($editCategory['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়') : 'সক্রিয়'); ?></span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas <?php echo $editCategory ? 'fa-save' : 'fa-plus'; ?>"></i>
                        <?php echo $editCategory ? 'আপডেট' : 'যোগ করুন'; ?>
                    </button>
                </div>
                
                <?php if ($editCategory): ?>
                <div style="margin-top: 12px;">
                    <a href="categories.php" class="btn btn-secondary btn-sm">নতুন যোগ করুন</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ===== CATEGORY TABLE (Desktop) ===== -->
        <div class="categories-table-section">
            <h2>সকল ক্যাটাগরি</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>আইকন</th>
                        <th>নাম</th>
                        <th>প্রোডাক্ট</th>
                        <th>অর্ডার</th>
                        <th>স্ট্যাটাস</th>
                        <th>অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <div class="cat-icon"><i class="fas <?php echo $cat['icon']; ?>"></i></div>
                        </td>
                        <td style="font-weight:600;"><?php echo $cat['name']; ?></td>
                        <td><?php echo $cat['product_count']; ?>টি</td>
                        <td><?php echo $cat['sort_order']; ?></td>
                        <td>
                            <span class="status-dot <?php echo $cat['status'] ? 'active' : 'inactive'; ?>"></span>
                            <?php echo $cat['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('মুছে ফেলবেন?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">
                            <div class="empty-state">
                                <i class="fas fa-tags"></i>
                                <p>কোনো ক্যাটাগরি পাওয়া যায়নি</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== CATEGORY CARDS (Mobile) ===== -->
        <div class="categories-mobile">
            <?php foreach ($categories as $index => $cat): ?>
            <div class="category-card" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                <div class="card-icon">
                    <i class="fas <?php echo $cat['icon']; ?>"></i>
                </div>
                <div class="card-body">
                    <div class="card-title"><?php echo $cat['name']; ?></div>
                    <div class="card-meta"><?php echo $cat['product_count']; ?>টি প্রোডাক্ট • অর্ডার: <?php echo $cat['sort_order']; ?></div>
                    <div class="card-footer">
                        <span class="card-status <?php echo $cat['status'] ? 'active' : 'inactive'; ?>">
                            <span class="status-dot <?php echo $cat['status'] ? 'active' : 'inactive'; ?>"></span>
                            <?php echo $cat['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                        </span>
                        <div class="card-actions">
                            <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('মুছে ফেলবেন?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
            <div class="empty-state">
                <i class="fas fa-tags"></i>
                <p>কোনো ক্যাটাগরি পাওয়া যায়নি</p>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ===== BOTTOM NAV (Mobile) ===== -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>ড্যাশ</span>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-shopping-bag"></i>
            <span>অর্ডার</span>
            <?php if ($pendingCount > 0): ?>
            <span class="badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="products.php" class="nav-item">
            <i class="fas fa-box"></i>
            <span>প্রোডাক্ট</span>
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
        const currentPath = window.location.pathname.split('/').pop() || 'categories.php';
        document.querySelectorAll('.bottom-nav .nav-item, .sidebar .nav-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPath) {
                item.classList.add('active');
                item.parentElement.querySelectorAll('.nav-item').forEach(sib => {
                    if (sib !== item) sib.classList.remove('active');
                });
            }
        });

        // Touch feedback
        document.querySelectorAll('a, button, .category-card, .btn').forEach(el => {
            el.addEventListener('touchstart', function() {}, {passive: true});
        });
    })();
</script>

</body>
</html>