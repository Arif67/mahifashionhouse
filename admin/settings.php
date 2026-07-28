<?php
require_once '../Config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'general') {
        $settings = [
            'site_name' => $_POST['site_name'] ?? 'Mahi Fashion House',
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'meta_keywords' => $_POST['meta_keywords'] ?? '',
            'phone_number' => $_POST['phone_number'] ?? '',
            'whatsapp_number' => $_POST['whatsapp_number'] ?? '',
            'facebook_link' => $_POST['facebook_link'] ?? '',
            'exchange_policy' => $_POST['exchange_policy'] ?? '',
            'return_policy' => $_POST['return_policy'] ?? '',
            'shipping_inside_dhaka' => $_POST['shipping_inside_dhaka'] ?? '70',
            'shipping_outside_dhaka' => $_POST['shipping_outside_dhaka'] ?? '120',
        ];
        
        try {
            foreach ($settings as $key => $value) {
                query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
            }
            $message = 'সাধারণ সেটিংস আপডেট হয়েছে!';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    
    if ($action === 'pixel') {
        $pixelCode = $_POST['meta_pixel_code'] ?? '';
        $googleTag = $_POST['google_tag_code'] ?? '';
        $metaAccessToken = trim($_POST['meta_access_token'] ?? '');
        $metaTestEventCode = trim($_POST['meta_test_event_code'] ?? '');
        $metaCapiEnabled = isset($_POST['meta_capi_enabled']) ? '1' : '0';

        try {
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_pixel_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$pixelCode, $pixelCode]);
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('google_tag_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$googleTag, $googleTag]);
            // পাসওয়ার্ড ফিল্ডের মতো — খালি রেখে সেভ করলে আগের Access Token মুছে যাবে না
            if ($metaAccessToken !== '') {
                query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_access_token', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$metaAccessToken, $metaAccessToken]);
            }
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_test_event_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$metaTestEventCode, $metaTestEventCode]);
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_capi_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$metaCapiEnabled, $metaCapiEnabled]);
            $message = 'পিক্সেল/ট্যাগ সেটিংস আপডেট হয়েছে!';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }

    if ($action === 'clear_meta_token') {
        try {
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_access_token', '') ON DUPLICATE KEY UPDATE setting_value = ''");
            $message = 'Meta Access Token মুছে ফেলা হয়েছে!';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    
    if ($action === 'password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'সব ফিল্ড পূরণ করুন!';
        } elseif ($newPass !== $confirmPass) {
            $error = 'নতুন পাসওয়ার্ড মিলছে না!';
        } elseif (strlen($newPass) < 6) {
            $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে!';
        } else {
            try {
                $admin = fetchOne("SELECT * FROM admin_users WHERE username = ?", [$_SESSION['admin_username']]);
                if ($admin && password_verify($currentPass, $admin['password'])) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    query("UPDATE admin_users SET password = ? WHERE id = ?", [$hash, $admin['id']]);
                    $message = 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!';
                } else {
                    $error = 'বর্তমান পাসওয়ার্ড ভুল!';
                }
            } catch (Exception $e) {
                $error = 'ত্রুটি: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'upload_logo') {
        $uploadDir = __DIR__ . '/../uploads/favicon/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        if (!empty($_FILES['site_logo']['name'])) {
            $ext = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
            $fileName = 'site_logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadDir . $fileName)) {
                query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$fileName, $fileName]);
                $message = 'লোগো আপলোড হয়েছে!';
            }
        }
    }
    
    if ($action === 'upload_favicon') {
        $uploadDir = __DIR__ . '/../uploads/favicon/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        if (!empty($_FILES['site_favicon']['name'])) {
            $ext = pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION);
            $fileName = 'favicon_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $uploadDir . $fileName)) {
                query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_favicon', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$fileName, $fileName]);
                $message = 'ফেভিকন আপলোড হয়েছে!';
            }
        }
    }
}

// Get current settings
$settings = getAllSettings();

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
    <title>সেটিংস – <?php echo $siteName; ?></title>
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

        /* ===== SETTINGS GRID ===== */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .settings-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 28px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            animation: fadeUp 0.5s ease forwards;
            opacity: 0;
        }
        .settings-card:nth-child(1) { animation-delay: 0.05s; }
        .settings-card:nth-child(2) { animation-delay: 0.10s; }
        .settings-card:nth-child(3) { animation-delay: 0.15s; }
        .settings-card:nth-child(4) { animation-delay: 0.20s; }
        
        .card-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        .card-title i { color: var(--primary); font-size: 18px; }

        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Hind Siliguri', sans-serif;
            outline: none;
            transition: all 0.2s;
            background: #fff;
            -webkit-appearance: none;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
        }
        .form-textarea { min-height: 80px; resize: vertical; }
        .code-input { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; min-height: 100px; }
        
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

        .preview-area {
            margin-top: 10px;
            padding: 16px;
            background: #fafbfc;
            border-radius: 12px;
            text-align: center;
            border: 2px dashed #e0e0e0;
            transition: all 0.2s;
        }
        .preview-area:hover { border-color: var(--primary-light); }
        .preview-area img { max-height: 60px; border-radius: 8px; }
        .preview-area .no-preview { color: var(--text-secondary); font-size: 13px; }
        .preview-area .no-preview i { font-size: 28px; margin-bottom: 6px; display: block; color: #ddd; }

        .pixel-info {
            background: linear-gradient(135deg, #e3f2fd, #f0f7ff);
            border-left: 4px solid var(--primary);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
        }
        .pixel-info code {
            background: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-family: 'SF Mono', 'Fira Code', monospace;
            color: var(--primary);
            border: 1px solid #e0e0e0;
        }
        .pixel-info strong { color: var(--text-primary); }

        .shipping-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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
            .settings-grid { grid-template-columns: 1fr; }
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
            
            .settings-card { padding: 20px; border-radius: var(--radius-sm); }
            .card-title { font-size: 16px; }
            .shipping-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .form-input, .form-textarea { font-size: 16px; }
        }

        @media (max-width: 420px) {
            .brand h1 { font-size: 14px; }
            .bottom-nav .nav-item { font-size: 9px; min-width: 48px; padding: 4px 6px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .settings-card { padding: 16px; }
            .card-title { font-size: 15px; }
            .pixel-info { font-size: 12px; padding: 12px; }
            .preview-area { padding: 12px; }
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
            <a href="banners.php" class="nav-item">
                <i class="fas fa-image"></i> <span>Banners</span>
            </a>
            <a href="settings.php" class="nav-item active">
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
            <h1 class="page-title">সেটিংস</h1>
        </div>

        <!-- Alert -->
        <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ===== SETTINGS GRID ===== -->
        <div class="settings-grid">

            <!-- General Settings -->
            <div class="settings-card">
                <h2 class="card-title"><i class="fas fa-cog"></i>সাধারণ সেটিংস</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="general">
                    
                    <div class="form-group">
                        <label class="form-label">সাইটের নাম</label>
                        <input type="text" name="site_name" class="form-input" value="<?php echo $settings['site_name'] ?? 'Mahi Fashion House'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">মেটা টাইটেল</label>
                        <input type="text" name="meta_title" class="form-input" value="<?php echo $settings['meta_title'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">মেটা ডেসক্রিপশন</label>
                        <textarea name="meta_description" class="form-textarea"><?php echo $settings['meta_description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">মেটা কীওয়ার্ড</label>
                        <input type="text" name="meta_keywords" class="form-input" value="<?php echo $settings['meta_keywords'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ফোন নাম্বার</label>
                        <input type="text" name="phone_number" class="form-input" value="<?php echo $settings['phone_number'] ?? '01849518188'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">WhatsApp নাম্বার</label>
                        <input type="text" name="whatsapp_number" class="form-input" value="<?php echo $settings['whatsapp_number'] ?? '+8801849518188'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Facebook লিংক</label>
                        <input type="url" name="facebook_link" class="form-input" value="<?php echo $settings['facebook_link'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">এক্সচেঞ্জ পলিসি</label>
                        <textarea name="exchange_policy" class="form-textarea"><?php echo $settings['exchange_policy'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">রিটার্ন পলিসি</label>
                        <textarea name="return_policy" class="form-textarea"><?php echo $settings['return_policy'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="shipping-grid">
                        <div class="form-group">
                            <label class="form-label">ঢাকার ভিতরে শিপিং</label>
                            <input type="number" name="shipping_inside_dhaka" class="form-input" value="<?php echo $settings['shipping_inside_dhaka'] ?? '70'; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ঢাকার বাহিরে শিপিং</label>
                            <input type="number" name="shipping_outside_dhaka" class="form-input" value="<?php echo $settings['shipping_outside_dhaka'] ?? '120'; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 8px;">
                        <i class="fas fa-save"></i>সেভ করুন
                    </button>
                </form>
            </div>

            <!-- Logo & Favicon -->
            <div class="settings-card">
                <h2 class="card-title"><i class="fas fa-image"></i>লোগো ও ফেভিকন</h2>
                
                <form method="POST" action="" enctype="multipart/form-data" style="margin-bottom: 24px;">
                    <input type="hidden" name="action" value="upload_logo">
                    <div class="form-group">
                        <label class="form-label">সাইট লোগো</label>
                        <input type="file" name="site_logo" class="form-input" accept="image/*" style="padding: 10px;">
                    </div>
                    <div class="preview-area">
                        <?php if (!empty($settings['site_logo'])): ?>
                        <img src="../uploads/favicon/<?php echo $settings['site_logo']; ?>" alt="Current Logo">
                        <?php else: ?>
                        <div class="no-preview"><i class="fas fa-image"></i>কোনো লোগো আপলোড করা হয়নি</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 12px;">
                        <i class="fas fa-upload"></i>লোগো আপলোড
                    </button>
                </form>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_favicon">
                    <div class="form-group">
                        <label class="form-label">ফেভিকন</label>
                        <input type="file" name="site_favicon" class="form-input" accept="image/*" style="padding: 10px;">
                    </div>
                    <div class="preview-area">
                        <?php if (!empty($settings['site_favicon'])): ?>
                        <img src="../uploads/favicon/<?php echo $settings['site_favicon']; ?>" alt="Current Favicon" style="max-height: 32px;">
                        <?php else: ?>
                        <div class="no-preview"><i class="fas fa-globe"></i>কোনো ফেভিকন আপলোড করা হয়নি</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 12px;">
                        <i class="fas fa-upload"></i>ফেভিকন আপলোড
                    </button>
                </form>
            </div>

            <!-- Meta Pixel -->
            <div class="settings-card">
                <h2 class="card-title"><i class="fab fa-facebook"></i>Meta Pixel / Google Tag</h2>
                
                <div class="pixel-info">
                    <i class="fas fa-info-circle" style="margin-right: 5px; color: var(--primary);"></i>
                    <strong>Meta Pixel ID:</strong> শুধুমাত্র Pixel ID নম্বরটি দিন (যেমন: <code>123456789012345</code>)। পুরো কোড নয়।<br>
                    <strong>Google Tag ID:</strong> শুধুমাত্র Tracking ID দিন (যেমন: <code>G-XXXXXXXXXX</code> বা <code>AW-XXXXXXXXXX</code>)।
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="pixel">
                    
                    <div class="form-group">
                        <label class="form-label">Meta Pixel ID</label>
                        <input type="text" name="meta_pixel_code" class="form-input" placeholder="123456789012345" value="<?php echo $settings['meta_pixel_code'] ?? ''; ?>">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">পিক্সেল ID ছাড়া অন্য কিছু দেবেন না</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Google Tag / Conversion ID</label>
                        <input type="text" name="google_tag_code" class="form-input" placeholder="G-XXXXXXXXXX বা AW-XXXXXXXXXX" value="<?php echo $settings['google_tag_code'] ?? ''; ?>">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">Google Analytics বা Ads Tracking ID</small>
                    </div>

                    <hr style="border: none; border-top: 1px solid var(--border); margin: 20px 0;">

                    <div class="pixel-info" style="margin-bottom: 16px;">
                        <i class="fas fa-server" style="margin-right: 5px; color: var(--primary);"></i>
                        <strong>Conversions API (Server-Side):</strong> Browser Pixel বন্ধ হয়ে গেলে বা Ad Blocker থাকলেও সার্ভার থেকে সরাসরি Meta-তে ইভেন্ট পাঠানো হবে। Access Token Meta Events Manager থেকে সংগ্রহ করুন।
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="meta_capi_enabled" value="1" <?php echo (($settings['meta_capi_enabled'] ?? '0') === '1') ? 'checked' : ''; ?> style="width: auto; margin-right: 6px;">
                            Conversions API চালু করুন (Enable)
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Access Token</label>
                        <input type="password" name="meta_access_token" class="form-input" placeholder="<?php echo !empty($settings['meta_access_token']) ? '•••••••• (সেভ করা আছে — পরিবর্তন করতে নতুন টোকেন লিখুন)' : 'Meta Events Manager থেকে Access Token পেস্ট করুন'; ?>" autocomplete="off">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">নিরাপত্তার জন্য টোকেন খালি দেখানো হয়; খালি রেখে সেভ করলে আগেরটাই থাকবে।</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Test Event Code (ঐচ্ছিক)</label>
                        <input type="text" name="meta_test_event_code" class="form-input" placeholder="TEST12345" value="<?php echo $settings['meta_test_event_code'] ?? ''; ?>">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">Meta Events Manager-এর Test Events ট্যাব থেকে পাওয়া কোড — শুধু টেস্টের সময় ব্যবহার করুন, লাইভে খালি রাখুন।</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 8px;">
                        <i class="fas fa-save"></i>সেভ করুন
                    </button>
                </form>
                <?php if (!empty($settings['meta_access_token'])): ?>
                <form method="POST" action="" onsubmit="return confirm('Access Token মুছে ফেলতে চান? Conversions API কাজ করা বন্ধ হয়ে যাবে।');" style="margin-top: 8px;">
                    <input type="hidden" name="action" value="clear_meta_token">
                    <button type="submit" class="btn btn-secondary" style="color: #DC2626;">
                        <i class="fas fa-trash"></i>Access Token মুছে ফেলুন
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Change Password -->
            <div class="settings-card">
                <h2 class="card-title"><i class="fas fa-lock"></i>পাসওয়ার্ড পরিবর্তন</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="password">
                    
                    <div class="form-group">
                        <label class="form-label">বর্তমান পাসওয়ার্ড</label>
                        <input type="password" name="current_password" class="form-input" placeholder="বর্তমান পাসওয়ার্ড" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">নতুন পাসওয়ার্ড</label>
                        <input type="password" name="new_password" class="form-input" placeholder="নতুন পাসওয়ার্ড (কমপক্ষে ৬ অক্ষর)" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">পাসওয়ার্ড কনফার্ম</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="নতুন পাসওয়ার্ড আবার লিখুন" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 8px;">
                        <i class="fas fa-key"></i>পাসওয়ার্ড পরিবর্তন
                    </button>
                </form>
            </div>

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
        <a href="settings.php" class="nav-item active">
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
        const currentPath = window.location.pathname.split('/').pop() || 'settings.php';
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
        document.querySelectorAll('a, button, .btn').forEach(el => {
            el.addEventListener('touchstart', function() {}, {passive: true});
        });
    })();
</script>

</body>
</html>