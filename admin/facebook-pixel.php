<?php
require_once '../Config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pixelCode = trim($_POST['meta_pixel_code'] ?? '');
        $metaAccessToken = trim($_POST['meta_access_token'] ?? '');
        $metaTestEventCode = trim($_POST['meta_test_event_code'] ?? '');
        $existingToken = trim(getSetting('meta_access_token', ''));
        $tokenOk = $metaAccessToken !== '' || $existingToken !== '';
        // Enable টগল অন + Pixel + Token → CAPI কানেক্ট
        $metaCapiEnabled = (isset($_POST['meta_capi_enabled']) && $pixelCode !== '' && $tokenOk) ? '1' : '0';

        try {
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_pixel_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$pixelCode, $pixelCode]);
            if ($metaAccessToken !== '') {
                query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_access_token', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$metaAccessToken, $metaAccessToken]);
            }
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_test_event_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$metaTestEventCode, $metaTestEventCode]);
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_capi_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$metaCapiEnabled, $metaCapiEnabled]);
            $message = ($metaCapiEnabled === '1')
                ? 'Facebook Pixel ও Conversions API সেভ হয়েছে — CAPI কানেক্টেড!'
                : 'সেটিংস সেভ হয়েছে। CAPI চালু করতে Pixel ID + Access Token দিন এবং Enable টগল অন রাখুন।';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }

    if ($action === 'clear_meta_token') {
        try {
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_access_token', '') ON DUPLICATE KEY UPDATE setting_value = ''");
            query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_capi_enabled', '0') ON DUPLICATE KEY UPDATE setting_value = '0'");
            $message = 'Access Token মুছে ফেলা হয়েছে — CAPI বন্ধ।';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }

    if ($action === 'test_capi') {
        $pixelCode = trim($_POST['meta_pixel_code'] ?? '') ?: getMetaPixelCode();
        $tokenInput = trim($_POST['meta_access_token'] ?? '');
        $token = $tokenInput !== '' ? $tokenInput : getMetaAccessToken();
        $testCode = trim($_POST['meta_test_event_code'] ?? '');
        if ($testCode === '') {
            $testCode = getMetaTestEventCode();
        }
        $testResult = testMetaCapiConnection($pixelCode, $token, $testCode);
        if ($testResult['ok']) {
            $message = $testResult['message'];
        } else {
            $error = $testResult['message'];
        }
    }
}

$settings = getAllSettings();
$pendingCount = fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")['c'] ?? 0;
$siteName = getSetting('site_name', 'Mahi Fashion House');
$adminName = $_SESSION['admin_username'] ?? 'Admin';

$pixelId = $settings['meta_pixel_code'] ?? '';
$hasToken = !empty($settings['meta_access_token']);
$capiOn = ($settings['meta_capi_enabled'] ?? '0') === '1';
$isConnected = $capiOn && $pixelId !== '' && $hasToken;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1877f2">
    <title>Facebook Pixel & CAPI – <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6C63FF;
            --primary-dark: #5a52d5;
            --primary-light: #8b83ff;
            --fb: #1877f2;
            --fb-dark: #0f4fbf;
            --secondary: #FF6B6B;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
            --bg: #f0f2f5;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --shadow: 0 8px 30px rgba(108, 99, 255, 0.12);
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
            font-family: 'Hind Siliguri', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 16px);
            padding-top: var(--safe-top);
            overflow-x: hidden;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }

        .app { max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }
        .header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 24px rgba(102, 126, 234, 0.4);
            min-height: var(--header-height);
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .menu-btn {
            color: #fff; font-size: 20px; width: 42px; height: 42px; border-radius: 12px;
            background: rgba(255,255,255,0.15); display: none; align-items: center; justify-content: center;
        }
        .brand { display: flex; align-items: center; gap: 10px; color: #fff; }
        .brand i { font-size: 24px; }
        .brand h1 { font-size: 19px; font-weight: 700; line-height: 1.2; }
        .brand span { font-size: 11px; opacity: 0.85; display: block; margin-top: -2px; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .admin-avatar {
            width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.20);
            display: flex; align-items: center; justify-content: center; font-weight: 700;
            font-size: 15px; color: #fff; border: 1.5px solid rgba(255,255,255,0.25);
        }

        .sidebar {
            position: fixed; top: var(--header-height); left: 0; bottom: 0; width: var(--sidebar-width);
            background: #fff; box-shadow: 4px 0 30px rgba(0,0,0,0.06); z-index: 90;
            transition: transform 0.35s cubic-bezier(0.32,0.72,0,1); border-right: 1px solid rgba(0,0,0,0.04);
            display: flex; flex-direction: column; overflow-y: auto;
        }
        .sidebar-profile {
            padding: 24px 20px 20px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        }
        .profile-top { display: flex; align-items: center; gap: 14px; }
        .profile-avatar {
            width: 48px; height: 48px; border-radius: 14px; background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 16px;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .profile-info h4 { color: #fff; font-size: 15px; font-weight: 700; }
        .profile-info span { color: rgba(255,255,255,0.8); font-size: 12px; }
        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .sidebar .nav-label {
            font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);
            padding: 12px 14px 6px; font-weight: 700; opacity: 0.7;
        }
        .sidebar .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 3px 0;
            border-radius: 14px; color: var(--text-secondary); font-weight: 500; font-size: 14px;
            transition: all 0.25s; position: relative; overflow: hidden;
        }
        .sidebar .nav-item i { width: 22px; font-size: 18px; text-align: center; }
        .sidebar .nav-item:hover { color: var(--primary); transform: translateX(4px); }
        .sidebar .nav-item.active {
            background: linear-gradient(135deg, rgba(24,119,242,0.12), rgba(24,119,242,0.06));
            color: var(--fb); font-weight: 600;
        }
        .sidebar .nav-item .badge {
            margin-left: auto; background: var(--secondary); color: #fff; font-size: 10px;
            font-weight: 700; padding: 2px 7px; border-radius: 10px; min-width: 20px; text-align: center;
        }
        .sidebar-footer { margin-top: 8px; }
        .sidebar-bottom-info {
            padding: 14px 20px; font-size: 11px; color: var(--text-secondary); border-top: 1px solid #f0f0f0;
        }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 85;
        }
        .sidebar-overlay.active { display: block; }

        .main-content {
            margin-left: var(--sidebar-width); padding: 24px 28px 40px; flex: 1;
        }
        .page-title {
            font-size: 22px; font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 10px;
        }
        .page-title i { color: var(--fb); }
        .page-sub { color: var(--text-secondary); font-size: 14px; margin-bottom: 20px; }

        .alert {
            padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px;
            display: flex; align-items: flex-start; gap: 10px; line-height: 1.5;
        }
        .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .status-bar {
            display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
        }
        .status-chip {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px;
            border-radius: 12px; font-size: 13px; font-weight: 600; background: #fff;
            border: 1px solid #e8e8e8; box-shadow: var(--shadow);
        }
        .status-chip .dot {
            width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1;
        }
        .status-chip.on .dot { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.25); }
        .status-chip.off .dot { background: #f59e0b; }

        .fb-card {
            background: #fff; border-radius: var(--radius); overflow: hidden;
            box-shadow: var(--shadow); border: 1px solid #e9edf4; max-width: 640px;
        }
        .fb-card-head {
            padding: 20px 22px; color: #fff;
            background: linear-gradient(135deg, #1877f2, #0f4fbf);
        }
        .fb-card-head h2 { font-size: 18px; font-weight: 700; margin: 0; }
        .fb-card-head p { margin: 6px 0 0; font-size: 13px; opacity: 0.9; }
        .fb-card-body { padding: 22px; }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary); }
        .form-input {
            width: 100%; padding: 12px 14px; border: 1.5px solid #e5e7eb; border-radius: 12px;
            font-size: 14px; font-family: inherit; background: #fafbfc; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none; border-color: var(--fb); background: #fff;
            box-shadow: 0 0 0 3px rgba(24,119,242,0.15);
        }
        .form-hint { display: block; margin-top: 5px; font-size: 12px; color: var(--text-secondary); line-height: 1.5; }

        .pixel-info {
            background: linear-gradient(135deg, #e8f1ff, #f0f7ff);
            border-left: 4px solid var(--fb);
            padding: 14px 16px; border-radius: 12px; margin-bottom: 18px;
            font-size: 13px; color: var(--text-secondary); line-height: 1.7;
        }
        .pixel-info strong { color: var(--text-primary); }
        .pixel-info code {
            background: #fff; padding: 2px 7px; border-radius: 6px; font-size: 12px;
            color: var(--fb); border: 1px solid #e0e0e0; font-family: ui-monospace, monospace;
        }

        .toggle-row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #eef2f7;
            margin-bottom: 16px;
        }
        .toggle-row .label-wrap strong { display: block; font-size: 14px; }
        .toggle-row .label-wrap span { font-size: 12px; color: var(--text-secondary); }
        .switch { position: relative; width: 48px; height: 28px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .slider {
            position: absolute; inset: 0; background: #cbd5e1; border-radius: 28px; cursor: pointer; transition: 0.25s;
        }
        .switch .slider:before {
            content: ''; position: absolute; width: 22px; height: 22px; left: 3px; top: 3px;
            background: #fff; border-radius: 50%; transition: 0.25s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .switch input:checked + .slider { background: #1877f2; }
        .switch input:checked + .slider:before { transform: translateX(20px); }

        .btn-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 18px; border-radius: 12px; font-size: 14px; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: all 0.2s; border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1877f2, #0f4fbf); color: #fff;
            box-shadow: 0 4px 14px rgba(24,119,242,0.35);
        }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-secondary { background: #f0f2f5; color: var(--text-secondary); }
        .btn-test {
            background: #fff; color: var(--fb); border: 1.5px solid #1877f2;
        }
        .btn-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .events-list {
            margin-top: 20px; max-width: 640px; background: #fff; border-radius: var(--radius);
            padding: 18px 20px; box-shadow: var(--shadow); border: 1px solid #e9edf4;
        }
        .events-list h3 { font-size: 15px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .events-list ul { list-style: none; display: grid; gap: 8px; }
        .events-list li {
            display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-secondary);
            padding: 8px 10px; background: #f8fafc; border-radius: 10px;
        }
        .events-list li i { color: #10b981; }

        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px);
            display: none; align-items: center; justify-content: space-around;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.08); z-index: 200;
        }
        .bottom-nav .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            color: var(--text-secondary); font-size: 10px; font-weight: 600; padding: 6px 10px; min-width: 56px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--primary); }

        @media (max-width: 900px) {
            .menu-btn { display: flex; }
            .sidebar { transform: translateX(-105%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .bottom-nav { display: flex; }
        }
    </style>
</head>
<body>
<div class="app">
    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <div class="brand">
                <i class="fas fa-store-alt"></i>
                <div>
                    <h1><?php echo htmlspecialchars($siteName); ?></h1>
                    <span>Admin Panel</span>
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-profile">
            <div class="profile-top">
                <div class="profile-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
                <div class="profile-info">
                    <h4><?php echo htmlspecialchars($adminName); ?></h4>
                    <span>Administrator</span>
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
            <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i> <span>Analytics</span></a>
            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item"><i class="fas fa-image"></i> <span>Banners</span></a>
            <a href="facebook-pixel.php" class="nav-item active"><i class="fab fa-facebook"></i> <span>Facebook Pixel</span></a>
            <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> <span>Settings</span></a>
            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </div>
        <div class="sidebar-bottom-info">v1.0.0 &middot; Mahi Fashion House</div>
    </aside>

    <main class="main-content">
        <h1 class="page-title"><i class="fab fa-facebook"></i> Facebook Pixel &amp; CAPI</h1>
        <p class="page-sub">Pixel ID, Access Token ও Test Event Code দিয়ে Browser Pixel + Conversions API কানেক্ট করুন</p>

        <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($message); ?></span></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <div class="status-bar">
            <div class="status-chip <?php echo $pixelId ? 'on' : 'off'; ?>">
                <span class="dot"></span>
                Pixel: <?php echo $pixelId ? htmlspecialchars($pixelId) : 'সেট করা নেই'; ?>
            </div>
            <div class="status-chip <?php echo $isConnected ? 'on' : 'off'; ?>">
                <span class="dot"></span>
                CAPI: <?php echo $isConnected ? 'Connected' : 'Disconnected'; ?>
            </div>
            <div class="status-chip <?php echo !empty($settings['meta_test_event_code']) ? 'on' : 'off'; ?>">
                <span class="dot"></span>
                Test Code: <?php echo !empty($settings['meta_test_event_code']) ? htmlspecialchars($settings['meta_test_event_code']) : 'অফ (লাইভ)'; ?>
            </div>
        </div>

        <div class="fb-card">
            <div class="fb-card-head">
                <h2>Facebook / Meta Pixel</h2>
                <p>Browser Pixel + Conversions API (Server-Side) configuration</p>
            </div>
            <div class="fb-card-body">
                <div class="pixel-info">
                    <strong>কীভাবে কানেক্ট করবেন:</strong><br>
                    1. Meta Events Manager থেকে <strong>Pixel ID</strong> কপি করুন<br>
                    2. Settings → Conversions API থেকে <strong>Access Token</strong> জেনারেট করুন<br>
                    3. টেস্ট করতে Test Events ট্যাবের <strong>Test Event Code</strong> দিন (লাইভে খালি রাখুন)<br>
                    4. সেভ করুন — CAPI অটো কানেক্ট হবে। চাইলে “টেস্ট কানেকশন” চাপুন।
                </div>

                <form method="POST" action="" id="pixelForm">
                    <div class="form-group">
                        <label class="form-label">Pixel ID *</label>
                        <input type="text" name="meta_pixel_code" class="form-input" placeholder="123456789012345"
                               value="<?php echo htmlspecialchars($pixelId); ?>" required>
                        <small class="form-hint">শুধুমাত্র Pixel ID নম্বর — পুরো স্ক্রিপ্ট কোড নয় (যেমন: <code>123456789012345</code>)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Access Token *</label>
                        <input type="password" name="meta_access_token" class="form-input" autocomplete="off"
                               placeholder="<?php echo $hasToken ? '•••••••• (সেভ করা আছে — নতুন দিলে আপডেট হবে)' : 'Meta Events Manager থেকে Access Token পেস্ট করুন'; ?>">
                        <small class="form-hint">নিরাপত্তার জন্য টোকেন লুকানো থাকে। খালি রেখে সেভ করলে আগের টোকেন থাকবে।</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Test Event Code (ঐচ্ছিক)</label>
                        <input type="text" name="meta_test_event_code" class="form-input" placeholder="TEST12345"
                               value="<?php echo htmlspecialchars($settings['meta_test_event_code'] ?? ''); ?>">
                        <small class="form-hint">শুধু টেস্টিং এর সময় ব্যবহার করুন। লাইভ সাইটে খালি রাখুন যাতে ইভেন্ট রেগুলার রিপোর্টিং-এ যায়।</small>
                    </div>

                    <div class="toggle-row">
                        <div class="label-wrap">
                            <strong>Conversions API Enable</strong>
                            <span>সার্ভার থেকে Meta-তে ইভেন্ট পাঠাবে (Ad Blocker-এও কাজ করে)</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="meta_capi_enabled" value="1" <?php echo ($capiOn || !$hasToken) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="action" value="save" class="btn btn-primary">
                            <i class="fas fa-save"></i> সেভ ও কানেক্ট
                        </button>
                        <button type="submit" name="action" value="test_capi" class="btn btn-test">
                            <i class="fas fa-plug"></i> টেস্ট কানেকশন
                        </button>
                    </div>
                </form>

                <?php if ($hasToken): ?>
                <form method="POST" action="" style="margin-top:12px;" onsubmit="return confirm('Access Token মুছে ফেলতে চান? CAPI বন্ধ হয়ে যাবে।');">
                    <button type="submit" name="action" value="clear_meta_token" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Access Token মুছুন
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="events-list">
            <h3><i class="fas fa-bolt" style="color:#f59e0b;"></i> যে ইভেন্টগুলো অটো পাঠানো হয়</h3>
            <ul>
                <li><i class="fas fa-check"></i> PageView — সব পেজে</li>
                <li><i class="fas fa-check"></i> ViewContent — প্রোডাক্ট পেজ</li>
                <li><i class="fas fa-check"></i> AddToCart — কার্টে যোগ</li>
                <li><i class="fas fa-check"></i> InitiateCheckout — চেকআউট শুরু</li>
                <li><i class="fas fa-check"></i> Purchase — অর্ডার সম্পন্ন (Pixel + CAPI, একই Event ID)</li>
            </ul>
        </div>
    </main>

    <nav class="bottom-nav">
        <a href="index.php" class="nav-item"><i class="fas fa-home"></i><span>হোম</span></a>
        <a href="orders.php" class="nav-item"><i class="fas fa-shopping-bag"></i><span>অর্ডার</span></a>
        <a href="products.php" class="nav-item"><i class="fas fa-box"></i><span>প্রোডাক্ট</span></a>
        <a href="facebook-pixel.php" class="nav-item active"><i class="fab fa-facebook"></i><span>পিক্সেল</span></a>
        <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>লগআউট</span></a>
    </nav>
</div>

<script>
(function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuToggle = document.getElementById('menuToggle');
    function openSidebar() { sidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    if (menuToggle) menuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
