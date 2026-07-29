<?php
require_once '../Config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$uploadDir = __DIR__ . '/../uploads/banners/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $title = clean($_POST['title'] ?? '');
        $link = clean($_POST['link'] ?? '#');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        try {
            if ($id > 0) {
                // Update
                if (!empty($_FILES['image']['name'])) {
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fileName = 'banner_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName);
                    
                    // Delete old image
                    $old = fetchOne("SELECT image FROM banners WHERE id = ?", [$id]);
                    if ($old && file_exists($uploadDir . $old['image'])) {
                        unlink($uploadDir . $old['image']);
                    }
                    
                    query("UPDATE banners SET title = ?, image = ?, link = ?, sort_order = ?, status = ? WHERE id = ?",
                        [$title, $fileName, $link, $sortOrder, $status, $id]);
                } else {
                    query("UPDATE banners SET title = ?, link = ?, sort_order = ?, status = ? WHERE id = ?",
                        [$title, $link, $sortOrder, $status, $id]);
                }
                $message = 'ব্যানার আপডেট হয়েছে!';
            } else {
                // Insert
                if (empty($_FILES['image']['name'])) {
                    $error = 'ব্যানার ছবি আপলোড করুন!';
                } else {
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fileName = 'banner_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName);
                    
                    query("INSERT INTO banners (title, image, link, sort_order, status) VALUES (?, ?, ?, ?, ?)",
                        [$title, $fileName, $link, $sortOrder, $status]);
                    $message = 'ব্যানার যোগ হয়েছে!';
                }
            }
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $img = fetchOne("SELECT image FROM banners WHERE id = ?", [$id]);
            if ($img && file_exists($uploadDir . $img['image'])) {
                unlink($uploadDir . $img['image']);
            }
            query("DELETE FROM banners WHERE id = ?", [$id]);
            $message = 'ব্যানার মুছে ফেলা হয়েছে!';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
}

$banners = fetchAll("SELECT * FROM banners ORDER BY sort_order ASC");
$editBanner = null;
if (isset($_GET['edit'])) {
    $editBanner = fetchOne("SELECT * FROM banners WHERE id = ?", [intval($_GET['edit'])]);
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
    <title>ব্যানারস – <?php echo $siteName; ?></title>
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
            padding: 28px;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full { grid-column: 1 / -1; }
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .form-input {
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
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
        }
        .form-input[type="file"] { padding: 10px; }
        
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

        /* ===== BANNERS GRID ===== */
        .banners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .banner-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            transition: all 0.25s cubic-bezier(0.22,1,0.36,1);
            animation: fadeUp 0.5s ease forwards;
            opacity: 0;
        }
        .banner-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .banner-card:active { transform: scale(0.98); }
        .banner-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }
        .banner-card:hover .banner-img { transform: scale(1.03); }
        .banner-info { padding: 16px 18px 12px; }
        .banner-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .banner-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .status-active { background: #e6f7ed; color: #0caa5e; }
        .status-inactive { background: #fee; color: #e74c3c; }
        .banner-actions {
            display: flex;
            gap: 8px;
            padding: 0 18px 16px;
        }
        .banner-actions .btn { flex: 1; justify-content: center; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            animation: fadeUp 0.5s ease forwards;
        }
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
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
            .form-group.full { grid-column: 1; }
            
            .banners-grid { grid-template-columns: 1fr; gap: 14px; }
            .banner-img { height: 160px; }
            .banner-card { border-radius: var(--radius-sm); }
            
            .top-bar { flex-direction: row; align-items: center; }
            .btn { padding: 10px 18px; font-size: 13px; }
        }

        @media (max-width: 420px) {
            .brand h1 { font-size: 14px; }
            .bottom-nav .nav-item { font-size: 9px; min-width: 48px; padding: 4px 6px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .form-section { padding: 14px; }
            .banner-img { height: 140px; }
            .banner-info { padding: 12px 14px 10px; }
            .banner-actions { padding: 0 14px 12px; }
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
        .banner-card:nth-child(1) { animation-delay: 0.05s; }
        .banner-card:nth-child(2) { animation-delay: 0.10s; }
        .banner-card:nth-child(3) { animation-delay: 0.15s; }
        .banner-card:nth-child(4) { animation-delay: 0.20s; }
        .banner-card:nth-child(5) { animation-delay: 0.25s; }
        .banner-card:nth-child(6) { animation-delay: 0.30s; }
        
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
            <a href="categories.php" class="nav-item">
                <i class="fas fa-tags"></i> <span>Categories</span>
            </a>
            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item active">
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

        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">ব্যানার ম্যানেজমেন্ট</h1>
            <a href="banners.php<?php echo $editBanner ? '' : '?add=1'; ?>" class="btn <?php echo $editBanner || isset($_GET['add']) ? 'btn-secondary' : 'btn-primary'; ?>">
                <i class="fas <?php echo $editBanner || isset($_GET['add']) ? 'fa-list' : 'fa-plus'; ?>"></i>
                <?php echo $editBanner || isset($_GET['add']) ? 'লিস্ট দেখুন' : 'নতুন ব্যানার'; ?>
            </a>
        </div>

        <!-- Alert -->
        <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ===== FORM ===== -->
        <?php if ($editBanner || isset($_GET['add'])): ?>
        <div class="form-section">
            <h2><?php echo $editBanner ? 'ব্যানার এডিট করুন' : 'নতুন ব্যানার যোগ করুন'; ?></h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $editBanner['id'] ?? 0; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">ব্যানার টাইটেল</label>
                        <input type="text" name="title" class="form-input" value="<?php echo $editBanner['title'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">লিংক (ঐচ্ছিক)</label>
                        <input type="text" name="link" class="form-input" value="<?php echo $editBanner['link'] ?? '#'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">সর্ট অর্ডার</label>
                        <input type="number" name="sort_order" class="form-input" value="<?php echo $editBanner['sort_order'] ?? '0'; ?>">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">ব্যানার ছবি <?php echo $editBanner ? '(নতুন ছবি আপলোড করলে পুরাতনটি পরিবর্তন হবে)' : '*'; ?></label>
                        <input type="file" name="image" class="form-input" accept="image/*" <?php echo $editBanner ? '' : 'required'; ?>>
                        <small style="color: var(--text-secondary); font-size: 12px;">রেকমেন্ডেড সাইজ: ৯৬০ x ৫০০ পিক্সেল</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="status" <?php echo ($editBanner['status'] ?? 1) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                            সক্রিয়
                        </label>
                    </div>
                    <div class="form-group" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $editBanner ? 'আপডেট' : 'সেভ করুন'; ?>
                        </button>
                        <a href="banners.php" class="btn btn-secondary">বাতিল</a>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ===== BANNERS GRID ===== -->
        <?php if (!$editBanner && !isset($_GET['add'])): ?>
        <?php if (!empty($banners)): ?>
        <div class="banners-grid">
            <?php foreach ($banners as $index => $b): ?>
            <div class="banner-card" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                <img src="../uploads/banners/<?php echo $b['image']; ?>" alt="" class="banner-img">
                <div class="banner-info">
                    <div class="banner-title"><?php echo $b['title'] ?: 'ব্যানার #' . $b['id']; ?></div>
                    <div class="banner-meta">
                        <span>অর্ডার: <?php echo $b['sort_order']; ?></span>
                        <span class="status-badge <?php echo $b['status'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $b['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                        </span>
                    </div>
                </div>
                <div class="banner-actions">
                    <a href="banners.php?edit=<?php echo $b['id']; ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-edit"></i> এডিট
                    </a>
                    <form method="POST" style="display:inline; flex: 1;" onsubmit="return confirm('ব্যানার মুছে ফেলবেন?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="width: 100%;">
                            <i class="fas fa-trash"></i> ডিলিট
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-image"></i>
            <h3>কোনো ব্যানার পাওয়া যায়নি</h3>
            <p>নতুন ব্যানার যোগ করুন</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>

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
        const currentPath = window.location.pathname.split('/').pop() || 'banners.php';
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
        document.querySelectorAll('a, button, .banner-card, .btn').forEach(el => {
            el.addEventListener('touchstart', function() {}, {passive: true});
        });
    })();
</script>

</body>
</html>